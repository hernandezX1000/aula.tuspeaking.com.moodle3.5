# TICKET — Monitorización de crons incompleta tras la migración a Hetzner

**Fecha:** 2026-08-07 · **Repo:** `aula.tuspeaking.com.moodle3.5` (el código a tocar son los
scripts de `tus-tools/autocorrect/`; la parte de crontab se ejecuta en el LMS)
**IDs BACKLOG:** MON-2, MON-3, AC-4, REPO-3
**Origen:** revisión del digest de estado del 07/08 08:00 (`🟡 0 fallo, 2 aviso`)

---

## 0. Resumen en una línea

El cron de anoche corrió bien (0 fallos), pero **la red de seguridad que lo vigila está
incompleta**: el heartbeat no está instalado en el Hetzner, un backup manual puede
enmascarar la ausencia del automático, hay scripts que aún conectan a la BD como antes
de la migración, y la versión del digest que corre en producción **no está en el repo**.

Ninguno de los cuatro rompe el servicio hoy. Los cuatro hacen que un fallo futuro pase
en silencio — que es exactamente lo que ocurrió el 10-jul-2026.

---

## 1. Estado observado (digest 07/08 08:00)

```
🟢 DATOS Y ASISTENCIA
  ✅ Ingesta Zoom (4:05) — última hace 3.8 h
  ✅ Feeder reservas (Acuity→own_acuity) — 35 reservas en 24h
  ✅ Autocorrector (cada 2h) — última hace 2.0 h
  ✅ Quiz grader (cada 4h) — última hace 4.0 h
  ✅ Moodle cron (4:15) — última tarea hace 3.5 h
🟢 BACKUPS Y SEGURIDAD
  ✅ Backup BD aula (3:00) — db_aula_20260807_0755_fundae_manual.sql.gz (1256.9 MB)
  ✅ Backup BD CESCE (3:00) — db_cesce_20260807_0300.sql.gz (129.1 MB)
  ✅ Backup offsite (3:05) — sync OK
🟢 REPORTES
  ⚠️ Feedback (cada 30m) — pendiente configurar en Hetzner
🟢 MONITOR
  ⚠️ Heartbeat (cada hora) — pendiente configurar en Hetzner
🔒 Disco 48% · Swap 37% · SSL válido 62 d
Resumen: 13 procesos · 11 OK · 2 aviso · 0 fallo
```

Además, el digest de autocorrección de las 07:00 reporta **1 error en log**:

```
[ERROR BD] (1698, "Access denied for user 'moodle35'@'localhost'")
```

---

## 2. Hallazgos

### MON-2 · Heartbeat y feedback sin cron en el Hetzner — **Alta**

Los dos avisos del digest no son fallos de ejecución: son checks que llevan desde el
cutover (26-jul) marcados como *pendiente configurar en Hetzner*. El heartbeat
(`heartbeat_crons.sh`, cada hora) es el **dead-man's-switch**: avisa cuando un cron deja
de logar. Mientras no esté:

- La única vigilancia es el digest de 8:00/20:00.
- Si el digest cae (o su cron desaparece), **nadie avisa de nada**. Fallo silencioso.

El 10-jul se perdieron semanas de ingesta Zoom exactamente así: el monitor que debía
avisar era el digest de las 7:00, que también estaba caído.

**Cierra cuando:** heartbeat y feedback tienen cron activo en el crontab de `coreadmin`,
loguean en `~/hansel_logs/`, y el digest los da ✅ dos ciclos seguidos.

### MON-3 · Un backup manual puede enmascarar la ausencia del automático — **Alta**

El check de backup del aula dio ✅ con el fichero
`db_aula_20260807_0755_fundae_manual.sql.gz` — un volcado **manual de las 07:55**, no el
automático de las 03:00. El de CESCE sí es el correcto (`db_cesce_20260807_0300.sql.gz`).

Dos defectos en `check_file()`:

1. El patrón admite comodín, así que **cualquier** fichero del día casa — incluido uno
   manual. El check no distingue "hay backup de hoy" de "el cron de backup corrió".
2. `glob.glob()` no ordena: se usa `hits[0]`, que es un resultado arbitrario del
   sistema de ficheros, no el más reciente.

Consecuencia: si el backup automático de las 3:00 falla el día que alguien hizo un
volcado manual, el digest dice ✅. **No sabemos si el de las 3:00 de hoy corrió.**

**Fix:** que el check valide (a) que el nombre casa el patrón del **automático**
(`db_aula_%Y%m%d_0300.sql.gz` o equivalente sin sufijos), y (b) que el `mtime` cae dentro
de la ventana esperada. Ordenar por `mtime` y reportar el más reciente que cumpla.
Opcional: listar aparte los backups manuales como informativo, sin que puntúen.

### AC-4 · Scripts aún conectan a `localhost` → error 1698 — **Media**

Post-migración la BD vive en Docker y solo escucha por TCP en `127.0.0.1:3307`. `pymysql`
/ `mysql.connector` con `host='localhost'` intentan **socket Unix** → `1698 Access denied
for user 'moodle35'@'localhost'`.

Se arregló en `hansel_quiz_grader.py` (30-jul, commit `b398aac`) pero **no en el resto**:

| Fichero | Línea | Valor actual |
|---|---|---|
| `tus-tools/autocorrect/hansel_autocorrect.py` | 55 | `'host': 'localhost'` |
| `tus-tools/autocorrect/hansel_digest.py` | 37 | `DB_HOST = 'localhost'` |
| `tus-tools/autocorrect/hansel_status_digest.py` | 187 | `host='localhost'` |

Anoche no se notó porque no había entregas que procesar (0 writings, 0 audios). Con
entregas reales, el autocorrector no corrige nada y el digest lo reporta como ✅ (el log
se escribe igual; solo cambia el contenido). El caso de `hansel_status_digest.py` es peor:
`check_moodle_cron()` captura la excepción y devuelve **WARN "no verificable"** — es decir,
el check del Moodle cron degrada a aviso en vez de fallar. Hoy dio OK, luego en producción
esa línea ya está corregida (ver REPO-3).

**Fix:** patrón de `hansel_quiz_grader.py`:
`host=os.environ.get('MOODLE_DB_HOST','127.0.0.1')`, `port=int(os.environ.get('MOODLE_DB_PORT','3307'))`.

### REPO-3 · La versión de producción del digest NO está en el repo — **Alta**

Comparando el email de hoy con `tus-tools/autocorrect/hansel_status_digest.py` en `dev`:

| | Repo (`dev`) | Producción (email 07/08) |
|---|---|---|
| Checks | "Sync asistencia (cada 15m)" | *no aparece* |
| | *no existe* | "Feeder reservas (Acuity→own_acuity)" |
| Backups | "Backup BD principal (2:00)" | "Backup BD aula (3:00)" |
| | "Backup offsite Hetzner (3:00)" | "Backup offsite (3:05)" |
| Texto avisos | *no existe* | "pendiente configurar en Hetzner" |
| Nº procesos | 12 | 13 |

**Producción va por delante del repo.** Alguien editó el script en el servidor, saltándose
el flujo `dev → main → deploy` que CLAUDE.md marca como INVIOLABLE.

⚠️ **Consecuencia operativa inmediata: no desplegar `hansel_status_digest.py` desde el
repo hasta reconciliar.** Un `deploy.sh` hoy pisaría la versión buena con una peor
(perdería el check del feeder de reservas y volvería a los backups de las 2:00).

**Fix:** traer la versión de producción al repo (`scp` server→Mac), commit en `dev` como
"reconciliar deriva", y solo entonces aplicar AC-4 y MON-3 encima.

---

## 3. Orden de ejecución (importa)

1. **REPO-3 primero.** Reconciliar prod → repo. Sin esto, cualquier deploy destruye trabajo.
2. **AC-4** sobre la versión ya reconciliada.
3. **MON-3** sobre la versión ya reconciliada.
4. **MON-2** (crontab en el server) — independiente del código, se puede hacer en paralelo.
5. Verificar: dos digests seguidos (20:00 y 8:00) con 0 avisos y 0 fallos.

---

## 4. Diagnóstico pendiente (requiere SSH — Hansel)

Bloque de solo lectura, no modifica nada:

```bash
ssh coreadmin@46.225.232.27

# 1) ¿Qué crons hay realmente? (MON-2)
crontab -l
sudo crontab -l 2>/dev/null
ls -la /etc/cron.d/ 2>/dev/null

# 2) ¿Existen los logs de heartbeat y feedback? (MON-2)
ls -la ~/hansel_logs/ | sort -k9
ls -la ~/hansel_logs/heartbeat.log ~/feedback_cron.log 2>&1

# 3) ¿Corrió el backup automático de las 3:00 de hoy? (MON-3)
ls -la ~/backups/db_daily/ | tail -20
ls -la ~/backups/db_daily/ | grep "$(date +%Y%m%d)"

# 4) Deriva prod vs repo (REPO-3)
md5sum ~/scripts/hansel_status_digest.py ~/scripts/hansel_autocorrect.py \
       ~/scripts/hansel_digest.py ~/scripts/hansel_quiz_grader.py
grep -n "localhost\|127.0.0.1\|3307" ~/scripts/hansel_*.py

# 5) Confirmar el error 1698 (AC-4)
grep -n "1698\|Access denied" ~/hansel_logs/hansel_autocorrect.log | tail -5

# 6) Moodle cron — fuente autoritativa
docker exec -i moodle35-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" aulatuspeaking35 -e "
  SELECT classname, FROM_UNIXTIME(lastruntime) AS ultima, faildelay
  FROM mdl_task_scheduled
  WHERE disabled = 0
  ORDER BY lastruntime DESC;"'
```

**Lo que hay que mirar en la salida del punto 6:** `faildelay > 0` es una tarea que está
fallando y Moodle está reintentando con backoff — el digest actual no lo detecta porque
solo mira `MAX(lastruntime)`. Si aparece alguna, es un hallazgo nuevo.

Para llevarse la versión de producción al Mac (REPO-3):

```bash
scp coreadmin@46.225.232.27:'~/scripts/hansel_*.py' \
    ~/Proyectos/aula.tuspeaking.com.moodle3.5/_prod_reconcile/
```

---

## 5. Mejora de fondo (candidata a ROADMAP, no a este ticket)

El digest comprueba **frescura de logs**, no **resultado**. Un cron que corre y falla
escribe log igual → sale ✅. Se ve en los dos casos de hoy: el error 1698 no aparece en
el digest de estado, y un backup manual puntúa como automático.

Regla a adoptar: *un check verifica el efecto, no la ejecución.* Backup → fichero del
patrón automático con tamaño esperado. Autocorrector → 0 líneas `[ERROR` en el log del
ciclo. Moodle cron → ninguna tarea con `faildelay > 0`.

---

## 6. Referencias

- `docs/BACKLOG.md` — MON-2, MON-3, AC-4, REPO-3
- `docs/sessions/2026-07-10.md` — incidente de crons caídos que motivó el heartbeat
- Memoria: `aula-crons-autocorrect`, `conexion-bd-aula`
- `CLAUDE.md` §Flujo de trabajo (INVIOLABLE) — la regla que REPO-3 incumple
