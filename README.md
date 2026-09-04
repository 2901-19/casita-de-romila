# Casita de Romila 🏡

Sistema de **gestión de punto de venta (POS)** y **ERP** para la panadería/café *Casita de Romila*.

- **Stack:** Laravel 13 · PHP ^8.3 · Bootstrap 5 · Alpine.js · Vite · Chart.js
- **Moneda:** los productos se almacenan en **USD** (`sale_price`) y se muestran en **Bs** usando el tipo de cambio vigente (`ExchangeRate`).
- **Roles y permisos dinámicos:** roles (Gerente, Recepcionista) con un catálogo de 8 permisos por módulo, gestionados desde la interfaz.
- **Flujo de venta:** POS directo para la mañana + módulo de **Comandas** (pedidos a cocina) para la noche, con entrega por unidades y cobro que genera la venta real vía `CheckoutService`.

---

## Requisitos previos

- **PHP** `^8.3` (con extensiones comunes de Laravel: `pdo`, `pgsql`, `mbstring`, `openssl`, `fileinfo`, etc.)
- **Composer** (gestor de dependencias PHP)
- **Node.js** + **npm** (para compilar los assets con Vite)
- **PostgreSQL** para el entorno de producción
- Opcional: **Git**

> Los **tests** usan SQLite en memoria (`:memory:`), por lo que **no** necesitan ningún servicio externo.

---

## Instalación

### 1. Clonar el repositorio e instalar dependencias

```bash
git clone https://github.com/2901-19/casita-de-romila.git
cd casita-de-romila

composer install
npm install
```

### 2. Configurar el entorno

```bash
# Crear el archivo .env a partir de la plantilla
cp .env.example .env

# Generar la clave de la aplicación
php artisan key:generate
```

Edita el archivo `.env` y configura la conexión a la base de datos.

**Para producción con PostgreSQL:**

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=casita_de_romila
DB_USERNAME=postgres
DB_PASSWORD=tu_password
```

**Para desarrollo local (opcional, SQLite):** cambia `DB_CONNECTION` a `sqlite` y crea la base:

```bash
# En .env
DB_CONNECTION=sqlite

# Crear el archivo de la base en blanco
touch database/database.sqlite
```

### 3. Crear la estructura de la base de datos y los datos base

```bash
php artisan migrate --force

# Crea el catálogo de roles/permisos y el usuario administrador (admin)
php artisan db:seed
```

`db:seed` (vía `DatabaseSeeder`) genera el usuario administrador por defecto:

| Usuario | Contraseña | Rol     |
|---------|-----------|---------|
| `admin` | `password` | Gerente |

> 🔐 **Importante:** cambia la contraseña por defecto antes de usar el sistema en producción.

### 4. Cargar datos de prueba (opcional)

Si quieres datos de demostración por módulo (usuarios, categorías, productos, clientes, ventas, inventario...):

```bash
php artisan db:seed --class=DemoSeeder
```

Usuarios demo: `maria` y `carlos` con contraseña `password`.

### 5. Vincular el almacenamiento (imágenes de productos)

Si el sistema usa imágenes de productos subidas por el usuario:

```bash
php artisan storage:link
```

### 6. Compilar los assets frontend

```bash
# Para producción
npm run build

# Para desarrollo (con recarga en caliente)
npm run dev
```

### 7. (Opcional) Limpiar sesiones antiguas

```bash
php artisan sessions:purge
```

---

## Ejecutar el servidor

```bash
php artisan serve
```

Accede a `http://localhost:8000` e inicia sesión con el usuario `admin` / `password`.

En Windows, también puedes arrancar el sistema con el lanzador incluido en `launcher/` (`romila-launcher.ps1`), que limpia sesiones y levanta el servidor.

---

## Optimización de producción (equipos de bajos recursos)

Tras desplegar los cambios, y siempre que se modifiquen rutas, config o vistas en el servidor, ejecuta los caches de Laravel (necesita permisos de escritura en `bootstrap/cache/` y `storage/`):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> Después de tocar vistas de Blade, recuerda limpiar las compiladas: `Remove-Item storage/framework/views/*.php -Force` (o `php artisan view:clear`).

**OPcache de PHP** (recomendado en servidores Windows/IIS o Nginx, no con `artisan serve`):
- Habilita `opcache.enable=1` con `opcache.memory_consumption=64`, `opcache.max_accelerated_files=10000` y `opcache.revalidate_freq=60`.
- `php artisan serve` (servidor de desarrollo) **no** usa OPcache ni mantiene keep-alive; para producción usa un servidor real (Apache/Nginx/IIS + PHP-FPM).

**Assets:** el frontend está dividido en bundles (`app.js` global; `charts.js` solo en Dashboard; `pos.js` solo en POS) y las fuentes están limitadas a `latin` con 2 pesos, para reducir la carga en equipos modestos. Los archivos tienen hash Vite para cachear con `Cache-Control` largo.

---

## Realizar los tests

La suite completa son **217 tests**:

```bash
./vendor/bin/phpunit
```

Para un test o archivo concreto:

```bash
./vendor/bin/phpunit --filter=nombre_del_test
./vendor/bin/phpunit tests/Feature/ComandaTest.php
```

---

## Módulos principales

- **Dashboard** — resumen con gráficos de ventas (Chart.js).
- **POS** — punto de venta directo (mañana).
- **Comandas** — pedidos a cocina con estados `montada → entregada → cobrada`, entrega por unidades y cobro que genera la venta (`CheckoutService`).
- **Ventas / Consulta de ventas** — historial y detalle; anulación con permiso.
- **Inventario y Producción** — control de stock.
- **Mermas** — registro de pérdidas.
- **Créditos** — ventas a crédito y gestión de clientes.
- **Tipo de cambio** — actualización y consulta de la tasa (BS/USD).
- **Reportes** — estadísticas y exportaciones.
- **Usuarios y Roles** — administración de acceso y permisos.

---

## Licencia

Proyecto privado de *Casita de Romila*. El framework Laravel base está licenciado bajo la [MIT license](https://opensource.org/licenses/MIT).
