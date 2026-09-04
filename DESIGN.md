---
name: Casita de Romila
description: Sistema de gestión del local — POS, comandas y administración
colors:
  rosa-chicle: "#ff8fda"
  rosa-chicle-hover: "#e07ec0"
  rosa-chicle-active: "#c770aa"
  rosa-chicle-soft: "#ffddf4"
  on-brand: "#3d1233"
  menta-lima: "#8fffb4"
  menta-lima-soft: "#e3ffec"
  moka: "#2a232b"
  almendra: "#faf7fa"
  blanco-nube: "oklch(1 0 0)"
  perla: "#ece4ec"
  verde-exito: "#1da55a"
typography:
  display:
    fontFamily: "Insaniburger, Montserrat, sans-serif"
    fontWeight: 400
  body:
    fontFamily: "Montserrat, system-ui, -apple-system, Segoe UI, Roboto, sans-serif"
    fontWeight: 400
rounded:
  sm: "0.6rem"
  md: "0.75rem"
  lg: "0.9rem"
  pill: "999px"
spacing:
  xs: "0.35rem"
  sm: "0.75rem"
  md: "1rem"
  lg: "1.25rem"
components:
  button-primary:
    backgroundColor: "{colors.rosa-chicle}"
    textColor: "{colors.on-brand}"
    rounded: "{rounded.sm}"
    padding: "0.55rem 1rem"
  button-primary-hover:
    backgroundColor: "{colors.rosa-chicle-hover}"
  button-primary-active:
    backgroundColor: "{colors.rosa-chicle-active}"
  badge-soft-success:
    backgroundColor: "{colors.menta-lima-soft}"
    textColor: "{colors.verde-exito}"
    rounded: "{rounded.pill}"
    padding: "0.25rem 0.6rem"
  nav-link-active:
    backgroundColor: "{colors.rosa-chicle}"
    textColor: "{colors.on-brand}"
    rounded: "{rounded.sm}"
---

# Design System: Casita de Romila

## Overview

**Creative North Star: "El Mostrador de Confianza"**

Un mostrador cálido y amable donde ningún pedido se pierde. El sistema se comporta como la recepcionista ideal del local: agradable de tratar, impecable en la cuenta y absolutamente fiable con los pedidos. La personalidad es dulce pero decidida: una paleta de golosinas (rosa chicle y menta lima) asentada sobre neutros suaves (moka, almendra y perla), tipografía de letrero escrito a mano que da voz amistosa a los títulos, y una operación impecable debajo.

Es un sistema de modo **Operate**: la velocidad del servicio y la claridad del estado ganan sobre la expresión. El rosa es el acento operativo — acción, selección, estado activo; la menta comunica éxito y atención; los neutros cargan el contenido. La densidad es cómoda y aireada: tarjetas de producto táctiles en el POS, tablas espaciadas para escaneo en administración.

La geometría es amiga: casi nada es anguloso. Inputs, botones y navegación comparten una curva suave (0.6rem); las tarjetas de producto son un poco más redondas (0.75rem); el contenedor grande del carrito (0.9rem); los estados y categorías, píldoras completas (999px). Las superficies descansan planas; la profundidad responde al estado: una sombra ligera al hover, una sombra profunda solo sobre los modales.

**Key Characteristics:**
- Dúo de golosinas: rosa chicle para acción, menta lima para éxito — siempre sobre neutros que cargan el contenido
- Texto sobre rosa en moka profundo (`--on-brand`), nunca blanco
- Dos voces tipográficas: Insaniburger (letrero escrito a mano) para marca y títulos; Montserrat para el cuerpo
- Curvas suaves por todas partes; nada menor a 0.5rem en controles interactivos
- Plano en reposo, luz al hover, sombra profunda solo en modales
- Estados siempre visibles de un vistazo: badges píldora con tinta y fondo suave del mismo color

## Colors

Dulceso pero funcional: rosa y menta son los dos acentos principales, los neutros cargan la interfaz y los colores de estado derivan de la misma lógica de tintas-suaves.

### Primary
- **Rosa Chicle** (#ff8fda): el acento operativo. Botones primarios (`btn-brand`), navegación activa, logo-mark, icono de tarjeta de producto, precios en el POS, número de comanda. Todo lo que significa "acción o estado vivo".
- **Rosa Chicle Hover** (#e07ec0): versiones hover/active de botones primarios y enlaces activos.
- **Rosa Chicle Soft** (#ffddf4): tinta suave — avatares, iconos de producto, badges `info`.

### Secondary
- **Menta Lima** (#8fffb4): el acento de éxito. Complementa al rosa en estados "entregado/cobrado" y en la arquitectura de tokens (`--accent`).
- **Menta Lima Soft** (#e3ffec): fondo de los badges de éxito y de los indicadores de vérdex.

### Neutral
- **Moka** (#2a232b): texto principal (--fg). Sobre rosa se usa la variante `--on-brand` (#3d1233).
- **Almendra** (#faf7fa): fondo de página (--bg).
- **Blanco Nube** (oklch(1 0 0)): superficie de tarjetas, topbar, modal (--surface).
- **Perla** (#ece4ec): bordes y divisores.
- **Verde Éxito** (#1da55a): éxito semántico (badge-soft.success), pareado con background menta-lima-soft.

### Named Rules
**La Regla del Contraste Dulce.** Texto sobre rosa chicle es siempre moka profundo (#3d1233), nunca blanco. La legibilidad dulce es la firma.
**La Regla del Rosa Contado.** El rosa es el acento operativo y debe parecer escaso: acciones y estados activos, no paisajes completos. Cuando una pantalla siente mucho rosa, está mal resuelta.

## Typography

**Display Font:** Insaniburger (con Montserrat de respaldo)
**Body Font:** Montserrat (400 y 700), con fallback system-ui, Segoe UI, Roboto

**Character:** un dúo de voz doble juguetona con fundamento. Insaniburger, con su trazo informal de letrero de golosina, da personalidad a marca, títulos de página y cabeceras de tarjeta; Montserrat, sobria y moderna, carga toda la lectura operativa. La convivencia dice "casita amable" sin sacrificar legibilidad.

### Hierarchy
- **Display** (400, topbar-title 1.55rem / brand 1.15rem / modal-title 1.25rem, line-height 1.1): Insaniburger solo en marca, títulos de sección y encabezados de tarjeta. Nunca en párrafos.
- **Body** (400/600, 0.9rem, line-height 1.3–1.5): Montserrat. Texto de controles, celdas, descripciones.
- **Label** (600, 0.88rem, case normal): etiquetas de formulario (`.form-label`), con margen inferior pequeño.
- **Table Header** (600, 0.74rem, uppercase, tracking 0.04em): único uso de mayúsculas del sistema, reservado a cabeceras de tabla.

### Named Rules
**La Regla del Letrero Solo.** Insaniburger nunca se usa para párrafos, tablas o inputs; su voz es para lo que se mira, no para lo que se lee.

## Layout

**App shell:** columna lateral fija de 250px (sticky, 100vh) con la navegación, y columna principal flexible con topbar sticky y contenido. En móvil la lateral se repliega a un offcanvas.

**POS:** grid de dos columnas `1fr 360px` — catálogo de productos a la izquierda (grid de 3 columnas que baja a 2 y a 1 en breakpoints menores) y carrito fijo a la derecha. La altura del layout se ajusta al viewport (`calc(100vh - 7rem)`) con scroll interno de la lista de ítems, para que el formulario nunca se estire.

**Comanda show:** ancho completo del main (sin constreñir).

**Ritmo:** espaciado base de 0.75rem entre celdas del grid, rellenos de tarjeta de 1rem; margen inferior de sección ~1.1rem. Densidad cómoda, no compacta: el operador escanea filas y tarjetas grandes más rápido que texto denso.

## Elevation & Depth

El sistema es mayormente plano: las superficies se separan con bordes Perla y tonos de fondo, y la sombra aparece solo como respuesta al estado. No hay elevación estructural permanente.

- **Card Ambient** (`0 1px 2px oklch(0.3 0.01 40 / 0.03), 0 6px 18px oklch(0.3 0.01 40 / 0.05)`): tarjetas genéricas en reposo — suficiente paraizar, no para escalar.
- **Hover Lift** (`0 6px 20px oklch(0.15 0.1 25 / 0.14)`): tarjetas de producto al hover/focus; es la elevación interactiva del sistema.
- **Logo Glow** (`0 4px 10px rgba(224, 126, 192, 0.35)`): sombra rosa bajo el logo-mark; el único halo de marca.
- **Modal Deep** (`0 18px 50px oklch(0.25 0.01 40 / 0.25)` + backdrop con blur 6px): la profundidad máxima, reservada al modal.

### Named Rules
**La Regla del Reposo Plano.** Las superficies descansan planas; la sombra es la respuesta al estado (hover, focus, modal). Una tarjeta con sombra sin interacción relación es ruido.

## Shapes

Lenguaje de formas amistoso: casi todo es redondeado, casi nada es anguloso. Los controles comparten 0.6rem; las tarjetas de producto suben a 0.75rem y el contenedor grande del carrito a 0.9rem; los estados, categorías y precios son píldoras (999px); los avatares y logo-referencias son círculos. La silueta recurrente es la píldora y el óvalo, no el cuadrado.

Los inputs crecen de 0.55rem de relleno; el foco de los campos se distingue por borde y anillo rosa (`--brand-ring`), sin sombra de foco.

## Components

### Buttons
- **Shape:** curva suave (0.6rem), peso 600.
- **Primario (`btn-brand`):** fondo Rosa Chicle, texto Moka profundo (#3d1233), transición suave a hover (Rosa Chicle Hover) y active (Rosa Chicle Active). Incluye el contraste dulce y el peso seminegrita; es el botón de acción del servicio.
- **Outline (`btn-outline-brand`):** borde y texto Rosa Chicle; al hover se rellena de Rosa Chicle Soft. Para acciones secundarias alrededor de la acción primaria.
- **Icono (`.btn-icon`):** cuadrado 40px en Blanco Nube con borde Perla, fondo se aclara a Almendra al hover; para acciones de fila compactas.

### Chips
- **Style:** píldora (999px), relleno compacto (0.25rem 0.6rem), texto 0.74rem peso 600, tinta y fondo suave del mismo color de estado: success (verde sobre menta-lima-soft), warning, danger, muted, info (rosa sobre rosa-chicle-soft). Los filtros de categoría son píldoras con `.active` en Rosa Chicle.

### Cards / Containers
- **Corner Style:** suave — 0.6rem genéricas, 0.75rem tarjetas de producto, 0.9rem contenedor del carrito.
- **Background:** Blanco Nube (--surface) sobre fondo Almendra.
- **Shadow Strategy:** plano en reposo (ambient), luz en hover (ver Elevation).
- **Border:** 1px Perla.
- **Internal Padding:** 1rem.

### Inputs / Fields
- **Style:** relleno 0.55rem 0.75rem, fondo Blanco Nube, borde Perla, radio 0.6rem, texto Moka 0.9rem; placeholder en gris suave.
- **Focus:** borde neutro cálido y **sin** sombra de foco (box-shadow none); el anillo rosa (--brand-ring) se reserva para radios/checks y elementos seleccionados.

### Navigation
- **Style:** enlace blando (0.6rem) con icono y texto Moka neutro; hover invertido a fondo Almendra con texto Moka; **active** en Rosa Chicle con texto #3d1233. La navegación activa es otro uso del rosa operativo.

### Signature Component: Product Card (POS)
- **Corner Style:** 0.75rem; **Background:** Blanco Nube; **Border:** Perla; **State:** sin sombra en reposo, borde rosa + sombra hover al pasar/focus; contenido centrado: icono en caja de Rosa Chicle Soft, nombre de 2 líneas clamp con corte de palabra, precio en Rosa Chicle (700). Es la tarjeta táctil que define el frente de venta.

## Do's and Don'ts

### Do:
- **Do** usar `--on-brand` (#3d1233) como texto siempre que el fondo sea Rosa Chicle, y nunca blanco.
- **Do** reservar rados menores (0.6rem) para controles y subir a 0.75/0.9 sólo donde el ojo debe agrupar (tarjeta de producto, carrito).
- **Do** expresar estados con píldoras de tinta + fondo suave del mismo color (badge-soft), de un vistazo.
- **Do** dejar las superficies planas en reposo y responder con sombra solo en hover/focus/modal.
- **Do** usar Insaniburger solo para marca y títulos; Montserrat para todo lo legible.
- **Do** recortar la altura del contenedor de items al viewport con scroll interno para que los formularios altos no estiren la página.

### Don't:
- **Don't** usar texto blanco sobre rosa chicle — rompe la legibilidad dulce.
- **Don't** inundar una pantalla de rosa; el rosa es el acento operativo y su escasez es el punto.
- **Don't** introducir esquinas angulosas (<0.5rem) en componentes interactivos.
- **Don't** dar sombra permanente a las tarjetas; la elevación es respuesta al estado.
- **Don't** usar mayúsculas fuera de las cabeceras de tabla (0.74rem uppercase) — el resto del texto va en caja normal.