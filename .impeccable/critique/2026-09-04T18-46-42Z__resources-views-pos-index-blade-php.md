---
target: POS / Ventas (pos/index.blade.php), el frente de servicio
total_score: 22
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 3
target_identity: "file:C:\\Users\\Usuario\\Documents\\Projects\\CasitaDeRomila\\casita-de-romila\\resources\\views\\pos\\index.blade.php"
target_fingerprint: "sha256:ac68a84db74de5dbe48c539360c6c35b3bec0000fa543f56a02c06a4532b9da0"
target_path: "C:\\Users\\Usuario\\Documents\\Projects\\CasitaDeRomila\\casita-de-romila\\resources\\views\\pos\\index.blade.php"
timestamp: 2026-09-04T18-46-42Z
slug: resources-views-pos-index-blade-php
---
# Design Critique · POS / Ventas (`resources/views/pos/index.blade.php`)

## Design Health Score

| # | Heurística | Score | Issue clave |
|---|-----------|-------|-------------|
| 1 | Visibility of System Status | 3 | Spinners y totales bien; los errores de checkout son toasts efímeros |
| 2 | Match System / Real World | 4 | Bs, Biopago/Pago Móvil/PDV, crédito con cliente: lengua nativa del local |
| 3 | User Control and Freedom | 2 | Sin undo de "Vaciar carrito"; − elimina en qty=1; cantidad no editable |
| 4 | Consistency and Standards | 2 | Radios ocultos rompen el estándar; el POS ignora su propio sistema (grid→tabla) |
| 5 | Error Prevention | 2 | Doble gate de confirmación bueno, pero doble Enter duplica venta; borrados sin confirmar |
| 6 | Recognition Rather Than Recall | 3 | Catálogo visible, categorías, totales automáticos |
| 7 | Flexibility and Efficiency | 1 | Sin atajos, sin tipear cantidad, paginación por roundtrip |
| 8 | Aesthetic and Minimalist Design | 3 | Chrome coherente; front de cobro es tabla genérica, fila Subtotal=Total redundante |
| 9 | Error Recovery | 1 | Errores bien redactados pero en toast de 3s tras cerrar el modal |
| 10 | Help and Documentation | 1 | Una sola ayuda; Cobrar deshabilitado sin explicación |
| **Total** | | **22/40** | **Acceptable** |

## Design Specificity Verdict

**Semiantorado: el contenido es inequívocamente de Casita de Romila; la forma es intercambiable con cualquier POS genérico.**

La moneda Bs con tasas, los canales de pago venezolanos, el crédito con cliente, los combos y el recibo agradeciendo escriben el idioma del local en el momento de la cuenta. Pero el frente de venta renderiza una tabla Bootstrap estándar `table-sm`, mientras que el DESIGN.md define como Signature Component la tarjeta de producto en grid de 3 columnas (`.pos-grid`/`.product-card` en app.css:1111-1183) — **y ese componente está muerto: ningún view lo renderiza**. El precio del producto no está en rosa como promete el sistema. La composición del dinero no respira: es un listado denso, no la "densidad cómoda y aireada" del diseño.

**Deterministic scan:** detector impecable — 0 hallazgos en los 3 targets evaluados (`pos/index`, `resources/views/pos`, `resources/views/comandas`, exit 0 todos). El motor no confunde Blade/Alpine/Bootstrap. La evidencia determinista real vive en los checks shell del CSS: **15 hex hard-coded fuera de tokens, 5 rgba literales, 10 border-radius fuera de escala** en `resources/css/app.css`.

**Visual overlays:** no disponibles — no hay herramienta de browser/Playwright en esta sesión (fallback registrado). No requiere action.

## Overall Impression

El sistema cree en sí mismo en el contenido y en el chequeo (CheckoutService como fuente de verdad) pero se queda corto en la superficie que cobra el dinero: una tabla genérica cuando su propio diseño pide tarjetas táctiles, y una falla de confianza grave —un doble Enter puede duplicar la venta. La mayor oportunidad: que el POS finalmente *sea* El Mostrador de Confianza que el target pretende.

## What's Working

1. **Fidelidad de dominio impecable en el contenido.** Bs, canales de pago reales, crédito con cliente, recibo agradeciendo — no se copia de un template.
2. **Doble gate de cobro ejecutado con calma.** "Cobrar" abre recibo de verificación antes de "Confirmar venta"; el botón se deshabilita solo con carrito vacío o crédito sin cliente.
3. **Layout de Operate bien resuelto.** `1fr 360px` con altura fija al viewport y scroll interno; categorías y estados como píldoras; totales auto-calculados.

## Priority Issues

**[P0] Doble envío en "Confirmar venta" puede duplicar la venta y el stock.**
- **Why:** `processCheckout` cierra el modal y dispara fetch sin flag in-flight (pos.js:160-172); doble Enter/Doble clic = dos POSTs con el mismo cart; caja descuadrada al cierre.
- **Fix:** flag `processing` que deshabilite Confirmar/Cancelar durante el request y descarte re-submits.
- **Command:** `$impeccable harden`

**[P1] Los métodos de pago son inaccesibles por teclado.**
- **Why:** `.payment-option input[type=radio]{display:none}` (app.css:1482-1484) saca los radios del tab order — cobrar es un acto solo-mouse.
- **Fix:** `visually-hidden` real + `:focus-visible` en el chip, o botones `role=radio` con flechas.
- **Command:** `$impeccable harden`

**[P1] La pieza firma del diseño no existe en la pantalla que hace dinero.**
- **Why:** tabla `table-sm` con botones `+` de ~31px en vez del grid `.pos-grid`/`.product-card`; el precio no aparece en rosa; CSS muerto y dos `@media 575px` contradictorios (app.css:1197-1201).
- **Fix:** restaurar tarjetas en grid de 3 columnas (thumb rosa-soft, nombre clamp 2 líneas, precio rosa 700, click en toda la tarjeta, hover-lift).
- **Command:** `$impeccable layout`

**[P1] El error de checkout destruye su propio contexto.**
- **Why:** el modal de recibo se oculta antes de la respuesta (pos.js:161-162) y el fallo de stock/límite llega como toast de 3s; el operador pierde el recibo justo cuando la cuenta falla.
- **Fix:** mantener/reabrir el modal mostrando la `CheckoutException` dentro del recibo con `aria-live`; toast solo en éxitos.
- **Command:** `$impeccable clarify`

**[P2] El botón − elimina el ítem en cantidad 1 sin aviso ni undo.**
- **Why:** un clic perdido saca una línea del ticket en silencio; "cero zonas de error" promete explicar.
- **Fix:** separar papelera (danger) de "reducir"; deshabilitar − en 1 o exigir confirmación; undo toast.
- **Command:** `$impeccable clarify`

## Persona Red Flags

**Alex (power user):** el `+.` de 31px en fila de tabla es target chico e invisible para un vendedor nocturno; no puede tipear cantidad (input readonly); sin atajos ni doble-clic; pager de red (pageSize=9) añade latencia por página; 5 chips de pago en 0.78rem dentro de 360px es puntería fina.

**Sam (accesibilidad):** bloqueado en el método de pago (radios `display:none`); la lista de productos no anuncia cambios (cada `+` suena "Agregar, botón" sin nombre de producto); el selector de cliente aparece/desaparece sin aviso; "Vaciar carrito" es icon-only sin confirmar; el error de checkout no se anuncia como alerta de lector (modal cerrado + toast).

## Minor Observations

- Subtotal = Total en dos filas (blade 161-170): iguales; simplificar o desglosar real.
- Reloj estático del servidor (blade 7-10) no corre en turnos largos.
- "Vaciar carrito" sin confirmación resetea el método a efectivo sorpresivamente.
- `aria-label="Cantidad"` en input readonly se anuncia editable.
- "PDV" es críptico para quien cobra por primera vez.
- Sin visibilidad de stock en el POS: el operador descubre "stock insuficiente" al confirmar, no al mirar el catálogo.
- Drift de tokens (evidencia B): 15 hex hard-coded (L316,359,443,587,588,719,729,763,1581,1702,1744,1786,1848,2218), 5 rgba literales (L105,452,553,804,1961), 10 border-radius fuera de escala (0.7,1.25,0.4,0.3,0.55,1,0.8rem) — candidatos para `$impeccable polish`.
- CSS muerto/contradictorio: `.pos-grid`, `.product-card*`, `.pos-empty`, `.checkout-total/*`, y dos `@media (max-width:575px)` opuestos.
- Sombra siempre presente en cards (app.css:231) bordea la Regla del Reposo Plano.

## Questions to Consider

- Si la tarjeta de producto es la pieza firma, ¿por qué la pantalla que cobra renderiza una tabla genérica?
- El operador real que solo usa Efectivo y PDV: ¿le sirve la matriz de 5 métodos, o dos grandes + "Otro"?
- ¿Cuánta confianza le queda a "Confirmar venta" si el modal se cierra antes de oír "venta registrada"?
