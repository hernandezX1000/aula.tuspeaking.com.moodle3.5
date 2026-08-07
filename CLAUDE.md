# CLAUDE.md — Referencia de infraestructura (solo consulta)

> Este fichero NO contiene secretos. Las contraseñas/tokens viven fuera del repo.
> Sirve como mapa de la infraestructura para consulta rápida.

---

## ⚠️ MIGRADO A HETZNER (26-jul-2026) — LEER ESTO PRIMERO

**aula.tuspeaking.com ya NO está en Dinahosting.** Se migró a Hetzner el 26-jul-2026 (cutover completo). El Dinaserver se cancela el **4-ago-2026**. Todo lo que hay debajo de este bloque que mencione `vl24689.dinaserver.com` o rutas `/home/aulatuspeaking/...` es **HISTÓRICO** — la realidad actual es esta:

- **Servidor:** Hetzner `tuspeaking-lms`, IP **46.225.232.27**, SSH usuario **`coreadmin`** (Ubuntu 24.04). ⚠️ NO confundir con el Hetzner de la plataforma nueva (`178.104.12.31`, learn/teach/success).
- **aula corre en Docker:** contenedor **`moodle35-app`** (Apache 2.4.38 / PHP 7.3.33). Monta:
  - Código: `/mnt/moodle-data/moodle-code` → `/var/www/html/app/moodle` (dueño `www-data`/33).
  - moodledata: `/mnt/moodle-data/moodledata` → `/var/moodledata`.
  - config: `config-staging.php` (vive en el repo **`tuspeaking-lms`**, es infra) → `config.php`.
- **wwwroot** = `https://aula.tuspeaking.com` (raíz, sin subpath — ojo: en el Hetzner las rutas `/app/moodle/...` hardcodeadas dan 404, se sirve en raíz).
- **BD:** contenedor `moodle35-db` (MariaDB, `127.0.0.1:3307`), BD `aulatuspeaking35`, user `moodle35`.
- **Scripts Python** (autocorrector/digest/quiz): runtime en **`/home/coreadmin/scripts/`**; crons en el crontab de `coreadmin`.

### Flujo de despliegue NUEVO (sustituye al de Dina de más abajo)
El código sigue viniendo de **este repo** (dev→main, igual que antes). Solo cambia el destino:
1. Local (Mac): desarrollar en `dev` → probar → merge a `main` → push.
2. En el Hetzner: `cd /home/coreadmin/aula-repo && git pull` → `bash deploy-aula.sh` (sincroniza el código custom al volumen del contenedor con `chown 33:33`, copia los scripts Python a `/home/coreadmin/scripts`, y purga cachés).
- Purga manual: `docker exec -u www-data moodle35-app php /var/www/html/app/moodle/admin/cli/purge_caches.php`.
- ⚠️ **PENDIENTE de activar en el server:** clonar este repo en `/home/coreadmin/aula-repo` y probar `deploy-aula.sh` (aún NO ejecutado en producción — ver el script en la raíz del repo).

### Repos (roles — no mezclar)
- **Este repo (`aula.tuspeaking.com.moodle3.5`)** = CÓDIGO de la app (custom Moodle + scripts). Core gitignorado. Rama `dev`→`main`.
- **`tuspeaking-lms`** = INFRAESTRUCTURA del Hetzner (Docker, vhosts, backups, crons de root). Rama `main`. Ver su `CLAUDE.md`.
- **`cesce`** = app de ejercicios LTI de CESCE, el OTRO Moodle del mismo host. Rama `master` = producción.
- **`tuspeaking-platform`** = plataforma NUEVA (learn/teach/success), OTRO Hetzner — no tiene que ver con aula.

Criterio: **¿lo ejecuta el sistema operativo o un cron de root? → `tuspeaking-lms`.
¿Lo ejecuta la aplicación? → el repo de esa app.**

---

## Servidor (VIGENTE — verificado 07-ago-2026)

- Host: Hetzner **`tuspeaking-lms`** · **46.225.232.27** · SSH `coreadmin` · Ubuntu 24.04
- App en Docker: contenedor **`moodle35-app`** (Apache 2.4.38 / PHP 7.3.33)
- Código: `/mnt/moodle-data/moodle-code` → `/var/www/html/app/moodle` (dueño `www-data`/33)
- moodledata: `/mnt/moodle-data/moodledata` → `/var/moodledata`
- URL pública: https://aula.tuspeaking.com (servido en **raíz**, sin subpath)
- ⚠️ El servidor va en **UTC**: los crons "de las 3:00" corren a las 5:00 hora de España.
- Scripts Python: `/home/coreadmin/scripts/` · logs en `/home/coreadmin/cron_*.log`
- Comparte host con CESCE (`moodle35-app-cesce` / `moodle35-db-cesce`, volumen distinto).

<details><summary>HISTÓRICO — Dinahosting (cancelado 4-ago-2026)</summary>

Host `vl24689.dinaserver.com`, usuario `aulatuspeaking`, Debian 11, raíz web
`/home/aulatuspeaking/www/app/moodle/` (física: `.ftp-users/moodle/`). Cualquier
documento del repo con estas rutas es histórico.
</details>

## Base de datos

- Contenedor **`moodle35-db`** (MariaDB 10.5) · **`127.0.0.1:3307`** · BD `aulatuspeaking35`
  · prefijo `mdl_` · usuario app `moodle35`
- ⚠️ Solo escucha por **TCP**. Un cliente con `host='localhost'` va por socket Unix y
  falla con `1698 Access denied`. Usar siempre `127.0.0.1:3307`.
- ⚠️ NO confundir con `moodle35-db-cesce` (`:3308`), que es CESCE.
- Consola sin teclear contraseña:
  `docker exec -it moodle35-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" aulatuspeaking35'`
- Credenciales de la app: `config.php` en el servidor (nunca en este repo)
- IMPORTANTE: ~51 ficheros propios llevan la contraseña en duro (SEC-2 del BACKLOG).
  Están excluidos vía `.gitignore`. Si se cambia la contraseña, hay que actualizarlos.

## Integraciones externas (claves NO en repo)

- Acuity Scheduling: owner 15680788. API key en los ficheros acuity*.php (excluidos).
- Zoom: credenciales OAuth vía variables de entorno (getenv ZOOM_CLIENT_ID/SECRET/ACCOUNT_ID)
  en _tszoom/. El JWT antiguo está en own_ZoomAPIToken.php (excluido).

## Código propio (versionado en este repo)

Carpetas: _tszoom, brand, feedback, reportes_cesce, reportes_1to1,
reportes_evaluaciones, reportes, admin-panel, empresas, evaluaciones, portal,
plantillas_email, timelog, faq, contenido, tus-content, tus-tools, shared_content, api

Ficheros raíz: acuity*.php/js/css, own_*, coding_*, reporte_*.php, courseacuity.php,
askddbb.php, sso_redirect.php, webhook_jotform_evaluaciones.php, tuspeaking-admin.css

### Excluido del repo (ver .gitignore)
- config.php y todos los .bak
- Core de Moodle (mod/, lib/, blocks/, auth/, etc.)
- ~51 ficheros con contraseña de BD en duro (feedback/*, empresas/*, varios reportes_cesce/*)
- *.pyc, __pycache__/, basura de scripts (nombres con comas/corchetes)

## Componente _tszoom (botón Reservas / modal Acuity)

- hostbutton.js: añade botón "Iniciar clase (host)" a enlaces de Zoom.
  Históricamente lanzaba TypeError en MutationObserver.observe(document.body)
  al correr desde <head>; corregido para esperar al body. Versión ?v=14.
- acuitymodal.js: intercepta clic en a.acuity-embed-button y abre la URL en un
  modal con iframe (evita el CORB del embed oficial de Acuity). Versión ?v=2.
- Ambos se cargan desde el campo additionalhtmlhead (config de Moodle en BD).

## Cachés Moodle

Purgar tras cambios de config:
    php /home/aulatuspeaking/www/app/moodle/admin/cli/purge_caches.php
(El warning de Zend OPcache es inofensivo.)

## Flujo de trabajo (INVIOLABLE — no saltárselo sin permiso expreso de Hansel)

Como en tuspeaking-platform: se desarrolla en **local** sobre `dev`, se prueba, se mergea
a `main` y se despliega al server. **Nunca editar producción a mano** (nada de nano/sed en
el server) salvo hotfix acordado. Esto garantiza que todo funcione siempre igual.

Repo: https://github.com/hernandezX1000/aula.tuspeaking.com.moodle3.5 (privado)
Ramas: **`dev`** (desarrollo) → **`main`** (producción). Credenciales en `~/.git-credentials` (600).

1. **Desarrollo** (local, Mac) — SIEMPRE sobre `dev`:

       cd ~/Proyectos/aula.tuspeaking.com.moodle3.5
       git checkout dev
       # ...editar ficheros...
       git add -A && git commit -m "..." && git push

2. **Prueba** (server, sin tocar producción) — traer solo el fichero de `dev` y probar en seco:

       git fetch origin
       git show origin/dev:ruta/al/script > /tmp/script_dev.py
       python3 /tmp/script_dev.py --dry-run

3. **Publicar** — merge `dev→main`:

       git checkout main && git merge dev && git push

4. **Desplegar** (Hetzner) — hoy es `scp` desde el Mac; NO hay despliegue automático:

       scp tus-tools/autocorrect/hansel_*.py coreadmin@46.225.232.27:/home/coreadmin/scripts/

   ⚠️ **Antes de desplegar un script, comprobar que producción no va por delante**
   (`md5sum` en el server vs repo). El 07-ago-2026 tres scripts se habían editado
   directamente en el servidor: desplegar el repo encima habría destruido esas mejoras.
   Si prod va por delante: rescatar primero (`scp` inverso), commitear, y luego desplegar.

   Los ficheros con secretos (config.php, scripts con API keys) están en `.gitignore` y se
   despliegan a mano.

ANTES de commitear, comprobar que no hay secretos:

       git grep -l "<CONTRASEÑA_BD>" $(git diff --cached --name-only)   # debe salir vacío

**Regla de oro:** rama `dev` → `main`, nunca editar el server a pelo. Saltarse esto requiere
permiso expreso de Hansel.

## Documentación y planificación (docs/)

Un sitio para cada cosa (detalle en `docs/README.md`):

- **docs/BACKLOG.md** — FUENTE ÚNICA de tareas/bugs/desarrollos abiertos (tabla priorizada, IDs `AREA-nn`). Aquí se mira qué hacer y aquí se cierra.
- **docs/ROADMAP.md** — desarrollos previstos por tema (el "qué construir").
- **docs/sessions/YYYY-MM-DD.md** — bitácora por sesión (qué se hizo / se decidió / quedó abierto).
- **docs/tickets/ · docs/incidents/ · docs/reference/** — detalle largo, referenciado desde BACKLOG.

Cómo se trabaja: se planifica en **ROADMAP** → se prioriza en **BACKLOG** → se ejecuta y se
registra en **sessions/**. Los dos `TICKETS.md` antiguos son histórico; la fuente viva es `BACKLOG.md`.

## Pendientes

Ver **docs/BACKLOG.md** (fuente única). No mantener listas de pendientes sueltas en este fichero.
