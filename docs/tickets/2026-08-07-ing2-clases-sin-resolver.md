# ING-2 (ampliado) — Una de cada cinco clases de 2026 no se resuelve nunca

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Prioridad:** Alta · **Estado:** 🔴 abierto
**Fecha:** 2026-08-07 · **Amplía:** `docs/2026-07-09-reproceso-verificando.md`

---

## 0. Dos sistemas distintos — no confundirlos

Este ticket va **solo del segundo**. Mezclarlos lleva a diagnósticos falsos.

| | **1 · Webhook de Acuity** | **2 · Ingesta de Zoom** |
|---|---|---|
| Ficheros | `modifyAndCreateAcuity.php`, `newAcuity.php`, `cancelAcuity.php` | `admin/cli/i3code_download_zoomdata.php` |
| Cuándo | En **cada reserva**, en tiempo real | Cron **1 vez al día**, 04:05 UTC (06:05 España) |
| Qué hace | Mete la reserva en `own_acuity` y crea los **eventos de calendario** | Rellena `mdl_i3code_acuityZoom`, baja participantes de Zoom y marca la asistencia |
| Si falla | **No hay clase**: el alumno no tiene enlace ni aviso | La clase se dio pero **no consta** como asistida |
| Ticket | ING-1 (webhooks mal apuntados, resuelto 6-ago) · ING-7 (recordatorios) | **este** |

⚠️ **`mdl_i3code_acuityZoom.zoom_meetingid = NULL` NO significa que el alumno se quede sin
enlace.** El enlace le llega por Acuity y por su evento de calendario (`own_acuity.studenteventid`).
`zoom_meetingid` es solo la copia que la ingesta extrae del `location` de Acuity. Para saber si
un alumno puede entrar a clase, mirar **`own_acuity`**, no esta tabla.

---

## 1. El hallazgo que cambia el diagnóstico

Hasta hoy se trataba esto como "clases que se atascan en Verificando asistencia". **No es un
atasco: el estado 3 es el estado INICIAL de toda reserva.**

Prueba: hay **143 clases futuras** en estado 3, con fechas hasta el 18 de noviembre. Una clase
de noviembre no puede estar verificando su asistencia.

Toda reserva nace en 3 y debe pasar a 1 (asistida) o 2 cuando el sync la procesa. Las que
aparecen "atascadas" son, simplemente, **las que nunca se procesaron**.

---

## 2. Cuánto hay (2026, excluyendo canceladas)

| Estado | Futuras | Pasadas | Total |
|---|---|---|---|
| 0 · "Sin datos" | 20 | **179** | 199 |
| 1 · asistida | 0 | 2.858 | 2.858 |
| 2 | 0 | 249 | 249 |
| 3 · "Verificando asistencia" | **143** | **678** | 821 |

**857 clases ya impartidas siguen sin resolver** (678 + 179), frente a 3.107 resueltas:
**el 22%**.

```sql
SELECT zoom_clasecompletada AS estado,
SUM(acuity_datetime >  DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 2 HOUR), '%Y-%m-%dT%H:%i:%s')) AS futuras,
SUM(acuity_datetime <= DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 2 HOUR), '%Y-%m-%dT%H:%i:%s')) AS pasadas,
COUNT(*) AS total
FROM mdl_i3code_acuityZoom
WHERE acuity_canceled = 0 AND acuity_datetime >= '2026-01-01'
GROUP BY zoom_clasecompletada ORDER BY estado;
```

⚠️ El servidor va en **UTC** y `acuity_datetime` es texto ISO con `+0200`: de ahí el
`DATE_ADD(NOW(), INTERVAL 2 HOUR)`. Sin eso, "futuras" y "pasadas" salen mal.

---

## 3. Son DOS problemas distintos y hay que separarlos

### 3.1 Etiqueta (cosmético, barato, alto impacto percibido)

El alumno ve **"Verificando asistencia"** en una clase que aún no ha tenido, y **"Sin datos"**
en sus próximas clases. Ambas cosas suenan a error del sistema y generan correos de soporte
(caso Juan Antonio Muñoz, Hyatt, 30-jul).

**Arreglo:** con fecha futura, mostrar **"Programada"**. El modelo ya tiene el concepto
(`mdl_i3code_acuityZoom_informe.clases_pendientes` existe para el contador), solo falta en la
etiqueta de cada clase.

### 3.2 Ingesta (el problema de fondo)

857 clases impartidas sin procesar. El caso verificado hoy (Juan Antonio Muñoz, 7-ago 12:00,
id 111016): **Zoom tenía los participantes** —profesora y alumno 29,4 min juntos— y
`mdl_i3code_acuityZoom` tenía `zoom_duration` NULL y cero filas en `_participants`.

**La reunión existía, la clase se dio, y la ingesta no bajó los datos.**

---

## 4. Por qué importa más allá de la estética

Una clase sin resolver **no cuenta en `clases_completadas`**, y de ahí salen:

- el **porcentaje de asistencia** que se reporta a FUNDAE,
- el cruce para **verificar las facturas de las profesoras**,
- lo que el alumno y el cliente ven en el panel.

Con un 22% sin resolver, ninguna de las tres cosas es fiable hoy.

---

## 4.bis · MECANISMO ENCONTRADO (07-ago, tarde)

### El cron corre UNA VEZ AL DÍA

```
5 4 * * * /home/coreadmin/scripts/run_ingesta.sh     # 04:05 UTC = 06:05 hora española
```

Corre y termina bien (`/home/coreadmin/cron_ingesta.log`: `2026-08-07 04:10:10 exit=0`).

➡️ **Toda clase impartida durante el día se queda en estado 3 hasta la mañana siguiente.**
Eso NO es un fallo, es el diseño. Pero el alumno ve "Verificando asistencia" durante horas y
escribe a soporte. El caso de Juan Antonio Muñoz (7-ago 12:00) era exactamente esto.

### La causa del atasco permanente: no hay reintento pasados 30 días

La fase que baja los participantes de Zoom (`i3code_download_zoomdata.php`, líneas 353-356):

```sql
WHERE z.zoom_meetingid IS NOT NULL AND z.zoom_starttime IS NOT NULL
  AND p.id IS NULL
  AND z.zoom_starttime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY z.zoom_starttime DESC LIMIT 500
```

Y el paso que marca la clase como completada (líneas 401-412) exige participantes con
**más de 600 segundos** acumulados:

```sql
HAVING sum(zoom_duration) > 600 ... SET z.zoom_clasecompletada = 1 WHERE z.manual_override = 0
```

**Si una clase no consigue sus participantes dentro de la ventana de 30 días, sale del rango
y el cron no vuelve a mirarla nunca.** Se queda en estado 3 de forma permanente. El `LIMIT 500`
agrava lo mismo cuando hay acumulación.

Además, el `UPDATE` **solo pone el estado a 1**: nunca marca el 2 (no asistió). Una clase a la
que el alumno no fue se queda igualmente en 3 para siempre.

### Qué explica esto

- Las **678 clases pasadas en estado 3** = las que perdieron su ventana de 30 días.
- Que el número **crezca cada mes** sin que nadie lo toque.
- Que el reproceso manual funcione (Zoom sí tiene los datos) pero no escale.

---

## 5. Qué investigar

### Arreglos propuestos (por relación coste/beneficio)

1. **Quitar o ampliar mucho la ventana de 30 días** en la consulta de participantes, y subir
   el `LIMIT`. Es el cambio de una línea que evita que sigan cayendo clases al pozo.
2. **Marcar el estado 2** cuando la clase ya pasó, tiene `zoom_meetingid` y Zoom devuelve
   cero participantes o menos de 600 s. Hoy nunca se marca: se queda en 3 para siempre.
3. **Subir la frecuencia del cron** de diaria a cada 2-4 horas. El alumno dejaría de ver
   "Verificando asistencia" durante todo el día tras su clase. Es la causa de buena parte
   de los correos a soporte.
4. **Etiqueta "Programada"** para clases futuras (§3.1).
5. **Reproceso masivo** de las 857 acumuladas contra la API de Zoom, en lote.

### Todavía por averiguar

- **¿Qué distingue al estado 0 del 3?** 179 clases pasadas en estado 0: puede ser otra vía de
  creación (¿reservas que no pasaron por el webhook?).
- **¿Qué es exactamente el estado 2?** 249 clases. Documentarlo en el skill con el resto.
- **7 clases futuras sin `zoom_meetingid`** (1 en estado 0, 6 en estado 3), ninguna con la
  etiqueta "To reschedule". Si Acuity no trae el `Meeting ID` en `location`, el cron no puede
  inventarlo y esos alumnos **no podrán entrar a clase**. Identificarlas y crear la reunión.

---

## 6. Mientras tanto

Cada caso que llegue por soporte se resuelve con el runbook de Zoom del skill `soporte-ops`
(§ VERIFICAR UNA CLASE CONTRA ZOOM) y se acredita con `manual_override = 1`.

**No escala**: van tres en un solo día (Jaume Alsina ×2, Juan Antonio Muñoz). Por cada una que
llega por correo hay decenas que nadie reclama.
