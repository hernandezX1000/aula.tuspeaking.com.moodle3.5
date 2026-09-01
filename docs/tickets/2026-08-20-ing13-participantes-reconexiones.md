# ING-13 — La ingesta cuenta las reconexiones del profesor como participantes distintos

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Prioridad:** Media · **Estado:** 🔴 abierto
**Fecha:** 2026-08-20 · **Fichero:** `admin/cli/i3code_download_zoomdata.php`

---

## 1. El caso que lo destapó

Clase de **Berta Rodríguez (5868)** del 10/08/2026 11:30, fila `108691`, meeting `82469180787`.

La BD decía: `zoom_clasecompletada = 3` · `zoom_duration = 18` · **`zoom_participants = 5`**.

Zoom decía (horas UTC, +2 en España):

```
Dehlia Williams  09:30:58 -> 09:34:53   235 s  in_meeting
Dehlia Williams  09:33:00 -> 09:36:44   224 s  in_meeting
Dehlia Williams  09:35:30 -> 09:37:43   133 s  in_meeting
Dehlia Williams  09:37:44 -> 09:41:35   231 s  in_meeting
Dehlia Williams  09:39:43 -> 09:48:21   518 s  in_meeting
```

**Los "5 participantes" son 5 sesiones de la MISMA persona**, con los tramos solapándose entre
sí: la profesora se reconectaba sin que la sesión anterior hubiera caído. La alumna no aparece
ni en sala de espera.

## 2. El fallo

`zoom_participants` guarda el **número de filas** que devuelve el report de Zoom, no el número
de **personas distintas**. Una conexión inestable infla el contador.

## 3. Consecuencias

- Cualquier lógica que lea `zoom_participants` como "cuánta gente hubo" está equivocada.
- Una clase con **solo el profesor reconectándose** parece una clase concurrida.
- Falsea el diagnóstico de soporte: la fila tenía datos de Zoom y aun así se quedó sin cerrar,
  lo que hace parecer que la ingesta funcionó cuando no lo hizo.

## 4. Arreglo propuesto

Contar **participantes únicos** (por `id` de usuario de Zoom, o por `name` normalizado si no
hay id) en vez de filas, y **sumar la duración por persona** en lugar de tomar la de una fila.

⚠️ Antes de tocarlo, mirar cuántas filas históricas tienen `zoom_participants` inflado:

```bash
aula-sql "SELECT z.id, z.acuity_datetime, z.zoom_participants,
COUNT(DISTINCT p.zoom_name) AS personas_reales
FROM mdl_i3code_acuityZoom z
JOIN mdl_i3code_acuityZoom_participants p ON p.zoom_meetingid = z.zoom_meetingid
WHERE z.acuity_datetime LIKE '2026-%'
GROUP BY z.id HAVING z.zoom_participants > personas_reales
ORDER BY z.acuity_datetime DESC LIMIT 30"
```

## 5. Relacionado

- ING-2 (clases sin resolver): esta fila era una de ellas y por eso pasó desapercibida.
- ING-12: el informe con cifras imposibles bebe de estos mismos números.
- OPS-AULA-09 (`Hansel/tickets/`): las reconexiones de esa profesora, como problema de proceso.
