# Incidente 2026-08-07 — Caída de aula.tuspeaking.com por ejecutar PHP de Moodle como root

- **Severidad:** crítica. Portal completo caído, **login incluido**.
- **Duración:** ~15 min de indisponibilidad total, ~45 min desde el primer síntoma
  hasta la causa raíz.
- **Estado:** resuelto. Blindaje pendiente (ver *Acciones*).
- **Causado por:** una sesión de soporte (asistente IA) que **no leyó este repo**
  antes de operar sobre el servidor.

---

## 1. Síntoma

En todas las páginas de `aula.tuspeaking.com`, incluida `/login/index.php`:

```
Detectado un error de codificación, debe ser corregido por un programador:
File store path does not exist and can not be created.
```

Origen en el código: `cache/stores/file/lib.php:643`, dentro de
`cachestore_file::ensure_path_exists()`, cuando `make_writable_directory()` falla.

## 2. Causa raíz

Ficheros propiedad de **root** dentro de
`/mnt/moodle-data/moodledata/cache/cachestore_file/`.

Apache corre como `www-data` (uid 33). No puede escribir ni recrear esos
directorios → `cachestore_file` lanza la excepción en **cada** petición.

### Cómo llegaron ahí los ficheros de root

Durante el diagnóstico de una incidencia de soporte se usó repetidamente este
comando, inventado sobre la marcha, para leer la contraseña de la BD:

```bash
# ☠️ COMANDO QUE PROVOCÓ LA CAÍDA — NO USAR JAMÁS
docker exec moodle35-app php -r 'define("CLI_SCRIPT",true); require "/var/www/html/app/moodle/config.php"; echo $CFG->dbpass;'
```

Dos errores encadenados:

1. **Sin `-u www-data`** → el proceso corre como **root** dentro del contenedor.
2. **`require config.php`** → `config.php` termina en
   `require_once(__DIR__.'/lib/setup.php')`, así que ese `require` **arranca
   Moodle entero**. Y Moodle, al arrancar, **escribe cachés** en `moodledata`
   con el usuario del proceso.

Resultado: cada ejecución sembró ficheros de root en
`moodledata/cache/cachestore_file/default_application/…` (core_config,
core_databasemeta, core_htmlpurifier, core_coursemodinfo, simplepie…).

Mientras las cachés existentes seguían siendo válidas, **no se notó nada**.

### El detonante (que no es la causa)

Un `purge_caches.php` posterior —ese sí ejecutado correctamente con
`-u www-data`— borró lo que `www-data` podía borrar, **dejó los ficheros de root
intactos**, y al intentar recrear los directorios reventó.

> El purge no causó la caída. La causa se plantó horas antes, con el primer
> `docker exec` sin `-u www-data`.

## 3. Lo que este repo ya decía (y no se leyó)

Todo lo necesario para no cometer el fallo **estaba documentado en `CLAUDE.md`**:

| Línea | Lo que decía | Lo que se hizo |
|---|---|---|
| `CLAUDE.md:25` | `docker exec -u www-data moodle35-app php … purge_caches.php` | Se ignoró el patrón `-u www-data` para otros comandos PHP |
| `CLAUDE.md:65` | **`docker exec -it moodle35-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" aulatuspeaking35'`** — consola **sin teclear contraseña** | Se inventó el `php -r 'require config.php'` en su lugar |
| `CLAUDE.md:61-62` | La BD **solo escucha por TCP**; `host='localhost'` da `1698 Access denied` | Se perdió tiempo con un `ERROR 1045 … 'moodle35'@'localhost'` |
| `CLAUDE.md:46` | El servidor va en **UTC** | Se dedujo a mano desde los datos de Zoom |
| `CLAUDE.md:14,43` | moodledata y código son de `www-data`/**33** | No se comprobó la propiedad tras escribir |
| `CLAUDE.md:67` / `SEC-2` | Ya existía ticket abierto por credenciales en duro | Se abrió un ticket duplicado fuera del repo |

**Conclusión: el incidente era 100% evitable leyendo `CLAUDE.md` (5 minutos).**
El comando seguro estaba escrito, literalmente, en la línea 65.

## 4. Diagnósticos fallidos (para no repetirlos)

Cuatro hipótesis erróneas antes de acertar. Se registran porque el coste real
del incidente estuvo aquí, no en el arreglo:

| # | Hipótesis | Por qué se cayó |
|---|---|---|
| 1 | "Son permisos, falta `chown`" | El `ls -la` mostraba todo `www-data` en el primer nivel |
| 2 | "Disco o inodos llenos" | 79% de disco, 2% de inodos |
| 3 | "`moodle35-app` es staging, no producción" | El banner de arranque dice *"Moodle 3.5.1 Staging — aula-test.tuspeaking.com"* porque la **imagen** se llama `moodle35-staging-moodle`. Pero es el único contenedor de aula: **es producción** |
| 4 | **"No hay ficheros de root"** | Se comprobó con `find … -maxdepth 1`, que **solo mira el primer nivel**. La basura estaba en subdirectorios. **Este fue el error caro**: descartó la causa real durante ~20 minutos |

La causa real solo apareció cuando un `rm -rf` como `www-data` devolvió
`Permission denied` fichero a fichero dentro de `cache/cachestore_file/`.

## 5. Resolución aplicada

```bash
sudo rm -rf /mnt/moodle-data/moodledata/cache/* /mnt/moodle-data/moodledata/localcache/*
sudo chown -R 33:33 /mnt/moodle-data/moodledata/cache \
                    /mnt/moodle-data/moodledata/localcache \
                    /mnt/moodle-data/moodledata/temp \
                    /mnt/moodle-data/moodledata/muc \
                    /mnt/moodle-data/moodledata/sessions
docker restart moodle35-app
curl -sI -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/login/index.php   # → 303 ✅
```

Borrar `cache/` y `localcache/` es seguro: son ficheros derivados y Moodle los
regenera. La primera carga va lenta.

## 6. Reglas que salen de aquí (obligatorias)

### 6.1 ☠️ Todo PHP de Moodle va con `-u www-data`

```bash
docker exec -u www-data moodle35-app php …   # ✅ SIEMPRE
docker exec moodle35-app php …               # ☠️ NUNCA
```

Sin excepciones. Ni "solo para leer un valor". Ni `php -r`. Ni `php -v` sobre el
árbol de Moodle si va a cargar `config.php`.

### 6.2 Nunca `require config.php` para leer un valor

`config.php` arranca Moodle. Para leer credenciales, **leer el fichero como
texto** o —mejor— usar lo que ya documenta `CLAUDE.md:65`:

```bash
docker exec -it moodle35-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" aulatuspeaking35'
```

### 6.3 Comprobar propiedad tras cualquier escritura en moodledata

```bash
sudo find /mnt/moodle-data/moodledata ! -uid 33 -printf '%u %p\n' | head -30
# si devuelve algo → sudo chown -R 33:33 /mnt/moodle-data/moodledata
```

⚠️ **Sin `-maxdepth`.** Con `-maxdepth 1` no ves nada y descartas la causa real.

### 6.4 Leer `CLAUDE.md` del repo antes de tocar el servidor

No es opcional. Ver §3: estaba todo escrito.

## 7. Acciones de blindaje (pendientes)

Registradas en `docs/BACKLOG.md`:

| ID | Acción |
|---|---|
| `SEC-6` | `USER www-data` en el Dockerfile (o wrapper `aula-php`) para que ejecutar PHP como root sea **imposible**, no solo desaconsejado |
| `MON-5` | Chequeo periódico `find /mnt/moodle-data/moodledata ! -uid 33` con alerta en el digest |
| `MIG-4` | Renombrar la imagen `moodle35-staging-moodle` o cambiar su banner: dice "Staging / aula-test" siendo producción |
| `COMP-4` | Bloque roto del Área personal: `blocks/html/dashboard_welcome.php not found` (referer `/my/`), presente **antes** de esta caída |

Relacionado y ya abierto: **`SEC-2`** (credenciales de BD en duro). Cerrarlo
elimina el motivo por el que alguien intentaría leer `dbpass` con un `require`.

## 8. Cronología

| Hora (CEST) | Hecho |
|---|---|
| ~09:5x | Primer `docker exec … php -r 'require config.php'` sin `-u www-data`. Se siembran ficheros de root en la caché |
| 09:36 | (ruido no relacionado) bot escaneando webshells — ver `docs/incidents/2026-08-07-bot-escaneo-webshells.md` |
| 09:45 | `blocks/html/dashboard_welcome.php not found` en `/my/` — **anterior** a la caída, problema distinto |
| ~10:28 | `purge_caches.php` (correcto, con `-u www-data`). Detonante |
| ~10:30 | Portal caído. `File store path does not exist and can not be created` |
| 10:30–10:50 | Cuatro diagnósticos fallidos (§4) |
| ~10:52 | `Permission denied` en `rm` revela los ficheros de root |
| ~10:55 | `rm` + `chown -R 33:33` + `docker restart` → `HTTP 303`. Servicio restablecido |

## 9. Contexto: qué se estaba haciendo

Incidencia de soporte de **Sonia Funes** (Hyatt / Inclusive Collection, userid
4827, curso 4046): no-show del 06/08 y bono bloqueado. Esa incidencia **se
resolvió correctamente** y es independiente de esta caída. Detalle en
`docs/incidents/2026-08-07-sonia-funes-no-show.md`.
