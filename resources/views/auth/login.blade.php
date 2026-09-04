<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión — Casita de Romila</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
        <div class="login-card">
            <div class="card-body p-4 p-sm-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="logo-mark" aria-hidden="true">
                        <i class="bi bi-house-heart-fill"></i>
                    </div>
                    <h1 class="brand mb-0">Casita de Romila</h1>
                </div>

                <h2 class="subtitle text-center mb-4">Iniciar Sesión</h2>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario</label>
                        <input type="text" class="form-control @error('username') is-invalid @enderror"
                               id="username" name="username" value="{{ old('username') }}"
                               placeholder="nombre.usuario" autocomplete="username" required>
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password"
                                   placeholder="••••••••" autocomplete="current-password" required>
                            <button class="btn btn-outline-secondary btn-toggle-password" type="button"
                                    id="togglePassword" aria-label="Mostrar contraseña">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            Mantener sesión abierta
                        </label>
                    </div>

                    <button type="submit" class="btn btn-brand w-100">Iniciar Sesión</button>
                </form>

                <p class="footer-text text-center mb-0 mt-4">Sistema de Gestión v1.0</p>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const input = document.getElementById("password");
            const toggle = document.getElementById("togglePassword");
            if (!input || !toggle) return;
            const icon = toggle.querySelector("i");

            toggle.addEventListener("click", function () {
                const show = input.type === "password";
                input.type = show ? "text" : "password";
                icon.className = show ? "bi bi-eye-slash-fill" : "bi bi-eye-fill";
                toggle.setAttribute("aria-label", show ? "Ocultar contraseña" : "Mostrar contraseña");
            });
        })();
    </script>
</body>
</html>
