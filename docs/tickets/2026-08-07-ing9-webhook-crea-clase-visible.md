# ING-9 — El webhook debe hacer visible la clase al reservar, no el cron del día siguiente

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Prioridad:** Alta · **Estado:** 🔴 abierto
**Fecha:** 2026-08-07 · **Fichero:** `newAcuity.php` (y su gemelo `modifyAndCreateAcuity.php`)

---

## 1. El problema

Cuando un alumno reserva, el webhook de Acuity:

- ✅ crea los eventos de calendario (con la URL de Zoom dentro),
- ✅ crea la fila en `own_acuity`,
- ❌ **NO crea la fila en `mdl_i3code_acuityZoom`**.

Y el panel "Mis clases" (`misclases.php`) **lee de `mdl_i3code_acuityZoom`**. Resultado: el
alumno reserva y **su clase no aparece en el panel** hasta que pasa el cron de la ingesta…
que corre **una sola vez al día, a las 04:05 UTC**.

### Evidencia (07-ago-2026)

Tres alumnas reservaron por la tarde. A las 21:00 seguían sin clase visible:

| Reserva | Hora (UTC) | Alumna | Clase |
|---|---|---|---|
| 114436 | 11:04 | Sonia Funes (Hyatt) | mar 11-ago 09:30 |
| 114437 | 12:24 | Nieves Sancho (E2Y) | lun 10-ago 13:30 |
| 114438 | 13:06 | María Luján (CENIEH) | lun 10-ago 08:00 |

Las tres con `studenteventid` correcto (evento de calendario creado) y **sin fila en la
ingesta**. Pueden ir a clase —tienen el enlace en su calendario y en el correo de Acuity—
pero en la plataforma su clase no existe.

### Consecuencias en cadena

Este diseño explica también:

- Las clases que **nacen en estado 0** ("Sin datos", sin botón Conectar): las inserta la
  ingesta, y `zoom_clasecompletada` es `tinyint NOT NULL DEFAULT 0`. → 20 clases futuras.
- Que reservar por la tarde signifique **no ver tu clase hasta el día siguiente**.
- Parte de los correos a soporte del tipo "no me aparece mi clase".

---

## 2. Lo que ya tiene el webhook (no hay que buscar nada)

`newAcuity.php`, líneas 87-89:

```php
$locationURL = explode(" ", $appointment['location'])[1];   // URL completa, con ?pwd=
$locationID  = explode(" ", $appointment['location'])[4];   // Meeting ID
$locationID  = str_replace("-","", $locationID);
```

Y en el mismo ámbito: `$acuityID`, `$courseID`, `$studentID`, `$teacherID`,
`$appointment['datetime' | 'type' | 'duration' | 'firstName' | 'lastName' | 'email']`.

**Están todos los datos.** Solo falta escribirlos donde el panel los busca.

---

## 3. El fix

Añadir, justo después del `INSERT INTO own_acuity` (línea ~240), un insert equivalente en
`mdl_i3code_acuityZoom`:

```php
// ING-9: la clase debe ser visible en "Mis clases" DESDE la reserva, no al día siguiente.
// misclases.php lee de esta tabla y exige zoom_clasecompletada = 3 para mostrar "Conectar".
$sqlIZ = "INSERT INTO mdl_i3code_acuityZoom
    (acuityid, courseid, studentid, teacherid,
     acuity_firstname, acuity_lastname, acuity_email,
     acuity_datetime, acuity_starttime, acuity_endtime, acuity_duration,
     acuity_type, acuity_location, zoom_meetingid,
     zoom_clasecompletada, acuity_canceled, acuity_rescheduled)
 VALUES ($acuityID, $courseID, $studentID, $teacherID,
     '{$appointment['firstName']}', '{$appointment['lastName']}', '{$appointment['email']}',
     '{$appointment['datetime']}', '{$appointment['time']}', '{$appointment['endTime']}',
     {$appointment['duration']},
     '{$appointment['type']}', '{$appointment['location']}', "
     . ($locationID ?: 'NULL') . ",
     3, 0, 0)
 ON DUPLICATE KEY UPDATE
     acuity_datetime = VALUES(acuity_datetime),
     acuity_location = VALUES(acuity_location),
     zoom_meetingid  = VALUES(zoom_meetingid)";
```

⚠️ **Escapar los valores.** El fichero usa concatenación directa; seguir el patrón existente
pero pasar los textos por `$conn->quote()` o parámetros. `acuity_type` y `acuity_location`
vienen de fuera.

⚠️ **`zoom_clasecompletada = 3`** ("Agendada") es lo que hace aparecer el botón Conectar.
Con el 0 por defecto sale "Sin datos".

⚠️ **No tocar** `zoom_*` de asistencia (`zoom_starttime`, `zoom_duration`, `zoom_participants`).
Eso sigue siendo trabajo de la ingesta.

---

## 4. Reparto de responsabilidades al que se llega

| | Cuándo | Qué escribe |
|---|---|---|
| **Webhook** | al reservar, en tiempo real | la clase **existe y se ve**: calendario + `own_acuity` + `mdl_i3code_acuityZoom` en estado 3 |
| **Ingesta** | cron, después de la clase | **solo asistencia**: participantes de Zoom → estado 1 o 2 |

Hoy la ingesta hace las dos cosas y por eso todo depende de un cron diario.

---

## 4.bis · LO GORDO: `newAcuity.php` solo sabe CREAR

El webhook catch-all de Acuity es **"Scheduled or Updated"**: recibe altas, reprogramaciones
y cambios. Pero el fichero al que apunta desde el 6-ago **solo contempla el alta**.

Análisis completo del fichero (07-ago):

| Rama del ciclo | Qué hace hoy | Qué debería hacer |
|---|---|---|
| **Alta** | ✅ INSERT en `mdl_event` (×3), `own_acuity` y `mdl_i3code_acuityZoom` | correcto |
| **Reprogramación** | ❌ **vuelve a INSERTAR**: eventos de calendario duplicados y fila duplicada en `own_acuity` | detectar que el `acuityid` ya existe → **UPDATE** de la hora en los eventos y en las dos tablas, `acuity_rescheduled = 1`, guardar `acuity_original_datetime`, `modifiedtimes + 1` |
| **Cancelación** | ❌ **no mira `$appointment['canceled']`**, que Acuity sí envía | `acuity_canceled = 1`, `own_acuity.iscancelled = 't'`, y **borrar los eventos de calendario** |

### Consecuencias ya observadas

- **30-jul, José Tomás Morán:** al reservar Candice en el hueco de una clase cancelada, el
  INSERT chocó con la clave única `idx_unique_clase` (error 1062) y **la clase nueva no se
  registró**. Hubo que arreglarlo a mano. Es este agujero.
- Eventos de calendario duplicados cuando alguien cambia la hora de su clase.
- Clases canceladas en Acuity que siguen vivas en el aula hasta que pasa la ingesta.

### Lo que hacía el fichero perdido

`modifyAndCreateAcuity.php` — el nombre lo dice: **modify AND create**. Cubría las tres
ramas. Se perdió en el borrado del 1-ago-2026 y **no está en ningún backup** (comprobados los
9 `code_moodle_*.tar.gz` del 1 al 7 de agosto: cero coincidencias) ni en git (estaba excluido
por llevar la API key de Acuity).

**Hay que reescribirlo.** No es un parche: es reponer el gestor del ciclo de reservas.

### Referencia para reescribirlo

La huella de la BD da el resultado esperado exacto. Última fila creada por el fichero viejo
(6-ago 09:48, 40 min antes de la rotura), `mdl_i3code_acuityZoom` id 111311:

```
acuity_firstname/lastname/phone/email : José Tomás · Morán Gálvez · '' · jose.moran@…
acuity_datetime  : 2026-08-10T13:00:00+0200      acuity_starttime : 13:00
acuity_endtime   : 13:30                          acuity_duration  : 30
acuity_type      : 2026  English Class (30 min)
acuity_location  : URL: https://us02web.zoom.us/j/82618542097?pwd=… \nMeeting ID: … \nPassword: …
zoom_meetingid   : 82618542097                    zoom_url         : NULL
zoom_* (asistencia) : todos NULL                  zoom_clasecompletada : 3
acuity_canceled  : 0                              acuity_rescheduled   : 0
manual_*         : 0 / NULL
```

Y en `own_acuity` (id 114400): `studenteventid` 637315 · `teachereventid` 637316 ·
`heventid` 637317 · `ceventid` 637318 · `geventid` 637319 · `modifiedtimes` 0 ·
`iscancelled` 'f' · `isteached` 'f'.

---

## 5. Comprobar también

- **`modifyAndCreateAcuity.php`** (fuera del repo, lleva la API key de Acuity) es el que
  atiende el webhook catch-all "Scheduled or Updated". Necesita **el mismo cambio**, o las
  reprogramaciones seguirán llegando tarde.
- **`cancelAcuity.php`**: al cancelar debería poner `acuity_canceled = 1` en la misma tabla.

---

## 6. Verificación

1. Hacer una reserva de prueba en Acuity.
2. **Sin esperar al cron**, abrir "Mis clases" del alumno: debe verse en naranja como
   **Agendada** y con el botón **Conectar**.
3. Comprobar que se creó una sola fila:
   ```sql
   SELECT id, acuityid, acuity_datetime, zoom_clasecompletada, zoom_meetingid,
          LEFT(acuity_location, 40)
   FROM mdl_i3code_acuityZoom WHERE acuityid = <ACUITYID_PRUEBA>;
   ```
4. Al día siguiente, comprobar que la ingesta **no ha duplicado** la fila (de ahí el
   `ON DUPLICATE KEY UPDATE`).
5. **Borrar la reserva de prueba** — en Acuity y en la base de datos. El 6-ago quedó una
   suelta (28-dic, José Tomás Morán) que apareció en el panel de un alumno real.

---

## 7. Mientras no esté

Las clases que nacen en estado 0 con enlace se pueden rescatar a mano:

```sql
UPDATE mdl_i3code_acuityZoom
SET zoom_clasecompletada = 3
WHERE zoom_clasecompletada = 0 AND acuity_canceled = 0
  AND zoom_meetingid IS NOT NULL
  AND acuity_datetime > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 2 HOUR), '%Y-%m-%dT%H:%i:%s');
```

No arregla la causa: al día siguiente hay más.
