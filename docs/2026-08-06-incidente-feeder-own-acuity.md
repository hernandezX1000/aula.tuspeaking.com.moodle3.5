# Incidente — El feeder de `own_acuity` no recibe reservas nuevas · 2026-08-06

## Síntoma

Alumnos que reservan una clase reciben el correo de confirmación de Acuity, pero la
clase **no aparece** en "Mis clases" ni en el calendario de la plataforma, y la profesora
no queda avisada por el sistema. Caso detonante: **José Tomás Morán (E2Y Commerce,
`studentid 5334`)** — reserva con Dehlia Williams para el lun 10-ago 13:00 que no consta.

## Causa raíz (confirmada)

El pipeline de asistencia tiene tres piezas:

1. **Reserva:** el alumno reserva desde el curso → `acuityrecursive.js` → `acuityapi.php`
   crea la cita **directamente en Acuity** (de ahí el correo de confirmación).
2. **Feeder:** Acuity llama por webhook a **`newAcuity.php`**, que consulta la cita por API,
   crea los eventos de calendario (`mdl_event`) e **inserta la fila en `own_acuity`**
   (INSERT en la línea ~231).
3. **Ingesta:** el cron nocturno `admin/cli/i3code_download_zoomdata.php` **solo enriquece
   con datos de Zoom lo que ya está en `own_acuity`** (`WHERE lastmodified >= 60 días`).
   **No inserta citas nuevas** (ver TICKET #7).

El feeder (paso 2) dejó de escribir `own_acuity` alrededor del **2-3 de agosto**:

| día | filas en own_acuity |
|---|---|
| 27–31 jul | 20–46 / día |
| 2-ago | 10 · 3-ago | 1 · 4-ago | 1 · 5-ago | 1 · 6-ago | 0 |

`newAcuity.php` **existe** en el servidor (`/var/www/html/app/moodle/newAcuity.php`, sin
cambios desde feb-2022) y funciona. Lo que falla es que **Acuity ya no lo llama bien tras
la migración**: en Dinahosting la URL del webhook era `…/app/moodle/newAcuity.php`; en
Hetzner Moodle se sirve **en la raíz** (`/newAcuity.php`). El webhook de Acuity sigue
apuntando a la ruta/host viejos → **404 → nunca escribe `own_acuity`**. Coincide con el
cierre de Dinahosting (4-ago) y con el borrado de código del 1-ago.

Consecuencia: como la ingesta solo enriquece `own_acuity`, **relanzarla NO recupera estas
reservas**. Y afecta a **toda reserva nueva desde ~2-ago**, no solo a José.

## Agravante de gobernanza

**`newAcuity.php` NO estaba versionado en git** (sus dependencias `askddbb.php` y
`own_ZoomAPI.php` sí lo están). Un fichero crítico del pipeline vivía solo en disco de
producción — por eso el incidente del 1-ago pudo dejarlo sin control y nadie lo detectó.

## Plan de resolución

1. **Repuntar el webhook de Acuity** a la URL correcta de Hetzner
   (`https://aula.tuspeaking.com/newAcuity.php`) para los eventos scheduled / rescheduled /
   canceled. Verificar con un `curl` que responde 200 y escribe la fila.
2. **Reconciliar las reservas caídas desde ~2-ago:** por cada cita en Acuity que no esté en
   `own_acuity`, reejecutar `newAcuity.php` (o un job que replique su lógica) para insertarlas.
   Idempotente por `acuityid`. Recupera a José y al resto del backlog. (= TICKET #7.)
3. **Versionar `newAcuity.php` en git + GitHub** (repo moodle3.5), revisando antes que no
   lleve credenciales de Acuity/Zoom hardcodeadas (externalizar a config si las tiene).
4. **Aparte:** clase de Candice Lazenby (5-ago) atascada en "verificando" = TICKET #8
   (participantes vía CSV/UUID de Zoom + `manual_override`).

## Prevención

- Inventariar y versionar TODO fichero custom del pipeline (receptores de webhook incluidos).
- Monitor: alertar si `own_acuity` no recibe filas nuevas en 24h (nº de reservas por día = 0
  cuando históricamente hay decenas).
- Cerrar TICKET #7 (job de reconciliación) para que un webhook caído no deje clases invisibles.

## Segundo bug encontrado (mismo día): grabación FUNDAE rompe `newAcuity.php`

Al reconciliar el backlog, las 6 reservas de un cliente **FUNDAE** (Lin3s / Kiara,
`prodriguez@lin3s.com`) fallaban con **"Call to undefined function generateTokenOAuth()"**.

Causa: para cursos FUNDAE (`isfundae='t'`), `newAcuity.php` llamaba a `patchZoom()`
(en `own_ZoomAPI.php`) para **forzar la grabación en la nube** de la clase. `patchZoom()`
usa `generateTokenOAuth()`, que vivía en `own_ZoomAPIToken.php` como un token **JWT**
(app de Zoom **deprecada**); tras la migración la función correcta es `generateZoomTokenOAuth()`
(S2S OAuth vía `get_config('zoom')`), así que `generateTokenOAuth()` quedó **indefinida** →
fatal → **toda reserva FUNDAE se perdía** aunque el webhook estuviera bien.

Fix: se **elimina la llamada a `patchZoom`** de `newAcuity.php` (la grabación automática no se
usa). Se mantiene `fundaeid = 161` para marcar la fila. Con esto las reservas FUNDAE entran bien.

## Reconciliación ejecutada (6-ago)

Script `docs/ops/reconciliar_own_acuity.py`: lista citas de Acuity creadas desde el 2-ago que
no están en `own_acuity` y las mete disparando `newAcuity.php`. Ventana 6-25 ago:
**16 reservas recuperadas** (10 individuales de varios clientes + 6 de Lin3s tras el fix FUNDAE).
Pendiente barrido de sep-oct por si hay reservas de la caída para clases posteriores.

## Estado

- [x] Causa raíz confirmada (6-ago)
- [x] Webhook de Acuity repuntado a Hetzner (`newAcuity.php`, probado de punta a punta)
- [x] Segundo bug FUNDAE (`patchZoom`/`generateTokenOAuth`) corregido
- [x] Reconciliación de reservas caídas ejecutada (16, ventana 6-25 ago)
- [x] José Tomás (10-ago) visible + Dehlia avisada · Candice (5-ago) validada
- [ ] `newAcuity.php` corregido en git + GitHub (claves Acuity fuera)
- [ ] Limpiar eventos de calendario huérfanos de los intentos fallidos (Lin3s)
- [ ] Barrido sep-oct del backlog
- [ ] Versionar `own_ZoomAPIToken.php` / decidir si se retira `own_ZoomAPI.php`
