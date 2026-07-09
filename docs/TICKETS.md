# Tickets de desarrollo — aula.tuspeaking.com

Pendientes técnicos. Marcar [x] al completar.

---

## TICKET #1 — Sacar credenciales de BD del código (ALTA)

**Estado:** [ ] Pendiente
**Prioridad:** Alta

**Problema:** La contraseña de la BD está escrita en duro (`new mysqli('localhost','moodle35','<password>','aulatuspeaking35')` o PDO equivalente) en ~51 ficheros propios. Además quedó en el historial de Git que se subió a GitHub (commit 7f48653), aunque el repo es privado.

**Ficheros afectados:** feedback/*, empresas/*, evaluaciones/*, portal/rrhh.php, reportes/admin.php, reportes_1to1/reporte_1to1.py, varios reportes_cesce/*, misclases.php, tutorias_con_profesor.php, webhook_jotform_evaluaciones.php. (Lista completa: `grep -rl "<password>" .` en las carpetas propias.)

**Tareas:**
- [ ] Crear `secrets.php` fuera del repo (ya está en .gitignore vía config.php pattern; verificar)
- [ ] Reemplazar la contraseña en duro por `require secrets.php` + constante, en los 51 ficheros
- [ ] Probar que las páginas afectadas siguen funcionando
- [ ] Una vez todo usa secrets.php, ROTAR la contraseña de BD en MySQL y actualizar secrets.php + config.php
- [ ] (Opcional) Limpiar el historial de Git con git filter-repo para borrar el rastro

**Nota:** Rotar la contraseña es lo que de verdad cierra el riesgo. Limpiar el historial sin rotar no sirve de mucho.

---

## TICKET #2 — Ficheros grandes en el repo (MEDIA)

**Estado:** [ ] Pendiente
**Prioridad:** Media

**Problema:** El repo pesa ~870 MB por los .pptx de `contenido/business_english/` (varios de 50-93 MB). GitHub avisa de que superan los 50 MB recomendados. Ralentiza clones y pushes.

**Opciones:**
- [ ] Opción A: configurar Git LFS para `contenido/**/*.pptx` (si se quieren versionar)
- [ ] Opción B: excluir `contenido/business_english/*.pptx` del repo y respaldarlos por otra vía (son contenido, no código)

---

## TICKET #3 — Token de GitHub expuesto (ALTA)

**Estado:** [ ] Pendiente
**Prioridad:** Alta

**Problema:** Un Personal Access Token con permiso `repo` se usó en texto plano y quedó expuesto. Sigue activo en ~/.git-credentials.

**Tareas:**
- [ ] Crear un token nuevo (sin compartirlo en ningún sitio)
- [ ] Actualizar ~/.git-credentials con el token nuevo
- [ ] Revocar el token expuesto en https://github.com/settings/tokens

---

## TICKET #4 — Contraseñas de BD en crontab (MEDIA)

**Estado:** [ ] Pendiente
**Prioridad:** Media (parte del #1)

**Problema:** El crontab tiene las contraseñas de las dos BD en texto plano
en los comandos mysqldump (BD principal y BD CESCE remota).

**Solución:** crear ~/.my.cnf con permisos 600:
    [client]
    user=moodle35
    password=<password>
y cambiar los mysqldump a `mysqldump --defaults-extra-file=~/.my.cnf ...`
sin la contraseña en la línea. Hacer junto con la rotación del #1.

---

## TICKET #5 — Vigilancia de disco y swap (BAJA)

**Estado:** [ ] Pendiente
**Prioridad:** Baja (vigilancia)

- Disco al 75% (junio 2026), creciendo. filedir = 247 GB de archivos de cursos.
  Cuando llegue ~90%, archivar/eliminar cursos antiguos DESDE Moodle (no a mano).
- Swap en 0 B: sin colchón si la RAM se llena. Valorar añadir swapfile.

---

## TICKET #6 — Charset utf8mb4 en el flujo de ingesta Zoom/Acuity (ALTA) — RESUELTO

**Estado:** [x] Resuelto (2026-07-09)
**Prioridad:** Alta

**Problema:** las 5 tablas del sync (`mdl_i3code_acuityZoom`, `_participants`,
`_informe`, `own_acuity`, `own_acuity_course`) estaban en `latin1`. El sync diario
fallaba al escribir filas con caracteres no-latin1 (nombres chinos de Rubi) y las
saltaba → esos alumnos sin panel. Venía fallando ~12-142 filas/día desde 2023.

**Fix aplicado:** `ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4` en las 5 tablas.
Migración: `docs/migrations/2026-07-09-charset-utf8mb4.sql`. Doc completo:
`docs/2026-07-09-fix-sync-ingesta.md`. Monitor actualizado: `docs/ops/check_zoom_sync.sh`.

**Residual (bajo impacto):** ~14 clases antiguas fallan al insertar (no charset, no
longitud; causa exacta sin determinar). No afecta paneles. Alerta con umbral.

---

## TICKET #7 — Feeder de own_acuity no importa reservas nuevas/reprogramadas (MEDIA)

**Estado:** [ ] Pendiente
**Prioridad:** Media

**Problema:** `i3code_download_zoomdata.php` solo procesa lo que ya está en
`own_acuity` (`WHERE lastmodified >= hace 60 días`); **no inserta citas nuevas** de
Acuity. Cuando un alumno crea/reprograma una reserva después del alta y esa cita no
llega a `own_acuity`, queda invisible para el sync → clases dadas que no constan.
Caso real: Martin Specht (IberAssekuranz), 6 clases dadas insertadas a mano el 2026-07-08.

**Diagnóstico (Acuity vs own_acuity):**
```
curl -s -u "<ACUITY_USER>:<ACUITY_KEY>" \
  "https://acuityscheduling.com/api/v1/appointments?email=<email>&minDate=<d>&maxDate=<d>&max=50"
-- comparar con: SELECT acuityid FROM own_acuity WHERE studentid=<uid>;
```

**Solución propuesta (job de reconciliación):**
- [ ] Localizar el proceso/webhook que rellena `own_acuity` y por qué no captura las reprogramaciones.
- [ ] Añadir un job (o ampliar el sync) que consulte la Acuity API por rango de fechas
  (por calendario/tipo) e **inserte en `own_acuity`** las citas que falten, para que el
  sync nocturno las recoja solo. Reusa `getAcuityAPI` y la conexión de BD de Moodle.
- [ ] Idempotente (no duplicar por `acuityid`), con `--dry-run` por defecto.

**Relacionado:** blindar el sync contra la reversión de slots "multi-slot fantasma"
(estado 3 al reprocesar el slot hijo sin datos propios en Zoom).
