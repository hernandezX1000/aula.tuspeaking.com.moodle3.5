# Runbook — Conexión a la BD del aula (producción)

Cómo entrar a la base de datos de `aula.tuspeaking.com`. **Este es el método bueno.**
No buscar el `.env` a mano ni teclear contraseñas: se saca del propio contenedor.

## Dónde vive (desde ago-2026)

- **Servidor:** Hetzner `46.225.232.27`, usuario `coreadmin`.
- **Contenedores Docker:**
  - `moodle35-db` — MariaDB 10.5. BD **`aulatuspeaking35`** (prefijo `mdl_`). Usuario app `moodle35`.
  - `moodle35-app` — Moodle 3.5 (PHP). Código en `/var/www/html/app/moodle`.
- **Host de BD:** puerto `3307` en el host mapea al contenedor `moodle35-db`.

## Conectar (copia y pega)

```bash
ssh coreadmin@46.225.232.27

# Entra directo en MySQL como root, sin teclear contraseña
# (la lee de la variable de entorno del propio contenedor):
docker exec -it moodle35-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" aulatuspeaking35'
```

Prompt esperado: `MariaDB [aulatuspeaking35]>`. Ya estás dentro.

### Si necesitas la contraseña del usuario `moodle35` (p. ej. para un script)

```bash
docker exec moodle35-db printenv MYSQL_PASSWORD
```

### Una línea para scripts (query directa, sin sesión interactiva)

```bash
docker exec moodle35-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" aulatuspeaking35 -e "SELECT 1;"'
```

## Notas / trampas conocidas

- ⚠️ **La carpeta en el servidor se llama `tuspeaking-platform`, NO `tuspeaking-lms`**, por histórico
  (el mount del contenedor depende de esa ruta). Por eso `~/tuspeaking-lms/...` no existe en el server.
  El `.env` real (que define `DB_MOODLE_PASSWORD`, `DB_ROOT_PASSWORD`) está en
  `~/tuspeaking-platform/services/moodle35-staging/.env`. Pero **no hace falta abrirlo**: usa el método de arriba.
- No confundir con `moodle35-db-cesce` (contenedor de CESCE, BD `aulatuspeaking35cesce`).
- `config.php` del aula se monta **read-only** desde `config-staging.php` del repo de infra; no editarlo en caliente.

## Relanzar la ingesta Zoom (asistencia)

```bash
docker exec -u www-data moodle35-app php /var/www/html/app/moodle/admin/cli/i3code_download_zoomdata.php
```

Ver `docs/runbooks/asistencia-zoom.md` para estados de asistencia y correcciones manuales.
