# Pipeline pruebas + refactor secrets.php — ESTADO Y CÓMO RETOMAR

**Fecha:** 2026-08-06
**Decisión:** PARADO a propósito. NO se aplica el refactor de secrets.php de momento
(el repo es privado → urgencia baja) y NO se amplía el servidor (coste). Todo el
código queda guardado en git para retomar sin rehacer nada.

---

## Qué quedó HECHO (y dónde)

### Repo aula (`aula.tuspeaking.com.moodle3.5`) — rama `agent/secrets-php`
- **Capa 1 de CI en VERDE** (`.github/workflows/ci-verify.yml`): en cada push a
  `agent/**` o `dev`, GitHub valida sin necesidad de Moodle vivo:
  1. `php -l` de los `.php` cambiados.
  2. Guardrail de secretos: ningún literal de credencial en el CÓDIGO
     (excluye `docs/`, `*.md`, `secrets.php.example` y `.github/` — este último
     porque el propio workflow contiene los literales como patrón del grep).
  3. `.gitignore` correcto: `secrets.php` ignorado, `.example` versionable.
  4. Los ficheros refactorizados usan constantes/`$CFG`, no literales.
  - Último run verde: commit `cd041da`.
- **5 ficheros refactorizados** (0 literales de secreto), leen de `secrets.php`/`$CFG`:
  `misclases.php`, `newAcuity.php`, `admin-panel/demo_manager.php`,
  `empresas/acuity_zoom.php`, `admin/cli/i3code_download_zoomdata.php`.
- **`secrets.php.example`** con placeholders `CHANGE_ME` (ACUITY_USER_ID,
  ACUITY_API_KEY, DEMO_DEFAULT_PASSWORD). `secrets.php` real queda ignorado.
- **Gate de despliegue** `docs/ops/verify-secrets-deploy.sh`: PRECHECK → BACKUP →
  DEPLOY → AUTOAUDITORÍA → **ROLLBACK automático** si algo falla en el smoke.
- Tickets: `docs/tickets/TICKET-SECRETS-PHP-refactor.md`,
  `TICKET-SEGURIDAD-rotar-credenciales.md`.

### Repo LMS (`tuspeaking-lms`) — `services/staging-disposable/`
- Staging DESECHABLE aislado (nombres `-staging`, red/volúmenes/puertos propios):
  `docker-compose.staging.yml`, `config-staging-disposable.php` (noemailever + creds
  dummy), `setup-staging.sh`, `teardown-staging.sh`, `.env.staging.example`, `README.md`.
- Guardrail: `setup-staging.sh` ABORTA si la ruta de código no contiene `-staging`.

---

## Por qué se PARÓ (diagnóstico del servidor, 6-ago)

Servidor Hetzner **CPX22** (46.225.232.27): 2 vCPU / **4 GB RAM** / 80 GB disco local
+ 550 GB en 2 volúmenes. Ya corre 8 contenedores (aula prod, CESCE ×2, 3 WordPress,
wordpress-db). En el momento del diagnóstico: **194 MB RAM libres**, swap 1.4 GB usado.

- **No hay staging aislado corriendo.** El `services/moodle35-staging/` (aula-test.
  tuspeaking.com) fue el montaje de la MIGRACIÓN pre-cutover: su compose declara
  `container_name: moodle35-app`/`moodle35-db` y monta `/mnt/moodle-data/moodle-code`,
  `moodledata`, `mysql-data`, red `moodle35-network`, puertos 8080/3307/3308 — es decir,
  **los MISMOS recursos que hoy son PRODUCCIÓN**. Tras el cutover, ese compose = prod.
  ⚠️ Desplegar en "aula-test" = desplegar en PRODUCCIÓN. NO usarlo como staging.
- Levantar el staging desechable en el mismo box es **arriesgado por RAM**: un 2º
  Moodle+MariaDB (~0.5–1 GB) con 194 MB libres podría disparar el OOM killer y matar
  un contenedor de PROD.
- **Conflicto de puerto:** el compose de staging publica la BD en `127.0.0.1:3309`,
  pero `wordpress-db` YA usa el 3309. Al retomar: cambiar a 3310 o no publicar el puerto
  (el app llega a su BD por la red interna). El puerto 8081 del app SÍ está libre.
- **Falta backup de código:** en `/mnt/moodle-data/backups/` solo hay `db_aula_*.sql.gz`
  (1.3 GB, diarios). No hay `code_moodle_*.tar.gz`. Al retomar: sourcear el código desde
  el vivo `/mnt/moodle-data/moodle-code` (cp -a) o generar un tar primero.
- **Coste de ampliar:** el €7.99/mes es precio antiguo (grandfathered). Tarifa actual
  (jun-2026): CPX22 €19.49, CPX32 (8 GB) €35.49. Redimensionar probablemente re-tarifica.

---

## Cómo RETOMAR (3 caminos, con coste)

1. **Canary guardado en prod (gratis, para este refactor de 5 ficheros):**
   - Crear `secrets.php` real en el server desde `secrets.php.example`.
   - Dejar los 5 ficheros de `agent/secrets-php` en `/tmp/secrets_deploy/`.
   - `bash docs/ops/verify-secrets-deploy.sh` → backup + deploy + autoauditoría +
     rollback automático. Sin RAM extra.

2. **Servidor de staging por horas (aislamiento total, ~céntimos/sesión):**
   - Crear un Hetzner temporal (p.ej. CPX32 8 GB, ~€0.05/h), instalar Docker.
   - Copiar `services/staging-disposable/`, `cp .env.staging.example .env.staging`
     (passwords NUEVAS), restaurar `db_aula_*.sql.gz`, `./setup-staging.sh`.
   - Smoke (`03-verify-staging.sh` / gate apuntado a staging), luego BORRAR el server.
   - Antes: arreglar puerto 3309→3310 y el source del código (ver arriba).

3. **Ampliar prod a CPX32 (8 GB, ~€35/mes fijos):** staging siempre disponible en el
   mismo box, pero encarece prod y pierde el precio viejo. Menos recomendable.

---

## Rotación de credenciales (pendiente aparte)

Las claves reales siguen en el histórico de git (`TuspeakingFix2025!`, key Acuity,
`Success2026!`). Al aplicar el refactor de verdad, rotar y limpiar histórico según
`docs/tickets/TICKET-SEGURIDAD-rotar-credenciales.md`. El repo es privado, por eso la
urgencia es baja hoy.
