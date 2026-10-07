# Casita de Romila - Lanzador de un clic
# Levanta `php artisan serve` en segundo plano (sin consola), abre la app en
# una ventana de navegador dedicada (modo app) y, al cerrar la ventana:
#   1) mata el arbol del navegador,
#   2) cierra la sesion del usuario (POST /lanzador/cerrar-sesion),
#   3) mata el arbol de PHP.
# Si una instancia anterior quedo huerfana (cierre forzado), la limpia al
# iniciar para que el puerto nunca quede bloqueado.
#
# Red local (host != 127.0.0.1 en config.json):
#   - El servidor queda audible en la LAN (--host=$host, normalmente 0.0.0.0).
#   - Se detecta la IP LAN activa y se reescribe SOLO la linea `APP_URL=` del
#     .env a http://<IP>:<puerto> para que assets y rutas generados por el
#     servidor funcionen en cualquier equipo de la red (y sobreviva a DHCP).
#   - Al terminar de levantar se muestra un aviso con la URL para las demas PCs.

$ErrorActionPreference = 'Stop'

$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $base 'config.json'

$config = @{ port = 8000; host = '127.0.0.1'; phpPath = $null; appPath = $null; browser = 'auto' }
if (Test-Path $configPath) {
    $cfg = Get-Content $configPath -Raw | ConvertFrom-Json
    if ($cfg.port)    { $config.port    = [int]$cfg.port }
    if ($cfg.host)    { $config.host    = [string]$cfg.host }
    if ($cfg.phpPath) { $config.phpPath = [string]$cfg.phpPath }
    if ($cfg.appPath) { $config.appPath = [string]$cfg.appPath }
    if ($cfg.browser) { $config.browser = [string]$cfg.browser }
}

$port = $config.port
$appDir = if ($config.appPath -and (Test-Path $config.appPath)) { $config.appPath } else { Join-Path $base '..' }
$appDir = (Resolve-Path $appDir).Path
$baseUrl = "http://127.0.0.1:$port"

$stateDir = Join-Path $env:LOCALAPPDATA 'CasitaDeRomila'
New-Item -ItemType Directory -Force -Path $stateDir | Out-Null
$pidFile = Join-Path $stateDir 'php.pid'
$tokenFile = Join-Path $stateDir 'token.txt'
$profileDir = Join-Path $stateDir 'edge-profile'

$phpProc = $null
$browser = $null
$token = $null

function Mensaje([string]$texto, [string]$tipo = 'Information') {
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show($texto, 'Casita de Romila', 'OK', $tipo) | Out-Null
}

function Test-Puerto([int]$p) {
    $cliente = New-Object System.Net.Sockets.TcpClient
    try {
        $cliente.Connect('127.0.0.1', $p)
        return $true
    } catch {
        return $false
    } finally {
        $cliente.Dispose()
    }
}

function Matar-Proc([int]$id) {
    if (-not $id -or $id -le 0) { return }
    try { & taskkill /PID $id /T /F 2>$null | Out-Null } catch { }
}

function Localizar-Php() {
    if ($config.phpPath -and (Test-Path $config.phpPath)) { return (Resolve-Path $config.phpPath).Path }
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $candidatos = @('C:\php\php.exe', "$env:ProgramFiles\php\php.exe")
    foreach ($c in $candidatos) { if (Test-Path $c) { return $c } }
    return $null
}

function Localizar-Navegador() {
    $rutas = @{
        edge   = @(
            "$env:ProgramFiles(x86)\Microsoft\Edge\Application\msedge.exe",
            "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
        )
        chrome = @(
            "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
            "$env:ProgramFiles(x86)\Google\Chrome\Application\chrome.exe",
            "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
        )
        brave  = @(
            "$env:ProgramFiles\BraveSoftware\Brave-Browser\Application\brave.exe",
            "$env:ProgramFiles(x86)\BraveSoftware\Brave-Browser\Application\brave.exe",
            "$env:LOCALAPPDATA\BraveSoftware\Brave-Browser\Application\brave.exe"
        )
    }
    $orden = switch ($config.browser) {
        'brave'  { @('brave', 'edge', 'chrome') }
        'edge'   { @('edge', 'chrome', 'brave') }
        'chrome' { @('chrome', 'edge', 'brave') }
        default  { @('edge', 'chrome', 'brave') }
    }
    foreach ($nombre in $orden) {
        foreach ($ruta in $rutas[$nombre]) {
            if (Test-Path $ruta) { return $ruta }
        }
    }
    return $null
}

function Obtener-IpLan() {
    # IPv4 preferida de un adaptador activo (no loopback, sin APIPA).
    $enLinea = @()
    try {
        $enLinea = [System.Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces() |
            Where-Object { $_.OperationalStatus -eq 'Up' -and $_.NetworkInterfaceType -ne 'Loopback' } |
            ForEach-Object { $_.GetIPProperties().UnicastAddresses.Address.IPAddressToString }
    } catch { }

    $candidata = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object {
            $_.AddressState -eq 'Preferred' -and
            $_.IPAddress -notlike '127.*' -and
            $_.IPAddress -notlike '169.254.*' -and
            ($enLinea -contains $_.IPAddress)
        } |
        Sort-Object InterfaceIndex |
        Select-Object -First 1

    if (-not $candidata) { return $null }
    return $candidata.IPAddress
}

function Actualizar-AppUrl([string]$appDir, [string]$ip, [int]$port) {
    # Reescribe unicamente la linea `APP_URL=` del .env (UTF-8 sin BOM),
    # preservando el resto del archivo (incluidas credenciales).
    $envPath = Join-Path $appDir '.env'
    if (-not (Test-Path -LiteralPath $envPath)) { return }

    $nueva = "APP_URL=http://${ip}:$port"
    $reemplazada = $false
    $lineas = @(Get-Content -LiteralPath $envPath) | ForEach-Object {
        if ($_ -match '^\s*APP_URL\s*=') { $reemplazada = $true; $nueva } else { $_ }
    }
    if (-not $reemplazada) { $lineas += $nueva }

    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllLines($envPath, $lineas, $encoding)
}

# --- Exposicion en red local: mantiene APP_URL apuntando a la IP LAN ---
# Debe ir DESPUES de las definiciones de Obtener-IpLan/Actualizar-AppUrl:
# en PowerShell una funcion debe estar definida antes de ser invocada.
$lanUrl = $null
if ($config.host -and $config.host -ne '127.0.0.1') {
    try {
        $lanIp = Obtener-IpLan
        if ($lanIp) {
            $lanUrl = "http://${lanIp}:$port"
            Actualizar-AppUrl $appDir $lanIp $port
        }
    } catch {
        $lanUrl = $null
    }
}

function Abrir-Ventana([string]$url) {
    $navegadorPath = Localizar-Navegador
    if (-not $navegadorPath) { throw 'No se encontro Microsoft Edge, Chrome ni Brave. Instala uno de ellos para usar Casita de Romila.' }
    return Start-Process -FilePath $navegadorPath -ArgumentList @("--app=$url", "--user-data-dir=$profileDir", '--start-maximized', '--no-first-run', '--no-default-browser-check') -PassThru
}

try {
    # --- Auto-recuperacion: estado de la instancia anterior ---
    $phpAnterior = $null
    if (Test-Path $pidFile) {
        $raw = Get-Content $pidFile -Raw
        if ($raw -and $raw.Trim() -match '^\d+$') {
            $phpAnterior = [int]$raw.Trim()
        } else {
            Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
        }
    }
    $puertoActivo = Test-Puerto $port

    if ($phpAnterior -and $phpAnterior -gt 0) {
        $procAnterior = Get-Process -Id $phpAnterior -ErrorAction SilentlyContinue
        if ($procAnterior -and $puertoActivo) {
            # El sistema ya esta abierto: solo enfocar la ventana y terminar.
            Abrir-Ventana $baseUrl | Out-Null
            exit 0
        }
        # Huerfano de un cierre forzado: matarlo y seguir limpio.
        Matar-Proc $phpAnterior
        Remove-Item $pidFile, $tokenFile -Force -ErrorAction SilentlyContinue
    } elseif ($puertoActivo) {
        throw "El puerto $port ya esta en uso por otro programa. Cierra ese programa y vuelve a abrir Casita de Romila."
    }

    $php = Localizar-Php
    if (-not $php) { throw 'No se encontro PHP. Instala PHP 8.2+ y vuelve a abrir Casita de Romila.' }

    # --- Limpieza de sesiones expiradas de ejecuciones anteriores ---
    Start-Process -FilePath $php -ArgumentList @('artisan', 'sessions:purge') -WorkingDirectory $appDir -WindowStyle Hidden -Wait | Out-Null

    # --- Arranque del servidor ---
    $token = [guid]::NewGuid().ToString('N')
    $phpProc = Start-Process -FilePath $php -ArgumentList @('artisan', 'serve', "--host=$($config.host)", "--port=$port") -WorkingDirectory $appDir -WindowStyle Hidden -PassThru
    if (-not $phpProc) { throw 'No se pudo iniciar el servidor PHP.' }
    Set-Content -Path $pidFile -Value $phpProc.Id
    Set-Content -Path $tokenFile -Value $token

    $listo = $false
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Milliseconds 500
        $phpProc.Refresh()
        if ($phpProc.HasExited) { break }
        if (Test-Puerto $port) { $listo = $true; break }
    }
    if (-not $listo) {
        throw 'El servidor PHP no levanto a tiempo. Revisa que PostgreSQL este encendido y vuelve a abrir Casita de Romila.'
    }

    # --- Abrir la ventana y esperar a que aparezca ---
    $browser = Abrir-Ventana "$baseUrl/?_lanzador=$token"
    $ventana = $false
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Seconds 1
        if (-not $browser) { break }
        $browser.Refresh()
        if ($browser.HasExited) { break }
        if ($browser.MainWindowHandle -ne 0) { $ventana = $true; break }
    }
    if (-not $ventana) { throw 'No se pudo abrir la ventana de Casita de Romila.' }

    if ($lanUrl) {
        Mensaje "El sistema ya esta disponible en tu red.`n`nOtras computadoras de la red abren:`n$lanUrl" 'Information'
    }

    # --- Vigilancia: al cerrar la ventana, apagar todo ---
    while ($true) {
        Start-Sleep -Seconds 2
        if (-not $browser) { break }
        $browser.Refresh()
        if ($browser.HasExited) { break }
        if ($browser.MainWindowHandle -eq 0) {
            Start-Sleep -Seconds 3
            $browser.Refresh()
            if ($browser.HasExited -or $browser.MainWindowHandle -eq 0) { break }
        }
    }
} catch {
    Mensaje $_.Exception.Message 'Error'
} finally {
    if ($phpProc) {
        if ($browser -and -not $browser.HasExited) { Matar-Proc $browser.Id }
        if ($token) {
            try {
                Invoke-WebRequest -Uri "$baseUrl/lanzador/cerrar-sesion" -Method Post -Body @{ token = $token } -UseBasicParsing -TimeoutSec 5 | Out-Null
            } catch { }
        }
        Matar-Proc $phpProc.Id
        Remove-Item $pidFile, $tokenFile -Force -ErrorAction SilentlyContinue
    }
}

exit 0
