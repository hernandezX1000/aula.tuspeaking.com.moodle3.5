# TICKET · Refactor de credenciales a `secrets.php`

Fecha propuesta: 2026-08-06 · Autor: agente de refactor (PROPUESTA, sin commit)
Fuente de verdad: `docs/AUDITORIA-COBERTURA-GIT.md` §3 (CON-SECRETOS) y §8 (refactor agrupado).

> **ESTADO: propuesta en working dir.** No se ha ejecutado ningún comando git (ni rama, ni
> commit, ni push). No se ha tocado producción, ni el servidor, ni la BD. No hay ningún valor
> real de credencial en ningún fichero versionado. El humano revisa, prueba y promueve.

---

## 1. Diseño elegido

Un único fichero `secrets.php` en la **raíz de Moodle** (junto a `config.php`), **gitignored**, que
expone las credenciales mediante `define()` con guarda `if(!defined())`. Se versiona solo la
plantilla `secrets.php.example` con placeholders `CHANGE_ME`.

Reglas de lectura por tipo de secreto:

- **Password de BD de Moodle** → se lee de `$CFG->dbpass` (y `$CFG->dbhost/dbname/dbuser`) en los
  scripts que ya hacen `require('config.php')`. NO se duplica en `secrets.php`. Para scripts
  *standalone* que no bootstrapean Moodle, `secrets.php` ofrece el fallback `TS_DB_HOST/NAME/USER/PASS`.
- **Acuity** → constantes `ACUITY_USER_ID` y `ACUITY_API_KEY`.
- **Password demo** → constante `DEMO_DEFAULT_PASSWORD`.
- **Zoom** → NADA. El JWT está deprecado; la ingesta usa S2S OAuth vía `get_config('zoom')`.

Constantes definidas en `secrets.php.example`:

| Constante | Uso | Placeholder |
|---|---|---|
| `ACUITY_USER_ID` | user/owner de Acuity para la API | `CHANGE_ME` (15680788) |
| `ACUITY_API_KEY` | API key de Acuity | `CHANGE_ME` |
| `DEMO_DEFAULT_PASSWORD` | password por defecto de cuentas demo | `CHANGE_ME` |
| `TS_DB_HOST/NAME/USER/PASS` | BD para scripts standalone (fallback) | `CHANGE_ME` |

---

## 2. Ficheros cambiados en esta propuesta (working dir)

### Nuevos / versionados
- **`secrets.php.example`** (nuevo) — plantilla con placeholders + cabecera de despliegue (`cp`,
  `chown 33:33`, `chmod 640`, ubicación en raíz Moodle).
- **`.gitignore`** — añadido `secrets.php` a la sección "Secretos: NUNCA versionar" y excepción
  `!/secrets.php.example` en la sección de ficheros de raíz permitidos.

### Refactorizados (5 ficheros trackeados; comportamiento idéntico)
| Fichero | Antes | Ahora |
|---|---|---|
| `misclases.php` | `new PDO(...,"moodle35","TuspeakingFix2025!")` | `new PDO("...host={$CFG->dbhost};dbname={$CFG->dbname}...", $CFG->dbuser, $CFG->dbpass)` |
| `newAcuity.php` | `$userID='15680788'; $key='7727…4693';` (x2 bloques) | `$userID=ACUITY_USER_ID; $key=ACUITY_API_KEY;` + `require_once($CFG->dirroot.'/secrets.php')` |
| `admin-panel/demo_manager.php` | `$password='Success2026!'` | `$password=DEMO_DEFAULT_PASSWORD` + `require_once __DIR__.'/../secrets.php'` |
| `admin/cli/i3code_download_zoomdata.php` | `$userID=15680788; $apiKey='7727…4693'` (solo Acuity) | `$userID=ACUITY_USER_ID; $apiKey=ACUITY_API_KEY;` + `require_once($CFG->dirroot.'/secrets.php')`. Zoom se deja como está (ya usa `get_config('zoom')`) |
| `empresas/acuity_zoom.php` | `$acuityUserID='15680788'; $acuityApiKey='7727…4693'` | `ACUITY_USER_ID/ACUITY_API_KEY` + `require_once($CFG->dirroot.'/secrets.php')`. El PDO ya usaba `$CFG->dbpass`, sin cambios |

> Nota `newAcuity.php`: había **dos** bloques idénticos de credenciales (líneas ~36 y ~96), ambos
> sustituidos.

### Verificado
- Grep de literales (`TuspeakingFix2025!`, `7727…4693`, `Success2026!`) en los 5 ficheros: **0 coincidencias**.
- `git check-ignore`: `secrets.php` → IGNORED; `secrets.php.example` y los 5 refactorizados → tracked;
  `local/misclases/lib.php` y `admin-panel/config.php` → siguen IGNORED.

### Hallazgo colateral (no era un secreto)
- `blocks/acuityblock/block_acuityblock.php` contiene `15680788`, pero es el **owner ID público** de
  Acuity (aparece en URLs de embed `embed.acuityscheduling.com/.../15680788.js` y en enlaces de
  reserva `tuspeaking.as.me?owner=15680788`). NO contiene la API key. No requiere refactor; se puede
  versionar como SEGURO. (Opcional: mover el owner ID a `ACUITY_USER_ID` por higiene, no urgente.)

---

## 3. PLAN para los ficheros SOLO en el servidor (gitignored, NO están en el repo)

Estos ficheros no se editan aquí (no existen en el clon). Aplicar el mismo patrón **en el servidor**
tras crear `secrets.php`. Agrupado por credencial.

### Grupo A — Password BD `TuspeakingFix2025!`
Para cada fichero: si ya hace `require('config.php')` → usar `$CFG->dbpass` (y `$CFG->dbhost/dbname/dbuser`).
Si es standalone → `require_once '<ruta>/secrets.php';` y usar `TS_DB_PASS` (+ `TS_DB_HOST/NAME/USER`).

| Fichero (server) | Literal a sustituir | Recomendado |
|---|---|---|
| `config.php` | `$CFG->dbpass = 'TuspeakingFix2025!'` | **Es la fuente**; no cambia. (Es el canon de la password de Moodle.) |
| `admin-panel/config.php` | `define('DB_PASS','TuspeakingFix2025!')` | `require_once __DIR__.'/../secrets.php';` → `define('DB_PASS', TS_DB_PASS)` (idem DB_HOST/NAME/USER si están en duro) |
| `db_api.php` | `TuspeakingFix2025!` en el PDO | `TS_DB_PASS` (o `$CFG->dbpass` si carga config) |
| `monitor_zoom.php` | `new PDO(... 'TuspeakingFix2025!')` | `TS_DB_PASS` / `$CFG->dbpass` |
| `tutorias_con_profesor.php` | `TuspeakingFix2025!` | `$CFG->dbpass` si carga config; si no, `TS_DB_PASS` |
| `webhook_jotform_evaluaciones.php` | `'password' => 'TuspeakingFix2025!'` | `TS_DB_PASS` |
| `local/misclases/lib.php` | `new PDO(... "TuspeakingFix2025!")` | `$CFG->dbpass` si el include tiene `$CFG`; si no, `TS_DB_PASS` |
| `portal/rrhh.php` | password BD | `TS_DB_PASS` / `$CFG->dbpass` |
| `feedback/*`, `empresas/*` (los .py/.php ya excluidos), `evaluaciones/*`, `reportes*/*` | `TuspeakingFix2025!` repetido | Barrido: incluir `secrets.php` y usar `TS_DB_PASS`. Los `.py` necesitan su propio mecanismo (env var / fichero de secreto Python), no `secrets.php`; ver nota abajo |

> **Nota Python**: los scripts `.py` de `reportes*/`, `evaluaciones/` y `reportes_1to1/` no pueden
> leer `secrets.php`. Para ellos, externalizar a variable de entorno (`os.environ`) o a un
> `secrets.env` gitignored cargado con `python-dotenv`. Fuera del alcance de este ticket PHP; queda
> anotado como sub-tarea.

### Grupo B — Acuity API key `7727…4693`
Patrón: `require_once '<ruta>/secrets.php';` → `ACUITY_USER_ID` / `ACUITY_API_KEY`.

| Fichero (server) | Literal | Recomendado |
|---|---|---|
| `acuityapi.php` | `$apiKey = '7727…4693'` | `ACUITY_API_KEY` (+ `ACUITY_USER_ID`) |
| `cancelAcuity.php` | `$key = '7727…'` | `ACUITY_API_KEY` |
| `modifyAcuity.php` | Acuity key | `ACUITY_API_KEY` |
| `modifyAndCreateAcuity.php` | Acuity key | `ACUITY_API_KEY` |

> `modifyAndCreateAcuity_NUEVO.php` es BASURA (scaffolding), no refactorizar: borrar o dejar ignorado.
> `cancelbymail.php` (SEGURO, ya en repo) hace `require_once acuityapi.php`; una vez `acuityapi.php`
> lea de `secrets.php`, `cancelbymail.php` sigue funcionando sin cambios.

### Grupo C — Zoom JWT (deprecado)
| Fichero (server) | Recomendado |
|---|---|
| `own_ZoomAPIToken.php` | El JWT `key`+`secret` en duro está muerto (post-migración se usa S2S OAuth). Migrar la 1ª función a `get_config('zoom')` (como ya hace la 2ª función del mismo fichero) o retirar el fichero si no se usa. No va a `secrets.php`. |

### Grupo D — Password demo `Success2026!`
Único fichero afectado (`admin-panel/demo_manager.php`) ya refactorizado en esta propuesta (§2). Sin
pendientes en servidor.

**Resumen pendientes servidor:** ~1 (config canon, no cambia) + ~7 PHP de Grupo A + barrido
`feedback/empresas/evaluaciones/reportes` + 4 PHP de Grupo B + 1 de Grupo C + sub-tarea Python.

---

## 4. CHECKLIST de validación

Antes de promover (en el server, tras crear `secrets.php`):

- [ ] Existe `secrets.php` en la raíz Moodle con valores reales; `chown 33:33`; `chmod 640`.
- [ ] `php -l` OK en los 5 ficheros refactorizados.
- [ ] `grep -RnE "TuspeakingFix2025|7727321b66b8210424f1d4d984584693|Success2026" <root>` NO devuelve
      nada en ficheros trackeados (sí puede aparecer en `secrets.php` real, que está ignorado).
- [ ] `git check-ignore -v secrets.php` → IGNORED · `git check-ignore -v secrets.php.example` → NO ignorado.
- [ ] El sitio carga (home + login) — confirma que `config.php` + `secrets.php` conviven.
- [ ] `misclases.php`: abrir "Mis Clases" de un alumno con clases → carga la tabla (PDO lee `$CFG->dbpass`).
- [ ] `empresas/acuity_zoom.php` (admin): seleccionar un Appointment Type → devuelve URLs de Zoom
      (Acuity key OK) y exporta CSV.
- [ ] `newAcuity.php`: flujo de reserva/consulta por `id` → responde con datos de Acuity (ambos bloques).
- [ ] Ingesta CLI: `php admin/cli/i3code_download_zoomdata.php` → el log muestra "Total Acuity
      encontrados" y no error de credenciales; Zoom sigue vía `get_config('zoom')`.
- [ ] Panel demo (`admin-panel/demo_manager.php`): crear una demo de prueba → el usuario se crea con
      la password de `DEMO_DEFAULT_PASSWORD` (probar login de esa cuenta demo).

## 5. Pasos de despliegue (server)

1. `cd <MOODLE_ROOT>` (en el server, la carpeta es `tuspeaking-platform`, no `lms`).
2. `cp secrets.php.example secrets.php` y rellenar valores reales (Acuity user/key, demo password;
   y `TS_DB_*` solo si se van a refactorizar scripts standalone).
3. `chown 33:33 secrets.php && chmod 640 secrets.php`.
4. Desplegar los 5 ficheros refactorizados (vía el flujo dev→CI→staging→master; NO editar prod a mano).
5. Purgar cachés de Moodle: `php admin/cli/purge_caches.php`.
6. Ejecutar la CHECKLIST §4.
7. Solo si todo OK: aplicar el PLAN §3 a los ficheros del servidor, grupo a grupo, revalidando.

> **Rotación pendiente (bloqueante de seguridad, ticket aparte):** Acuity key, password BD y password
> demo están comprometidas (ya estuvieron en el histórico de git). Externalizar NO las rota. Ver
> `docs/tickets/TICKET-SEGURIDAD-rotar-credenciales.md` y limpiar histórico (`git filter-repo`/BFG)
> antes de que el repo salga de un entorno controlado.

---

## 6. Instrucciones git para el humano (ejecutar en tu Mac, NO el agente)

El agente dejó todo como modificaciones del working dir. Tú creas la rama y el commit:

```bash
cd <clon del repo aula>
git status                      # revisar: secrets.php.example, .gitignore, misclases.php,
                                # newAcuity.php, admin-panel/demo_manager.php,
                                # empresas/acuity_zoom.php, admin/cli/i3code_download_zoomdata.php,
                                # docs/tickets/TICKET-SECRETS-PHP-refactor.md
git check-ignore -v secrets.php            # DEBE salir ignorado (por si existiera en local)
git checkout -b agent/secrets-php
git add secrets.php.example .gitignore misclases.php newAcuity.php \
        admin-panel/demo_manager.php empresas/acuity_zoom.php \
        admin/cli/i3code_download_zoomdata.php \
        docs/tickets/TICKET-SECRETS-PHP-refactor.md
git status                      # CONFIRMAR que secrets.php (real, con valores) NO aparece
git commit -m "seguridad: externalizar credenciales a secrets.php (5 ficheros + plantilla)"
```

Luego: probar en staging con la CHECKLIST §4, y promover por el flujo canónico
`dev → CI → staging → master` (INVIOLABLE: solo ramas dev/master, nunca editar prod a mano).
Mergear `agent/secrets-php → dev` y dejar que CI/staging validen antes de `dev → master`.
