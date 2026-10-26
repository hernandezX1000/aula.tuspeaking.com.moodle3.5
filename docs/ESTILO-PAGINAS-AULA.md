# Estilo de las páginas propias del aula (T-279e, 26/10/2026)

Guía para que todas las páginas y bloques propios del aula (los que no son de Moodle) se vean igual que el tema Boost.
Modelo: el **Panel FUNDAE** (`blocks/fundae/block_fundae.php`, 7fff437) y **Tutorías programadas · todas las acciones**
(`tutorias_programadas_todas.php`). Solo afecta a la presentación: nunca cambia datos, filtros ni permisos.

## Reglas
1. **La letra la pone el tema.** No se fija `font-family`. Tamaños en `rem`, nunca en `px`.
2. **Un solo tamaño para el texto: `.875rem`** (14 px, el de las tablas de Boost). Más pequeño (`.8125rem`) solo para etiquetas, botones pequeños y notas.
3. **Peso 600** para cabeceras y destacados; nada de `bold`/`<b>` sueltos.
4. **Números con `font-variant-numeric: tabular-nums`** (fechas, horas, ID) para que se alineen.
5. **Campos de Bootstrap 4**: `form-control`, `custom-select`, contenedores `card card-body`.
6. **Iconos de Font Awesome 4.7** (vienen con Boost): `<i class="fa fa-…" aria-hidden="true"></i>`.
7. **Todo el CSS va dentro de una clase raíz de la página** (`.ts-fundae-panel`, `.tp-pagina`…) para no tocar el resto del aula.
8. **Enlaces que son botones llevan el color con `!important`**: el tema pinta los enlaces de turquesa y, sin eso, el texto del botón desaparece sobre su fondo.
9. Todo lo que se pinta va escapado (`s()` / `htmlspecialchars`).

## Paleta
| Uso | Color |
|---|---|
| Marca: cabecera de tabla, botón principal, borde de botones secundarios | `#008ba3` (brandcolor del tema) |
| Oscuro: texto destacado sobre blanco (contraste AA), hover de botones | `#006b7f` |
| Suave: etiquetas, contador, hover de enlaces secundarios | `#e0f7fa` |
| Hover de fila | `#f0fbfc` |
| Texto secundario | `#6c757d` |
| Bordes: marco `#dee2e6` · líneas de fila `#eef1f3` | |
| Verde (en curso): fondo `#e8f5e9` / `#f1f8f2`, texto `#1b5e20`, botón `#2e7d32` | |
| Ámbar (pronto): fondo `#fff4d6`, texto `#7a5a00` | |
| Gris (neutro): fondo `#f1f3f5`, texto `#495057` | |
| Acento de marca (logo), solo detalles | `#fcb900` |

## Componentes (clases del Panel FUNDAE; en tutorías llevan el prefijo `tp-`)
| Componente | Cómo es |
|---|---|
| Cabecera de página/bloque | título `.875rem` 600 + subtítulo gris a la izquierda; botón principal a la derecha (`display:flex; justify-content:space-between`) |
| Botón principal `ts-btn` | fondo `#008ba3`, texto blanco `!important`, radio `.375rem`, `padding:.4rem .9rem`, 600, icono a la izquierda; hover `#006b7f` |
| Botón secundario `tp-btn-sec` | fondo blanco, borde `#008ba3`, texto `#006b7f`; hover fondo `#e0f7fa` |
| Filtros | `card card-body p-2/p-3 mb-3`, flex con `gap`, etiqueta encima del campo (gris, `.8125rem`, 600) |
| Contador `ts-cuenta` | píldora `#e0f7fa` / `#006b7f`, 600 («139 acciones», «32 tutorías») |
| Marco de tabla `ts-marco` | borde `#dee2e6`, radio `.5rem`, `overflow:auto`, `max-height:70–75vh` |
| Tabla `ts-tabla` | `border-collapse:separate; border-spacing:0`; `th` fija (`position:sticky; top:0`), fondo `#008ba3`, blanco, 600; `td` `padding:.5rem .625rem`, línea `#eef1f3`; hover de fila `#f0fbfc` |
| Código destacado `ts-ag` | 600, `#006b7f` (Acción/Grupo) |
| Etiqueta `ts-badge` / estado | píldora `.8125rem` 600; turquesa (modalidad, hoy), verde (en curso), ámbar (empieza pronto), gris (programada) |
| Enlace de fila `ts-ver` | botón pequeño redondo con borde `#008ba3`; hover relleno |
| Acción de fila `tp-entrar` | botón pequeño redondo relleno `#008ba3` con icono; verde si está en curso |
| Lista larga en celda `tp-mas` | `<details>`: se ve el primer valor y «+N»; al pulsar, el resto debajo. La tabla no se rompe |
| Fila destacada | fondo verde suave + barra izquierda `inset 3px 0 0 #2e7d32` |
| Nota al pie | gris `.8125rem` |

## Cómo se comprueba antes de subir
- Vista previa local con datos ficticios (Bootstrap 4.6 + Font Awesome 4.7) y captura con Playwright.
- Flujo normal: parche en dev → CI verde → `dev:main` → `t279-55` (o `t279-106` si el fichero es nuevo) → captura en el aula con el inspector.

## Pendiente
- Las 7 páginas del bloque «Seguimiento y control · FUNDAE» (f21e20f) siguen con su estilo antiguo: pasarlas a esta guía, una a una.
- Si se aplica a más de 3 páginas, sacar el CSS común a un único fichero (`tuspeaking-ui.css`) en lugar de repetirlo en cada página.
