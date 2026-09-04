# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Recepcionista (usuario primario):** opera el sistema durante el servicio. Toma el pedido en el mostrador, crea la comanda, recibe a la cocina, entrega ítems de a uno y cobra al final para generar la venta real. Necesita rapidez y claridad de estado en cada paso.
- **Gerente/administrador (secundario):** revisa dashboard y reportes, y administra usuarios, productos, inventario, créditos, mermas y la tasa BCV. Trabaja desde el mismo mostrador o en oficina.

No hay pantalla de cocina: la cocina recibe la comanda de palabra; el sistema no es un KDS.

## Product Purpose

Sistema de gestión interno de un local gastronómico pequeño (Casita de Romila): permite tomar pedidos, llevarlos a cocina como comanda (con entrega por unidad y tipo de consumo por ítem), registrar la venta real solo al momento de cobrar, y administrar inventario, producción, mermas, créditos, tasas de cambio, usuarios, roles y reportes. El éxito se mide en no perder pedidos ni ventas durante el servicio y en que el inventario/caja cuadren al cierre.

## Positioning

Lo que hace distinto de una caja registradora cualquiera: la comanda y la venta son dos momentos del mismo pedido. La comanda es un snapshot editable que vive primero (montada → entregada → cobrada) y la venta real se materializa **solo al cobrar**, reutilizando el mismo motor de checkout que el POS. Entrega por ítem (`delivered_quantity`) con tipos por línea (`delivery`, `local`, `para_llevar`), numeración correlativa diaria de comanda, y venta solo cuando la comanda está cobrada en su totalidad.

## Operating Context

- Local físico con una PC de escritorio/laptop en el mostrador, manejada con mouse y teclado (no es pantalla táctil ni tablet).
- Flujo de la noche: la recepcionista arma la comanda, la cocina prepara, se entregan ítems de a uno, y al cerrar el cobro se genera la **Sale** real (descuenta stock de productos `inventariable`; los `demanda` y `produccion` no).
- Login por `username` (no email). Los módulos se bloquean por permisos ligados a roles dinámicos (Gerente/Recepcionista).
- El stock y la caja solo se mueven en el cobro/checkout, nunca al montar o entregar la comanda.

## Capabilities and Constraints

- Funcionalidad confirmada: dashboard, POS/ventas, comandas (crear/editar/entregar/cobrar/cerrar), productos/categorías/combos, inventario, producción, mermas, créditos, tasa BCV, usuarios y roles, reportes.
- Estados de comanda: `montada` → `entregada` → `cobrada`. Se puede cobrar en cualquier momento (se cobra lo pendiente); `cerrar` solo cuando está cobrada en su totalidad y registra la Venta. Los ítems cobrados quedan congelados en edición.
- Crédito nunca se mezcla con efectivo en un mismo cobro.
- Restricciones técnicas: Laravel 13 / PHP ^8.3, Bootstrap 5, Alpine.js, Vite, Chart.js, tokens oklch, fuentes offline (@fontsource: Insaniburger + Montserrat). Producción en PostgreSQL; tests en SQLite in-memory (224 tests / 683 aserciones).
- Monetización en Bs con referencia USD: el precio depende de la tasa vigente (`unit_price = sale_price * rate`), redondeos por línea.

## Brand Commitments

El look actual (paleta rosa/menta `--brand #ff8fda`, `--accent #8fffb4`, texto sobre marca `--on-brand #3d1233`; fuentes Insaniburger para títulos y Montserrat para texto) es un **borrador pulible**: el usuario confirmó que gusta y que se puede refinar/evolucionar, pero manteniendo esas bases. No hay logo fijo ni guía de identidad vinculante más allá de eso. Valores anteriores de la paleta cálida quedaron guardados en comentario al inicio del `:root` en `resources/css/app.css` como referencia a revertir.

## Evidence on Hand

- `AGENTS.md` del repo con notas operativas y de arquitectura del sistema.
- Repositorio completo: vistas Blade, `resources/css/app.css`, controllers/services, factories y tests.
- 224 tests / 683 aserciones en verde (`./vendor/bin/phpunit`).
- Datos reales de operación en la BD de producción (PostgreSQL local); usuarios demo `maria`/`carlos` (`password`), admin `admin`/`password`.
- No hay testimonios, estudios de caso ni material de marketing; no fabricar ninguno.

## Product Principles

1. **Velocidad sobre pulcritud académica:** un solo operador en mostrador debe armar, entregar y cobrar en el menor número de pasos posibles.
2. **Reflejar el ritual físico:** la secuencia comanda → entrega por unidad → venta al cobrar debe verse y sentirse igual que el trabajo real en el local.
3. **El cobro es la única fuente de verdad:** la caja y el stock se mueven solo en el checkout; el estado de cada comanda (pendiente/cobrado/entregado) debe ser inequívoco de un vistazo.
4. **Cero zonas de error:** las acciones inválidas (cerrar sin cobrar todo, mezclar crédito con efectivo) se deshabilitan o explican, nunca fallan en silencio.
5. **Cada módulo para su oficio:** recepcionista ve lo que necesita para el servicio; gerente, lo que necesita para administrar.