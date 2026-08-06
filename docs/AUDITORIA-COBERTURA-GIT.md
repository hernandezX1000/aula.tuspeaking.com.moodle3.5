# Auditoría de cobertura Git — repo `aula` (Moodle 3.5 custom)

Fecha: 2026-08-06 · Fuente de verdad del código custom: rescate Dinahosting
`/tmp/rescue_check/aula-CUSTOMS-dinahosting/` (root php, `local/`, `theme/`, `blocks/`, `admin-panel/`, `portal/`…).
Cruzado con `git ls-files` (235 rutas versionadas hoy) y con el `.gitignore` vigente.

> **NO se ha hecho ningún commit ni push.** Este documento es sólo inventario + plan.

---

## 1. Resumen ejecutivo

| Categoría | Qué es | Acción | Nº aprox. |
|---|---|---|---|
| **SEGURO (a versionar)** | Custom sin credenciales en duro, hoy fuera de git | Des-ignorar y commitear | **~777 ficheros** |
| **CON-SECRETOS** | Credencial/clave en duro | NO versionar → refactor a `secrets.php` | **~13 ficheros** (+ listas ya excluidas de `feedback/empresas/evaluaciones/reportes_*`) |
| **CORE** | Moodle 3.5 estándar (re-descargable de moodle.org) | Ignorar | **~1.150 ficheros** (themes core + blocks core + lang + php core de raíz) |
| **BASURA** | `.bak*`, `_test`, `_NUEVO`, `phpinfo`, `opcache_reset`, `.disabled`, `__pycache__`, `.pyc` | Ignorar | **~180 ficheros** (incl. `rocketchat.disabled` 113) |

**Ya en git hoy:** 235 rutas (22 `.php` custom de raíz + `admin-panel` 6 + `_tszoom` + `contenido/` + `docs/` + reportes/plantillas + assets de marca…).

### RIESGO PRINCIPAL (bloqueante, atención inmediata)
Hay **credenciales reales YA COMMITEADAS** en git (no basta con dejar de trackearlas: están en el histórico):

| Fichero (en git) | Secreto embebido | Línea |
|---|---|---|
| `newAcuity.php` | Acuity API key `7727321b66b8210424f1d4d984584693` | 36 |
| `misclases.php` | Password BD `TuspeakingFix2025!` | 16 |
| `admin-panel/demo_manager.php` | Password demo `Success2026!` | 21 |

**Acción:** rotar esas credenciales (Acuity + BD + password demo), refactorizar los 3 ficheros a `secrets.php`/`$CFG`, y limpiar el histórico (`git filter-repo`/BFG) antes de que el repo salga de un entorno controlado. Mientras no se roten, considerar esas claves comprometidas.

---

## 2. SEGURO — a versionar (custom sin secretos)

### 2.1 Ficheros `.php` de raíz (14, hoy fuera de git)
Verificados con grep de credenciales (sin literal de password/API key; los que usan BD lo hacen vía `$CFG->dbpass`):

- `cancelbymail.php`  ·  `dashboard.php`  ·  `enviacorreo.php`  ·  `eventupdater.php`
- `formClass.php`  ·  `formFeedback.php`  ·  `fundae_calificaciones.php`  ·  `fundae_grupos.php`
- `mailsender.php`  ·  `own_MeetingDataGet.php`  ·  `reminder.php`  ·  `tuspeakingvrlogin.php`
- `visor.php`  ·  `coding_fundae_dl.php`

> Nota: `cancelbymail.php` hace `require_once acuityapi.php` (que SÍ tiene la key). El fichero en sí es seguro; la key vive en `acuityapi.php` (CON-SECRETOS). Al refactorizar, `acuityapi.php` leerá de `secrets.php` y `cancelbymail` seguirá funcionando.

### 2.2 `local/` — 89 ficheros limpios (0 en git hoy)
Todo `local/` es custom. Sin secretos salvo 1 fichero (ver §3).

| Subcarpeta | Ficheros limpios | Nota |
|---|---|---|
| `local/misclases/` | 4 | **excluir `lib.php`** (password BD en duro) |
| `local/navbarplus/` | 11 | |
| `local/reminders/` | 29 | |
| `local/resourcenotif/` | 11 | |
| `local/staticpage/` | 13 | |
| `local/tsbranding/` | 19 | (excluidas ~16 `*.bak*` de assets) |
| `local/readme.txt`, `upgrade.txt` | 2 | placeholders core, inocuos |

### 2.3 `theme/` custom (0 en git hoy)
| Tema | Ficheros | Clasificación |
|---|---|---|
| `theme/tuspeaking/` | 13 | **SEGURO** — OJO: contiene un **`.git` anidado** (repo dentro del repo). NO commitear ese `.git`; o se borra y se versiona plano, o se registra como submódulo. |
| `theme/lambda/` | 89 (excl. 8 `.bak`) | **SEGURO** — tema contrib personalizado (renderers/layout con marca tuSpeaking). |
| `theme/boost`, `bootstrapbase`, `clean`, `more` | 1.032 | **CORE** (ver §4) |

### 2.4 `blocks/` custom + contrib (0 en git hoy; todos con `version.php` válido)
**Custom tuSpeaking:**
- `blocks/acuityblock/` (6) · `blocks/catalogue/` (118) · `blocks/fundae/` (9, excl. 2 `.bak`) · `blocks/fundae_inspector/` (3) · `blocks/messageteacher/` (28)

**Contrib personalizados (re-descargables, pero forman parte del sitio en marcha):**
- `blocks/configurable_reports/` (245) · `blocks/advnotifications/` (44) · `blocks/completion_progress/` (37, excl. 2 `.bak`) · `blocks/dedication/` (11) · `blocks/point_view/` (42) · `blocks/ranking/` (21) · `blocks/forum_aggregator/` (8)

> Decisión conservadora: versionar también los contrib porque están instalados y a veces parcheados; si se prefiere no versionar contrib puro, moverlos a §4 (CORE/re-instalable).

### 2.5 `admin-panel/` (6 en git; resto)
Ya versionados: `demo_manager.php`, `editor.php`, `functions.php`, `index.php`, `log_viewer.php`, `tools.json`, `cache_fundae.json`, `panel.log`.
Pendiente: `admin-panel/config.php` → **CON-SECRETOS** (no versionar). Los `tools.json.bak_*` → BASURA.

---

## 3. CON-SECRETOS — NO versionar (refactor a `secrets.php`)

Ficheros con **literal de credencial** confirmado por grep:

| Fichero | Secreto | Estado gitignore |
|---|---|---|
| `config.php` | Moodle `$CFG->dbpass` real | ya excluido |
| `acuityapi.php` | Acuity `apiKey = '7727…4693'` | ya excluido |
| `cancelAcuity.php` | Acuity key `$key='7727…'` | ya excluido |
| `modifyAcuity.php` | Acuity key | ya excluido |
| `modifyAndCreateAcuity.php` | Acuity key | ya excluido |
| `own_ZoomAPIToken.php` | Zoom JWT `key`+`secret` en duro | ya excluido |
| `db_api.php` | `TuspeakingFix2025!` | **añadir exclusión** |
| `monitor_zoom.php` | `new PDO(... 'TuspeakingFix2025!')` | **añadir exclusión** |
| `tutorias_con_profesor.php` | `TuspeakingFix2025!` | ya excluido |
| `webhook_jotform_evaluaciones.php` | `'password' => 'TuspeakingFix2025!'` | **añadir exclusión** |
| `local/misclases/lib.php` | `new PDO(... "TuspeakingFix2025!")` | **añadir exclusión** |
| `admin-panel/config.php` | `define('DB_PASS','TuspeakingFix2025!')` | **añadir exclusión** |
| `portal/rrhh.php` | password BD | ya excluido |

Además, ya en `.gitignore` por la misma razón: todo `feedback/*`, `empresas/*`, `evaluaciones/*`, `reportes/*`, `reportes_cesce/*` (scripts py/php con BD en duro).

**LEAK ya en histórico** (además de lo anterior): `newAcuity.php`, `misclases.php`, `admin-panel/demo_manager.php` — ver §1 Riesgo principal.

---

## 4. CORE — ignorar (Moodle 3.5 estándar, re-descargable)

- **Temas core:** `theme/boost` (398), `theme/bootstrapbase` (604), `theme/clean` (17), `theme/more` (13).
- **Blocks core (~40):** `activity_modules, activity_results, admin_bookmarks, badges, blog_menu, blog_recent, blog_tags, calendar_month, calendar_upcoming, comments, community, course_list, course_summary, feedback, globalsearch, glossary_random, grade_overview, html, login, lp, mentees, mnet_hosts, myoverview, myprofile, navigation, news_items, online_users, participants, private_files, quiz_results, recent_activity, rss_client, search_forums, section_links, selfcompletion, settings, site_main_menu, social_activities, tag_flickr, tag_youtube, tags`. También `blocks/tests` (11) y `blocks/classes` (2) = framework core, no plugins.
- **`lang/en/*`** (iso6392, mnet, portfolio, repository, enrol, my…) = ficheros de idioma core.
- **PHP core de raíz:** `brokenfile.php, config-dist.php, draftfile.php, file.php, githash.php, help.php, help_ajax.php, index.php, install.php, pluginfile.php, version.php`.

---

## 5. BASURA — ignorar

- **`blocks/rocketchat.disabled-20251207/`** (113) — plugin desactivado con sufijo de backup.
- **Backups fechados** `*.bak*`: `local/` (18, sobre todo `tsbranding/assets/h5p-brand.v3.js.bak-*`), `theme/lambda` (8 renderers/layout), `admin-panel/tools.json.bak_*` (4), `blocks/fundae/*.bak-*` (2), `blocks/completion_progress/*.bak_*` (2), `portal/*.bak_*` (2).
- **Scaffolding/diagnóstico de raíz:** `dashboard_test.php`, `coding_fundae_test.php`, `coding_test_python.php`, `coding_test_simple.php`, `modifyAndCreateAcuity_NUEVO.php`, `phpinfo.php`, `opcache_reset.php`, `info.php` (20 bytes, casi seguro `phpinfo()`).
- **Python temporales:** `__pycache__/`, `*.pyc`.

---

## 6. Propuesta de `.gitignore` (líneas a añadir)

El `.gitignore` ya usa la estrategia `/*` + excepciones `!/…`. Para des-ignorar carpetas hay que permitir la carpeta **y** su contenido, y luego re-excluir secretos/basura dentro.

```gitignore
# === SEGURO: carpetas custom a des-ignorar (contenido incluido) ===
!/local/
!/local/**
!/theme/
!/theme/tuspeaking/**
!/theme/lambda/**
!/blocks/
!/blocks/acuityblock/**
!/blocks/catalogue/**
!/blocks/fundae/**
!/blocks/fundae_inspector/**
!/blocks/messageteacher/**
!/blocks/configurable_reports/**
!/blocks/advnotifications/**
!/blocks/completion_progress/**
!/blocks/dedication/**
!/blocks/point_view/**
!/blocks/ranking/**
!/blocks/forum_aggregator/**

# === SEGURO: .php custom de raíz a des-ignorar ===
!/cancelbymail.php
!/dashboard.php
!/enviacorreo.php
!/eventupdater.php
!/formClass.php
!/formFeedback.php
!/fundae_calificaciones.php
!/fundae_grupos.php
!/mailsender.php
!/own_MeetingDataGet.php
!/reminder.php
!/tuspeakingvrlogin.php
!/visor.php
!/coding_fundae_dl.php

# === CON-SECRETOS: mantener FUERA (además de los ya listados) ===
/db_api.php
/monitor_zoom.php
/webhook_jotform_evaluaciones.php
/local/misclases/lib.php
/admin-panel/config.php
/theme/tuspeaking/.git/

# === CORE / re-descargable: NO des-ignorar (dejar que /* los cubra) ===
# theme/boost, theme/bootstrapbase, theme/clean, theme/more, lang/, blocks core
# (no se añade ninguna excepción !/… para ellos)

# === BASURA dentro de carpetas permitidas: re-excluir ===
*.bak*
*.pyc
__pycache__/
/blocks/rocketchat.disabled-20251207/
/theme/tuspeaking/.git/
```

> Cuidado con el orden: en gitignore, un patrón posterior gana. Las líneas de **re-exclusión de secretos/basura deben ir DESPUÉS** de las excepciones `!/…` de sus carpetas.
> Antes de confiar en `**`, validar con `git check-ignore -v <ruta>` sobre 4–5 ficheros de cada carpeta (`local/misclases/lib.php` DEBE seguir ignorado; `local/reminders/xxx.php` NO).

---

## 7. Plan de despliegue (cuando haya acceso al servidor)

> Objetivo: llevar lo SEGURO al repo desde el servidor de producción sin arrastrar secretos. **No** ejecutar commits en este paso de auditoría.

1. **En el servidor** (raíz Moodle, p.ej. `/mnt/moodle-data/moodle-code` o `tuspeaking-platform`), construir un tar SÓLO de lo SEGURO, excluyendo secretos y basura:
   ```bash
   cd <MOODLE_ROOT>
   tar czf /tmp/aula_seguro_$(date +%Y%m%d).tgz \
     --exclude='*.bak*' --exclude='__pycache__' --exclude='*.pyc' \
     --exclude='config.php' --exclude='admin-panel/config.php' \
     --exclude='acuityapi.php' --exclude='cancelAcuity.php' \
     --exclude='modifyAcuity.php' --exclude='modifyAndCreateAcuity.php' \
     --exclude='modifyAndCreateAcuity_NUEVO.php' --exclude='own_ZoomAPIToken.php' \
     --exclude='db_api.php' --exclude='monitor_zoom.php' \
     --exclude='tutorias_con_profesor.php' --exclude='webhook_jotform_evaluaciones.php' \
     --exclude='local/misclases/lib.php' --exclude='portal/rrhh.php' \
     --exclude='theme/tuspeaking/.git' --exclude='blocks/rocketchat.disabled-20251207' \
     --exclude='*_test.php' --exclude='phpinfo.php' --exclude='opcache_reset.php' \
     --exclude='dashboard_test.php' --exclude='info.php' \
     local/ theme/tuspeaking theme/lambda \
     blocks/acuityblock blocks/catalogue blocks/fundae blocks/fundae_inspector \
     blocks/messageteacher blocks/configurable_reports blocks/advnotifications \
     blocks/completion_progress blocks/dedication blocks/point_view blocks/ranking \
     blocks/forum_aggregator \
     cancelbymail.php dashboard.php enviacorreo.php eventupdater.php \
     formClass.php formFeedback.php fundae_calificaciones.php fundae_grupos.php \
     mailsender.php own_MeetingDataGet.php reminder.php tuspeakingvrlogin.php \
     visor.php coding_fundae_dl.php
   ```
2. **Traer el tar** al entorno de trabajo: `scp coreadmin@46.225.232.27:/tmp/aula_seguro_*.tgz .`
3. **Grep de seguridad ANTES de descomprimir sobre el repo** (guardarraíl innegociable):
   ```bash
   tar tzf aula_seguro_*.tgz            # revisar que NO aparezca ningún fichero de §3
   mkdir /tmp/aula_check && tar xzf aula_seguro_*.tgz -C /tmp/aula_check
   grep -RniE "TuspeakingFix2025|7727321b66b8210424f1d4d984584693|Success2026|MWXtMAaaSjCCzVuhPZ3KDg|apiKey\s*=\s*'[0-9a-f]{20}" /tmp/aula_check && echo "!! SECRETO — ABORTAR" || echo "OK sin secretos"
   ```
4. Sólo si el grep da **OK**: descomprimir sobre el clon del repo, aplicar las excepciones del §6 al `.gitignore`, y:
   ```bash
   git add .gitignore local theme/tuspeaking theme/lambda blocks/* <los .php de raíz>
   git status                          # revisar que no se cuele nada de §3
   git check-ignore -v local/misclases/lib.php admin-panel/config.php   # DEBEN salir ignorados
   # git commit  ← lo ejecuta el humano, no esta auditoría
   ```
5. `theme/tuspeaking/.git`: decidir antes del add — borrarlo para versionar plano, o convertir en submódulo.

---

## 8. Refactor pendiente (`secrets.php`) — agrupado

Externalizar las claves a un único `secrets.php` (fuera de git, cargado por `config.php`) o a `get_config()`:

**Grupo A — Password BD `TuspeakingFix2025!`** (mismo secreto repetido; el más urgente por estar hasta en git):
`config.php`, `admin-panel/config.php`, `db_api.php`, `monitor_zoom.php`, `tutorias_con_profesor.php`, `webhook_jotform_evaluaciones.php`, `local/misclases/lib.php`, `portal/rrhh.php`, **`misclases.php` (en git)**, y todos los `feedback/*`, `empresas/*`, `evaluaciones/*`, `reportes*/*` ya excluidos.

**Grupo B — Acuity API key `7727…4693`:**
`acuityapi.php`, `cancelAcuity.php`, `modifyAcuity.php`, `modifyAndCreateAcuity.php`, **`newAcuity.php` (en git)**.

**Grupo C — Zoom JWT (`key`+`secret`):**
`own_ZoomAPIToken.php` (migrar del JWT en duro al OAuth `get_config('zoom')` que ya usa la 2ª función del mismo fichero).

**Grupo D — Password demo `Success2026!`:**
`admin-panel/demo_manager.php` (en git) — mover a variable de entorno/secrets y rotar.

Tras cada grupo: rotar la credencial, sustituir el literal por lectura de `secrets.php`, y sólo entonces mover el fichero de CON-SECRETOS a SEGURO (des-ignorarlo).
