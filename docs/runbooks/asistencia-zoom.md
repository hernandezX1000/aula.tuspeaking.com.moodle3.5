# Runbook — Asistencia y Zoom

Como consultar asistencia, estados y duracion en Zoom, y los problemas tipicos de cierre.
Fuente unica: este fichero (repo). Llega al server con cada deploy.

Tablas clave:
- **`mdl_i3code_acuityZoom`** — 1 fila por clase (reserva Acuity <-> reunion Zoom).
- **`mdl_i3code_acuityZoom_participants`** — participantes por reunion (join/leave, duracion).
- **`mdl_i3code_acuityZoom_informe`** — datos del panel.

> **Conexion Hetzner (produccion desde ago-2026):**
> ```bash
> ssh coreadmin@46.225.232.27
> docker exec -it moodle35-db mysql -u moodle35 -p'<pass>' aulatuspeaking35
> ```
> O en una linea (para scripts):
> ```bash
> docker exec moodle35-db mysql -u moodle35 -p'<pass>' aulatuspeaking35 -e "SELECT ..."
> ```
> La contrasena esta en `/home/coreadmin/tuspeaking-lms/.env` o en el `config.php` del contenedor.

---

## Columnas clave de `mdl_i3code_acuityZoom`

| Columna | Tipo | Significado |
|---------|------|-------------|
| `id` | INT | PK |
| `studentid` | INT | -> `mdl_user.id` |
| `acuity_datetime` | VARCHAR/DATETIME | Fecha/hora de la clase segun Acuity (**columna real**, NO `acuity_starttime`) |
| `zoom_clasecompletada` | INT | Estado (ver tabla abajo) |
| `zoom_meetingid` | VARCHAR | ID reunion Zoom (NULL = sin reunion vinculada) |
| `acuity_canceled` | INT | 1 = cancelada en Acuity |
| `acuity_rescheduled` | INT | 1 = reprogramada en Acuity (slot original no ocurrio) |
| `manual_override` | INT | 1 = correccion manual activa (el sync NO sobreescribe) |
| `manual_motivo` | VARCHAR | Texto del motivo de la correccion |
| `manual_fecha` | DATETIME | Cuando se hizo la correccion manual |

> NOTA: `acuity_datetime` = ISO completo con fecha+hora+TZ (usar para filtrar por fecha).
> `acuity_starttime` = solo la hora HH:MM (campo auxiliar, no usar para filtros de fecha).
> WARNING: El JOIN alumno<->clase es `mdl_user.id = mdl_i3code_acuityZoom.studentid`, NO `.userid`.

---

## Estados de asistencia (`zoom_clasecompletada`)

| Valor | Significado | Lo que ve el alumno |
|-------|-------------|---------------------|
| `0` | Sin datos (ingesta no procesada) | "Verificando asistencia" |
| `1` | **Asistio** | Marca verde |
| `2` | **Ausencia** (no-show real) | Ausencia |
| `3` | Programada / pendiente de verificar | "Verificando asistencia" |

> "Cancelada" no es un estado de `zoom_clasecompletada`: se refleja en `acuity_canceled = 1`.
> La UI muestra "Verificando asistencia" para estado 0 y 3 cuando la fecha ya paso.

---

## Consultar la asistencia de un alumno

Por email:

```sql
SELECT
  az.id,
  az.acuity_datetime                AS inicio,
  az.zoom_clasecompletada           AS estado,
  az.acuity_canceled                AS cancelada,
  az.acuity_rescheduled             AS reprogramada,
  az.zoom_meetingid,
  az.manual_override,
  az.manual_motivo
FROM mdl_i3code_acuityZoom az
JOIN mdl_user u ON u.id = az.studentid
WHERE u.email = 'alumno@empresa.com'
ORDER BY az.acuity_datetime DESC
LIMIT 30;
```

Resumen por estado (para cierre/FUNDAE):

```sql
SELECT az.zoom_clasecompletada AS estado, COUNT(*) AS n
FROM mdl_i3code_acuityZoom az
JOIN mdl_user u ON u.id = az.studentid
WHERE u.email = 'alumno@empresa.com'
GROUP BY az.zoom_clasecompletada;
```

Clases atascadas en "verificando" de un alumno:

```sql
SELECT az.id, az.acuity_datetime, az.zoom_clasecompletada, az.zoom_meetingid,
       az.acuity_canceled, az.acuity_rescheduled, az.manual_override
FROM mdl_i3code_acuityZoom az
JOIN mdl_user u ON u.id = az.studentid
WHERE u.email = 'alumno@empresa.com'
  AND az.zoom_clasecompletada IN (0,3)
  AND az.acuity_canceled = 0
  AND az.acuity_datetime < NOW()
ORDER BY az.acuity_datetime DESC;
```

---

## Correccion manual de una clase (override)

Usar cuando la ingesta no puede resolver sola (clase reprogramada, fantasma, cancelada tarde).
**`manual_override=1` es obligatorio**; sin el, el sync de 15 min lo revierte.

```sql
-- Marcar como asistida
UPDATE mdl_i3code_acuityZoom
SET zoom_clasecompletada = 1,
    manual_override      = 1,
    manual_motivo        = 'Asistencia confirmada manualmente',
    manual_fecha         = NOW()
WHERE id = <ID_FILA>;

-- Marcar como cancelada / slot reprogramado no realizado
UPDATE mdl_i3code_acuityZoom
SET acuity_canceled      = 1,
    manual_override      = 1,
    manual_motivo        = 'Clase reprogramada - slot original no realizado',
    manual_fecha         = NOW()
WHERE id = <ID_FILA>;

-- Marcar como ausencia (no-show)
UPDATE mdl_i3code_acuityZoom
SET zoom_clasecompletada = 2,
    manual_override      = 1,
    manual_motivo        = 'No-show confirmado',
    manual_fecha         = NOW()
WHERE id = <ID_FILA>;
```

---

## Todas las clases en "verificando" (global)

```sql
SELECT az.id, u.email, az.acuity_datetime, az.zoom_clasecompletada,
       az.zoom_meetingid, az.acuity_canceled, az.acuity_rescheduled
FROM mdl_i3code_acuityZoom az
JOIN mdl_user u ON u.id = az.studentid
WHERE az.zoom_clasecompletada IN (0,3)
  AND az.acuity_canceled = 0
  AND az.acuity_datetime < NOW()
ORDER BY az.acuity_datetime DESC
LIMIT 50;
```

---

## Problemas tipicos de cierre

1. **Clase reprogramada queda en "verificando".** Cuando Acuity reprograma un slot,
   el slot original (`acuity_rescheduled=1`) no tiene reunion Zoom -> la ingesta no lo cierra.
   Fix: `acuity_canceled=1` + `manual_override=1` en el slot original.

2. **Clase fantasma (NULL meetingid).** Clase en el pasado, `zoom_meetingid` NULL.
   Nunca hubo reunion Zoom -> archivar con `acuity_canceled=1` + `manual_override=1`.

3. **Sync revierte correcciones manuales.** El sync corre cada 15 min. Si no pones
   `manual_override=1`, lo revierte. Siempre incluirlo.

4. **Reuniones sin participantes.** Zoom a veces no devuelve datos de meetings pasados
   (limite de API). En ese caso la verdad esta en el CSV export de Zoom, no en la API.

5. **Sesiones dobles / extendidas.** Cuando dos slots se dan como una sola reunion larga,
   toda la reunion cae en el primer slot (estado 1) y el segundo queda en estado 3.
   Al cerrar, contar por duracion real, no por numero de clases.

6. **`mdl_i3code_acuityZoom_participants` vacia para clase de hoy.** La ingesta corre a
   las 04:05. Si la clase fue hoy, los datos de participantes aun no estan en BD. Para
   investigar en caliente, ir directamente al portal Zoom:
   `https://zoom.us/account/my/report` -> Reports -> Usage -> buscar por Meeting ID.
   Ahi se ven join_time/leave_time exactos de cada participante.

---

## Investigar desfase de tiempo (alumno y profesor no se solaparon)

Patron tipico: alumno dice "el profesor no aparecio" / profesor dice "el alumno no estaba".
En realidad ambos entraron, pero en momentos distintos (desfase de minutos).

**Paso 1 — identificar el meeting ID:**
```sql
SELECT az.id, az.acuity_datetime, az.zoom_meetingid, az.zoom_clasecompletada
FROM mdl_i3code_acuityZoom az
JOIN mdl_user u ON u.id = az.studentid
WHERE u.email = 'alumno@empresa.com'
  AND az.acuity_datetime LIKE '2026-MM-DD%';
```

**Paso 2a — si la clase fue ayer o antes (ingesta ya corrio):**
```sql
SELECT zoom_name, zoom_email, zoom_jointime, zoom_leavetime,
       zoom_duration AS segundos, ROUND(zoom_duration/60,1) AS minutos
FROM mdl_i3code_acuityZoom_participants
WHERE zoom_meetingid = <MEETING_ID>
ORDER BY zoom_jointime;
```
Si la tabla sale vacia, ir al Paso 2b.

**Paso 2b — verificar si el meeting fue iniciado (API directa):**
```bash
TOKEN=$(curl -s -X POST "https://zoom.us/oauth/token?grant_type=account_credentials&account_id=<ACCOUNT_ID>" \
  -u "<CLIENT_ID>:<CLIENT_SECRET>" | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

curl -s "https://api.zoom.us/v2/meetings/<MEETING_ID>" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
```
Credenciales en `/home/coreadmin/tuspeaking-platform/services/moodle35-staging/.env`.

Interpretar el campo `status`:
- `waiting` = el host NUNCA inicio la reunion. Nadie pudo entrar (join_before_host=false).
  -> Incidencia del profesor. Ofrecer recuperacion al alumno.
- `started` = la reunion esta activa ahora mismo.
- (vacio o ausente) = reunion ya finalizada; ir al report de participantes.

Si el report dice "Meeting does not exist" = nadie entro nunca (meeting en waiting o cancelado).

**Paso 3 — interpretar participantes (si el meeting se inicio):**
- Si los tiempos NO se solapan: desfase real. Uno entro cuando el otro ya se habia ido.
  -> Contar como clase intentada; ofrecer recuperacion a ambas partes.
- Si los tiempos SI se solapan pero cortos (<10 min de 30): posible problema tecnico.
  -> Revisar con el profesor si hubo incidencia. Ofrecer recuperacion.
- Si solo aparece el profesor (0 min alumno): no-show del alumno.
- Si solo aparece el alumno: el profesor no entro (incidencia del profesor).

**Columnas reales de `mdl_i3code_acuityZoom_participants`:**
`id, zoom_meetingid, zoom_userid, zoom_name, zoom_email, zoom_jointime, zoom_leavetime, zoom_duration`
(zoom_duration en SEGUNDOS)

---

## Ingesta Zoom (recordatorio)

- Cron: `i3code_download_zoomdata.php` (04:05). Descarga Acuity->Zoom->participantes->estados->informe.
- Log: `admin/cli/logs/i3code_download_zoomdata/log_YYYYMMDD.log`.
- Relanzar a mano (Hetzner/Docker):
  ```bash
  docker exec -u www-data moodle35-app php /var/www/html/app/moodle/admin/cli/i3code_download_zoomdata.php
  ```
