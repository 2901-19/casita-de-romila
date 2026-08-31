# Casita de Romila - Lanzador de un clic
# Levanta `php artisan serve` en segundo plano (sin consola), abre la app en
# una ventana de navegador dedicada (modo app) y, al cerrar la ventana:
#   1) mata el arbol del navegador,
#   2) cierra la sesion del usuario (POST /lanzador/cerrar-sesion),
#   3) mata el arbol de PHP.
# Si una instancia anterior quedo huerfana (cierre forzado), la limpia al
# iniciar para que el puerto nunca quede bloqueado.

$ErrorActionPreference = 'Stop'

$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $base 'config.json'

$config = @{ port = 8000; phpPath = $null; appPath = $null; browser = 'auto' }
if (Test-Path $configPath) {
    $cfg = Get-Content $configPath -Raw | ConvertFrom-Json
    if ($cfg.port)    { $config.port    = [int]$cfg.port }
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

function Abrir-Ventana([string]$url) {
    $navegadorPath = Localizar-Navegador
    if (-not $navegadorPath) { throw 'No se encontro Microsoft Edge, Chrome ni Brave. Instala uno de ellos para usar Casita de Romila.' }
    return Start-Process -FilePath $navegadorPath -ArgumentList @("--app=$url", "--user-data-dir=$profileDir", '--start-maximized', '--no-first-run', '--no-default-browser-check') -PassThru
}

try {
    # --- Auto-recuperacion: estado de la instancia anterior ---
    $phpAnterior = $null
    if (Test-Path $pidFile) {
        $phpAnterior = [int]((Get-Content $pidFile -Raw).Trim())
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
    $phpProc = Start-Process -FilePath $php -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', "--port=$port") -WorkingDirectory $appDir -WindowStyle Hidden -PassThru
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
        $browser.Refresh()
        if ($browser.HasExited) { break }
        if ($browser.MainWindowHandle -ne 0) { $ventana = $true; break }
    }
    if (-not $ventana) { throw 'No se pudo abrir la ventana de Casita de Romila.' }

    # --- Vigilancia: al cerrar la ventana, apagar todo ---
    while ($true) {
        Start-Sleep -Seconds 2
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
