# Auditoría de estructura del repo — aula Moodle 3.5 (producción)

**Fecha:** 2026-08-06
**Alcance:** repo `aula.tuspeaking.com.moodle3.5` (código custom del aula sobre Moodle 3.5.1).
**Veredicto:** **Funcional** (es el código que sirve producción) y con la parte "Moodle"
bien organizada, **pero** el repo mezcla código con contenido pesado y tiene una
arquitectura de crecimiento orgánico, no diseñada.

> Informe de referencia. NO modifica nada del repo; solo documenta el estado actual.

---

## 1. Qué es este repo

No es un checkout completo de Moodle: **no versiona el core** (faltan `version.php`,
`lib/`, `admin/`, etc.). Aplica la estrategia `.gitignore /*` + allow-list, de modo que
solo versiona el código propio de tuSpeaking **encima** de un Moodle 3.5.1 estándar.

Métricas medidas (6-ago-2026):

| Métrica | Valor |
|---|---|
| Ficheros versionados | 981 |
| Líneas de PHP custom (raíz + local + blocks propios) | ~26.400 |
| Tamaño working tree | ~1.9 GB |
| Tamaño `.git` | **916 MB** |
| Composición | 441 `.php`, 156 `.png`, **63 `.pptx`**, 45 `.js`, 41 `.md`, 24 `.css`, 14 `.py`, 14 `.csv`, 12 `.ttf`, 12 `.sh` |

---

## 2. Lo que está bien (coherente)

- **Plugins con convenciones Moodle correctas.** `local/` tiene 4 plugins bien formados
  (`navbarplus`, `reminders`, `resourcenotif`, `staticpage`), cada uno con `version.php`
  + `lib.php`. `blocks/` incluye bloques propios (`acuityblock`, `catalogue`, `fundae`,
  `fundae_inspector`) y de terceros (`configurable_reports`, `completion_progress`,
  `dedication`, `ranking`, `point_view`, `advnotifications`, `forum_aggregator`,
  `messageteacher`). `theme/` correcto (`tuspeaking`, `lambda`).
- **Sin secretos en duro en el código versionado.** El grep de credenciales conocidas
  sobre ficheros de código (excluyendo docs/*.md/example) no devuelve nada: los ficheros
  con credenciales están correctamente en el `.gitignore` (CON-SECRETOS). Solo aparecen
  hostnames (`mysql-5603.dinaserver.com`) en scripts de backup, que no son secretos.

---

## 3. Los 3 problemas reales

### 3.1. El repo es de *contenido*, no de código (1.9 GB; `.git` 916 MB)
Causa principal: `contenido/` ocupa **880 MB** en 63 PowerPoints de business English
(el mayor, `04_Business_communication.pptx`, 95 MB). Además, `_rescate_dinahosting_20260803/`
tiene **25 MB** de tarballs de rescate (`*.tgz`) commiteados. Eso es material didáctico y
backups, no aplicación. Un repo de solo código sería ~50–100 MB.
**Impacto:** clones lentos, histórico inflado, "code" y "content" mezclados.

### 3.2. Crecimiento orgánico, no diseño
Hay **31 scripts `.php` sueltos en la raíz** del webroot (`misclases.php`, `newAcuity.php`,
`reservaclase.php`, `reporte_*.php`, `coding_*.php`, etc.) y ~20 carpetas custom de primer
nivel (`contenido`, `reportes_cesce`, `reportes_1to1`, `reportes_evaluaciones`,
`evaluaciones`, `empresas`, `feedback`, `portal`, `timelog`, `faq`, `tus-tools`,
`tus-content`, `shared_content`, `brand`, `_tszoom`, `plantillas_email`...). Funciona, pero
las responsabilidades (Acuity, Zoom, FUNDAE, informes, feedback) están dispersas en la raíz
en vez de consolidadas en un plugin `local/tuspeaking`.

### 3.3. El core no está versionado
No se puede reconstruir el sitio solo con este repo (hace falta el core 3.5.1 aparte), y
cualquier **parche hecho a mano sobre ficheros del core de Moodle no queda registrado**. Es
el patrón que provocó el borrado de código del 1-ago-2026.

---

## 4. Recomendaciones (por impacto, sin ejecutar ahora)

1. **Sacar el peso de git** (alto impacto, bajo riesgo): mover `contenido/*.pptx` a object
   storage o `moodledata`, y retirar los `.tgz` de rescate (guardándolos offsite). Para que
   el `.git` adelgace de verdad hay que **reescribir histórico** (BFG / git-filter-repo);
   conviene unirlo al ticket que ya planea limpiar el histórico
   (`docs/tickets/TICKET-SEGURIDAD-rotar-credenciales.md`).
2. **Registrar los parches al core** de Moodle en un `patches/` (o lista versionada) para
   que una reinstalación del core no los pierda. Mitiga el riesgo recurrente de wipe
   (ver `docs/2026-08-06-incidente-*` y el incidente del 1-ago).
3. **Consolidar los 31 scripts de raíz** en un plugin `local/tuspeaking` (opcional, más
   adelante, con cuidado: es refactor de superficie amplia). Baja urgencia.

---

## 5. Limitación de esta auditoría

No se pudo ejecutar `php -l` en el entorno de análisis (sin PHP), así que "funcional" se
sostiene por evidencia **indirecta**: es el código en producción y la CI (`ci-verify.yml`)
ya lintea los `.php` que cambian en cada rama. No es un linteo completo de los 441 ficheros.
