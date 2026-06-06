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

## Flujo de trabajo Git

Repo: https://github.com/hernandezX1000/aula.tuspeaking.com.moodle3.5 (privado)
Rama: main · Credenciales guardadas en ~/.git-credentials (chmod 600, fuera del repo)

Guardar cambios:
    cd /home/aulatuspeaking/www/app/moodle/
    git add -A
    git commit -m "descripción del cambio"
    git push

Ver estado / historial:
    git status
    git log --oneline

ANTES de commitear ficheros nuevos, comprobar que no llevan secretos:
    git grep -l "<CONTRASEÑA_BD>" $(git diff --cached --name-only)
(debe salir vacío)

## Pendientes (mejoras futuras, no urgentes)

1. Refactorizar la contraseña de BD en duro a un secrets.php (51 ficheros).
2. .pptx grandes en contenido/business_english/ (>50MB): valorar Git LFS o excluir.
3. Rotar API key de Acuity y secret de Zoom (llevan tiempo en el servidor).
