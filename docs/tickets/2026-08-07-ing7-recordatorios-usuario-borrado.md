# TICKET ING-7 — Los recordatorios de clase se cortan cada noche por dos cuentas inactivas

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Rama:** `dev` · **Prioridad:** Alta
**Fecha:** 2026-08-07 · **Estado:** 🟡 fix hecho en el repo, **PENDIENTE DE DESPLEGAR**
**Fichero:** `newAcuity.php` (webhook de Acuity — se ejecuta en **cada reserva**)

---

## 1. Síntoma

Cada noche, el cron de Moodle procesa los recordatorios de clase en orden, envía unos
cuantos y **aborta la tarea entera**. Todos los eventos posteriores se quedan **sin
recordatorio**:

```
[Local Reminder] All reminders was sent successfully for event#636059 !
[Local Reminder] All reminders was sent successfully for event#636060 !
[Local Reminder] Starting sending reminders for 636061 [type: user]
Scheduled task failed: legacy_plugin_cron_task, Usuario no válido
```

Afecta a `\local_reminders\task\send_reminders` y `\core\task\legacy_plugin_cron_task`,
ambas con `faildelay > 0` desde el **26-jul-2026** (día del cutover a Hetzner).

**Impacto:** alumnos que no reciben el aviso de su clase → **no-shows injustificados**.

---

## 2. Causa raíz

`newAcuity.php` crea **cinco** eventos de calendario por cada reserva: el alumno, el
profesor y **tres copias de cortesía** a cuentas del equipo:

| userid | Quién | Estado real | Eventos acumulados |
|---|---|---|---|
| `$studentID` | el alumno | ✅ | — |
| `$teacherID` | el profesor | ✅ | — |
| 14 | Hansel Fernández | ✅ activo (accede a diario) | 131.467 |
| 2 | Carmen Lacabe | ⚠️ **suspendida**, sin acceder desde feb-2026 | 136.468 |
| 48 | Guillermo Bethencourt | ❌ **BORRADO** de Moodle en 2018 (`deleted=1`) | 125.316 |

El código, tal cual estaba (líneas 184-188):

```php
$sql .= ", (… $studentID …)"; // ID Estudiante
$sql .= ", (… $teacherID …)"; // ID Profesor
$sql .= ", (… 14 …)";         // ID de Hansel
$sql .= ", (… 2 …)";          // ID de Carmen
$sql .= ", (… 48 …)";         // ID de Guillermo   ← cuenta borrada en 2018
```

Nadie retiró el `48` al borrar la cuenta. **Ocho años después, cada reserva sigue creando
un evento para un usuario que no existe**, y el plugin de recordatorios revienta al
intentar avisarle.

**Verificado:** última ejecución del script el **07-ago-2026 a las 13:06** — está en pleno
uso, creando eventos rotos ahora mismo.

---

## 3. El fix (ya aplicado en el repo, commit en `dev`)

Se eliminan las copias a **Carmen (2)** y **Guillermo (48)**. Quedan alumno, profesor y
Hansel (14). Dos cambios en `newAcuity.php`:

1. **Línea ~187-188:** eliminadas las dos concatenaciones `$sql .=` de los userid 2 y 48.
   *(Se elimina la línea entera, no solo el número: es una tupla completa del `VALUES`.)*
2. **Línea ~207:** el `SELECT` que recupera los IDs recién creados pasa de
   `(… OR userid = 14 OR userid = 2 OR userid = 48)` a `(… OR userid = 14)`.

⚠️ **`newAcuity.php` no estaba versionado** hasta hoy: caía por la regla `/*` del
`.gitignore` de allow-list y nadie lo añadió. **Por eso se perdió el 1-ago-2026.** Ya está
en el repo, junto con `eventupdater.php`, `reminder.php`, `cancelbymail.php`,
`formClass.php` y `formFeedback.php` (ninguno lleva credenciales: usan `$CFG->dbpass`).

`modifyAcuity.php` y `cancelAcuity.php` siguen **fuera del repo a propósito** (llevan la
API key de Acuity). **Comprobar si tienen el mismo `48` antes de dar esto por cerrado.**

---

## 4. Despliegue — PENDIENTE

### 4.1 Enviar (Mac)

```bash
cd ~/Proyectos/aula.tuspeaking.com.moodle3.5
git add newAcuity.php && git commit -m "ING-7 fix: quitar eventos a Carmen (suspendida) y Guillermo (borrado)" && git push
scp newAcuity.php coreadmin@46.225.232.27:/tmp/
```

### 4.2 Validar sintaxis ANTES de sustituir (servidor)

Este fichero corre en **cada reserva**: un parse error deja el pipeline KO.

```bash
sudo cp /tmp/newAcuity.php /mnt/moodle-data/moodle-code/newAcuity.php.NUEVO
docker exec -u www-data moodle35-app php -l /var/www/html/app/moodle/newAcuity.php.NUEVO
```

Solo si responde `No syntax errors detected`:

```bash
sudo cp /mnt/moodle-data/moodle-code/newAcuity.php /mnt/moodle-data/moodle-code/newAcuity.php.bak-20260807
sudo mv /mnt/moodle-data/moodle-code/newAcuity.php.NUEVO /mnt/moodle-data/moodle-code/newAcuity.php
sudo chown 33:33 /mnt/moodle-data/moodle-code/newAcuity.php
```

### 4.3 Probar con una reserva real

Hacer una reserva de prueba en Acuity y comprobar que se crean **3 eventos, no 5**:

```bash
aula-sql "SELECT userid, name, FROM_UNIXTIME(timemodified) creado FROM mdl_event
WHERE timemodified > UNIX_TIMESTAMP() - 600 ORDER BY id DESC LIMIT 10"
```

**Criterio de aceptación:** aparecen 3 filas (alumno, profesor, 14) y ninguna con userid 2 o 48.

### 4.4 Limpiar los eventos huérfanos futuros — solo tras 4.3

```bash
aula-sql --write mdl_event
```
```sql
SELECT COUNT(*) FROM mdl_event e JOIN mdl_user u ON u.id = e.userid
WHERE e.eventtype='user' AND u.deleted = 1 AND e.timestart > UNIX_TIMESTAMP();   -- ~192

DELETE e FROM mdl_event e JOIN mdl_user u ON u.id = e.userid
WHERE e.eventtype='user' AND u.deleted = 1 AND e.timestart > UNIX_TIMESTAMP();

SELECT COUNT(*) FROM mdl_event e JOIN mdl_user u ON u.id = e.userid
WHERE e.eventtype='user' AND u.deleted = 1 AND e.timestart > UNIX_TIMESTAMP();   -- 0
```

### 4.5 Reactivar las tareas y verificar

```bash
aula-sql --write mdl_task_scheduled
```
```sql
UPDATE mdl_task_scheduled SET faildelay = 0
WHERE classname IN ('\\core\\task\\legacy_plugin_cron_task',
                    '\\local_reminders\\task\\send_reminders');
```

```bash
aula-php admin/cli/scheduled_task.php --execute='\local_reminders\task\send_reminders' 2>&1 | tail -20
```

⚠️ Esto **enviará los recordatorios pendientes**. Normalmente solo mira eventos próximos,
no doce días de acumulado, pero conviene lanzarlo sabiéndolo.

**Criterio de cierre:** al día siguiente,
`SELECT COUNT(*) FROM mdl_task_scheduled WHERE disabled=0 AND faildelay>0` devuelve **0**,
y `audit-estado.sh` deja de avisar.

### Rollback

```bash
sudo cp /mnt/moodle-data/moodle-code/newAcuity.php.bak-20260807 /mnt/moodle-data/moodle-code/newAcuity.php
sudo chown 33:33 /mnt/moodle-data/moodle-code/newAcuity.php
```

Y si hiciera falta deshacer el borrado de eventos: `aula-sql --write` deja la tabla copiada
como `mdl_event_bak_YYYYMMDD_HHMM`.

---

## 5. Pendiente aparte (no bloquea)

- **`modifyAcuity.php` / `cancelAcuity.php` / `sincronizar_acuityZoom.php`**: comprobar si
  llevan el mismo `48`. Si lo llevan, al modificar o cancelar una reserva se recrearía el
  evento roto y el fix quedaría a medias.
- **~400.000 eventos de cortesía acumulados** en `mdl_event` (125.316 de ellos huérfanos,
  de Guillermo, mayoritariamente pasados). Limpieza de mantenimiento; no urge.
- **977 eventos huérfanos de 2019-2020** (6 cuentas borradas, todos pasados). Polvo.
- **Revisar si la lógica de "copias de cortesía" tiene sentido hoy.** Genera ~3 eventos
  extra por reserva. Si el objetivo era que Hansel viera las clases en su calendario, hay
  formas más baratas.

---

## 6. Cómo se encontró

`audit-estado.sh`, en su **primera ejecución** (07-ago-2026), avisó de *"2 tareas
fallando"*. Antes, esas dos tareas llevaban **12 días en rojo** sin que nada lo dijera: el
digest solo miraba `MAX(lastruntime)`, no `faildelay`.

Cadena completa: *¿corrió el cron anoche?* → el digest tenía 2 avisos → se monta la
auditoría → detecta 2 tareas fallando → el log señala un evento → el evento apunta a un
usuario borrado en 2018 → la línea que lo causa lleva ocho años con el comentario
`// ID de Guillermo`.
