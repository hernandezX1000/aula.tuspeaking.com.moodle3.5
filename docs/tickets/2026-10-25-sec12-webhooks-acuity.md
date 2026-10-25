# SEC-12 · Webhooks y proxy de Acuity (25-oct-2026, T-279)

**Estado:** 🟢 resuelto en el aula y en CESCE · pendiente: exigir la firma (SEC-13) y rotar la clave (SEC-4).

## Qué había
- **`acuityapi.php`** (fuera de git, con la clave de Acuity escrita): con un POST `url=…` y **sin sesión** usaba la cuenta
  de Acuity del aula: leer todas las citas (nombre, correo, teléfono), crear o cancelar. Lo llaman desde el navegador
  `acuityrecursive.js` (reservas recurrentes) y `own_PrintZoom.js` (Mis clases); por eso nadie lo vio. Igual en CESCE.
- **`cancelAcuity.php`** (fuera de git, versión de 2022): webhook «Appointment Canceled». Metía `$_POST['id']` tal cual en
  el SQL (inyección sin sesión), no comprobaba en Acuity que la cita estuviera cancelada (cualquiera podía «cancelar» la
  clase de otro) y en la copia 3.9 ni siquiera conectaba con la BD (500).
- `modifyAndCreateAcuity.php` no existe en la copia; Acuity usa `newAcuity.php` (que desde ING-9 ya trata cancelaciones).

## Qué se cambió (commit d1533c0, instalado con `t279-55`)
- Clave y usuario de Acuity a `config.php` del servidor (`$CFG->ts_acuity_userid`, `$CFG->ts_acuity_key`;
  `tuspeaking-lms/scripts/t279/t279-82`). `acuityapi.php` y `cancelAcuity.php` entran en git sin clave.
- `acuityapi.php`: incluido desde PHP, solo define `callAPI()`/`putAPI()`; por POST directo exige sesión y solo deja
  `availability/dates|times`, `availability/check-times`, crear `appointments?noEmail=true` para uno mismo (correo y campo
  de alumno de la sesión, matriculado en el curso) y leer `appointments/{id}` sin correo ni teléfono. Administradores: todo.
- `cancelAcuity.php` = `require newAcuity.php`: consulta la cita a Acuity y aplica su estado real. Una petición falsa solo
  puede igualar el aula a lo que Acuity ya tiene.
- `newAcuity.php`: clave desde `$CFG`; anota cada aviso en `moodledata/acuity_webhooks.log` con «firma ok / FIRMA MALA /
  sin firma» (`X-Acuity-Signature` = base64 del HMAC-SHA256 del cuerpo con la clave de la API).
- CESCE no usa Acuity: `acuityapi`, `cancelAcuity`, `newAcuity`, `modifyAcuity`, `modifyAndCreateAcuity` → 403 en su vhost (`t279-83`).

## Comprobado
`acuityapi.php` sin sesión → 303 (a la entrada) · `cancelAcuity`/`newAcuity` con id no numérico → «Recibido.» · Mis Clases de
una alumna y la reserva por Acuity funcionan · CESCE: los cinco ficheros 403, acceso 200.

## Pendiente
- **SEC-4:** pasar a `$CFG` la clave que aún llevan `admin/cli/i3code_download_zoomdata.php` y `empresas/acuity_zoom.php`,
  y rotarla (está en la historia de git y el 25-oct se imprimió en una terminal). Después, borrar
  `moodle39-aula/instalados-antes/` (versiones viejas con la clave).
- **SEC-13:** exigir la firma cuando el registro muestre que todas llegan firmadas.
