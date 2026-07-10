# CLAUDE.md — Referencia de infraestructura (solo consulta)

> Este fichero NO contiene secretos. Las contraseñas/tokens viven fuera del repo.
> Sirve como mapa de la infraestructura para consulta rápida.

## Servidor

- Host: vl24689.dinaserver.com (Dinaserver)
- Acceso: SSH como usuario `aulatuspeaking`
- SO: Debian GNU/Linux 11 (bullseye)
- Raíz web Moodle: /home/aulatuspeaking/www/app/moodle/
  (ruta física real: /home/aulatuspeaking/.ftp-users/moodle/)
- URL pública: https://aula.tuspeaking.com
- Moodle 3.5 · PHP 7.x · MySQL · OPcache restringido (purge_caches avisa, no es error)

## Base de datos

- Definida en: config.php (variables $CFG->dbhost/dbname/dbuser/dbpass) — NO versionado
- Nombre BD principal: aulatuspeaking35 · prefijo de tablas: mdl_
- Credenciales: ver config.php en el servidor (nunca en este repo)
- IMPORTANTE: muchos scripts propios tienen la contraseña en duro (pendiente de
  refactorizar a un secrets.php incluido). Estos ficheros están excluidos del repo
  vía .gitignore. Si se cambia la contraseña de BD, hay que actualizarlos.

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

4. **Desplegar** (server):

       cd /home/aulatuspeaking/www/app/moodle && git pull
       bash tus-tools/autocorrect/deploy.sh     # copia scripts a ~/scripts/ con backup

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
