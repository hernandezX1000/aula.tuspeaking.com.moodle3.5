# Runbook — Asistencia y Zoom

Cómo consultar asistencia, estados y duración en Zoom, y los problemas típicos de cierre.
Fuente única: este fichero (repo). Llega al server con cada deploy.

Tablas clave:
- **`mdl_i3code_acuityZoom`** — 1 fila por clase (reserva Acuity ↔ reunión Zoom).
- **`mdl_i3code_acuityZoom_participants`** — participantes por reunión (join/leave, duración).
- **`mdl_i3code_acuityZoom_informe`** — datos del panel.

> Conexión: `mysql -u moodle35 -p'<pass>' aulatuspeaking35` (contraseña en `config.php`/`.env`, nunca aquí).

---

## Estados de asistencia (`zoom_clasecompletada`)

| Valor | Significado |
|-------|-------------|
| `1` | **Asistió** |
| `2` | **Ausencia** (no-show real) |
| `3` | **Verificando asistencia** (sin confirmar aún) |
| `0` | **Sin datos** |

> Ojo: "ausencia" es el **2**, no el 0. El 0 es "sin datos". No confundirlos.
> "Cancelada" no es un estado de `zoom_clasecompletada`: se refleja en `acuity_canceled = 1`.

---

## Consultar la asistencia de un alumno

Por email (el `studentid` enlaza a `mdl_user.id`):

```sql
SELECT z.acuity_starttime            AS inicio,
       z.acuity_type                 AS tipo,
       z.zoom_clasecompletada        AS estado,   -- 1 asistió · 2 ausencia · 3 verificando · 0 sin datos
       z.zoom_duration_total         AS min_zoom,  -- duración total de la reunión (min)
       z.zoom_participants           AS n_part,
       z.acuity_canceled             AS cancel,
       z.acuity_rescheduled          AS reprog
FROM mdl_i3code_acuityZoom z
JOIN mdl_user u ON u.id = z.studentid
WHERE u.email = 'alumno@empresa.com'
ORDER BY z.acuity_starttime;
```

Resumen por estado de un alumno (para cierre/FUNDAE):

```sql
SELECT z.zoom_clasecompletada AS estado, COUNT(*) AS n
FROM mdl_i3code_acuityZoom z
JOIN mdl_user u ON u.id = z.studentid
WHERE u.email = 'alumno@empresa.com'
GROUP BY z.zoom_clasecompletada;
```

Por curso (todos los alumnos, últimos 30 días):

```sql
SELECT z.zoom_clasecompletada AS estado, COUNT(*) AS n
FROM mdl_i3code_acuityZoom z
JOIN mdl_course c ON c.id = z.courseid
WHERE c.fullname LIKE '%Tekia%'
  AND z.acuity_starttime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY z.zoom_clasecompletada;
```

---

## Consultar la duración en Zoom

**Nivel clase** — `mdl_i3code_acuityZoom`:
- `zoom_duration_total` — minutos totales de la reunión.
- `zoom_participants` — nº de conectados.
- `zoom_starttime` / `zoom_endtime` — inicio/fin real de la reunión.

**Nivel participante** — `mdl_i3code_acuityZoom_participants` (detalle por persona):

```sql
-- Primero mira las columnas exactas:  SHOW COLUMNS FROM mdl_i3code_acuityZoom_participants;
SELECT p.zoom_duration AS segundos,        -- OJO: aquí la duración va en SEGUNDOS
       ROUND(p.zoom_duration/60,1) AS minutos
FROM mdl_i3code_acuityZoom_participants p
WHERE p.zoom_meetingid = '85650193634';
```

> Unidades: en la tabla **participantes** `zoom_duration` está en **segundos**; en la tabla
> **clase** `zoom_duration_total` está en **minutos**. No mezclarlas.

Si una reunión aparece con `zoom_participants = 1` y poca duración, suele ser el host esperando
a un alumno que no entró (no-show), o una clase reprogramada.

---

## Problemas de cierre (los típicos)

1. **Clases en "verificando" (estado 3) que no se cierran solas.** El sistema no pudo confirmar
   asistencia porque su reunión Zoom no tiene participantes (nadie entró, cancelada o no-show).
   Necesitan decisión humana (asistió / ausencia / cancelada) — no tocar en bloque si pueden tener
   ajuste manual previo. Listar las pendientes:

   ```sql
   SELECT id, zoom_meetingid, acuity_starttime, zoom_clasecompletada AS estado
   FROM mdl_i3code_acuityZoom
   WHERE zoom_clasecompletada IN (0,3)
     AND acuity_starttime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
   ORDER BY acuity_starttime DESC;
   ```

2. **Reuniones sin participantes que la ingesta reintenta cada vuelta.** Si una clase se dio pero
   nadie figura en `_participants`, esa reunión sale siempre como "pendiente" en la ingesta y no
   inserta nada (no hay a quién traer). No es un fallo; es que Zoom no tiene datos de esa reunión.

3. **Sesiones dobles / extendidas (clases fantasma).** Cuando dos slots se dan como una sola
   reunión larga, toda la reunión cae en el primer slot (estado 1) y el segundo queda "fantasma"
   en estado 3. Al cerrar, contar por **duración real**, no por nº de clases.

4. **⚠️ Reversión del sync (lección aprendida).** El sync de asistencia corre cada 15 min. Si
   corriges una clase **a mano** sobre datos que solo están en el CSV crudo (no en
   `_participants`), el sync la **revierte** en la siguiente vuelta. La corrección duradera es que
   la **ingesta funcione** y meta los participantes; entonces el sync deja de inventar ausencias.

---

## Ingesta Zoom (recordatorio)

- Cron: `i3code_download_zoomdata.php` (04:05). Descarga Acuity→Zoom→participantes→estados→informe.
- Log real: `admin/cli/logs/i3code_download_zoomdata/log_YYYYMMDD.log` (ruta relativa; **no** es `zoom_logs/cron.log`, ahí solo va stderr).
- Relanzar a mano:
  ```bash
  nohup /usr/local/bin/php73 -d disable_functions= -d memory_limit=4096M \
    /home/aulatuspeaking/.ftp-users/moodle/admin/cli/i3code_download_zoomdata.php \
    > /home/aulatuspeaking/zoom_logs/cron.log 2>&1 &
  ```
