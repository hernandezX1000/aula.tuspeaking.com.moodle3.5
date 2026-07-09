# Fix — Ingesta de datos Zoom/Acuity (sync i3code) · 2026-07-09

## Contexto

El sync diario `admin/cli/i3code_download_zoomdata.php` (cron 04:05) actualiza la
asistencia y regenera el panel del alumno (`mdl_i3code_acuityZoom_informe`, que
Success lee). Se reportaba que "los datos no se actualizaban".

## Problema 1 — El informe fallaba al escribir (charset) · RESUELTO

**Causa:** las 5 tablas del flujo estaban en `latin1`:
`mdl_i3code_acuityZoom`, `mdl_i3code_acuityZoom_participants`,
`mdl_i3code_acuityZoom_informe`, `own_acuity`, `own_acuity_course`.
El script regenera el informe con `DELETE` + reinsert fila a fila; las filas con
caracteres no-latin1 (nombres chinos de Rubi, p. ej. 蒋晓艳) fallaban con
"Incorrect string value" → se saltaban → ese alumno se quedaba sin panel.

**Alcance histórico:** venía fallando ~12 filas/día desde 2023 (primer log
conservado), subiendo a 98-142/día en 2026 según se daban de alta más alumnos con
nombres no-latinos. Nunca hubo una corrida limpia en el histórico.

**Fix:** convertir las 5 tablas a utf8mb4 → ver
`docs/migrations/2026-07-09-charset-utf8mb4.sql`.

**Verificado:** corrida manual sin errores de informe; panel de alumnos con nombres
chinos (@rubi.cn) ya se genera; casos IberAssekuranz correctos.

## Problema 2 — Reservas nuevas no se importan (feeder own_acuity) · ABIERTO

Ver **TICKET #7**. El sync solo recorre lo que ya está en `own_acuity`
(`WHERE lastmodified >= hace 60 días`, líneas ~189-210): no inserta citas nuevas
de Acuity. Las reservas que un alumno **reprograma** después del alta pueden no
llegar a `own_acuity` y quedarse invisibles (caso real: Martin Specht,
IberAssekuranz — 6 clases dadas que no constaban; se insertaron a mano).

## Monitorización

`check_zoom_sync.sh` (cron 05:00) actualizado → `docs/ops/check_zoom_sync.sh`.
Ahora avisa a `hfernandez@` además de `soporte@`, y distingue:
- Errores del **informe** (críticos, rompen paneles) → avisa siempre.
- Errores de **procesado** (`Error guardando i3code_acuityZoom`) → residual conocido
  (~14/día, clases antiguas sueltas, no afectan paneles); avisa solo si supera
  `UMBRAL_PROC=30`.

## Residual conocido (bajo impacto)

~14 registros de clase antiguos fallan al INSERTAR en `mdl_i3code_acuityZoom`
(no es charset — topics cortos y sin 4-byte; no es longitud). Causa exacta sin
determinar (requiere el error MySQL real, oculto tras el mensaje genérico de
Moodle). No afecta a los paneles. Pendiente de root-cause si se prioriza.

## Notas de operación

- La ventana del sync es de **60 días** (solo refresca reservas modificadas en ese
  rango); las clases más antiguas no se reconsultan a Zoom.
- El informe se **borra y regenera entero** cada corrida → las correcciones manuales
  al informe se sobreescriben, pero se regeneran bien si la fuente `acuityZoom` está
  correcta.
- Riesgo de reversión de fixes "multi-slot fantasma": si se reprocesa el slot hijo
  (sin datos propios en Zoom) el sync lo vuelve a dejar en estado 3. Blindaje pendiente.
