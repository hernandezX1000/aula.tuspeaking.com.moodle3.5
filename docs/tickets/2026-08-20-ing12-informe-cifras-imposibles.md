# ING-12 — El informe del alumno da cifras imposibles (completadas > total, pendientes negativas)

**Repo:** `aula.tuspeaking.com.moodle3.5` · **Prioridad:** Alta · **Estado:** 🔴 abierto
**Fecha:** 2026-08-20 · **Tabla:** `mdl_i3code_acuityZoom_informe`

---

## 1. Qué se ha visto

Al verificar una cancelación de **Nieves Sancho (userid 449, E2Y Commerce)** apareció esto:

```
courseid  clases_total  clases_completadas  clases_no_asistidas  clases_pendientes
    1298             0                   8                    0                 -8
    1476             0                  16                    0                -16
    2978            35                  65                    0                -30
    3114            55                  66                    0                -11
    2151            30                  20                    0                 10
    2389            36                  27                    3                  6
```

**Completadas mayores que el total, y pendientes en negativo.** En dos cursos el total es **0**
con 8 y 16 clases completadas.

## 2. Por qué importa

- `mdl_i3code_acuityZoom_informe` **se regenera entera en cada sync**. Que hoy esté así
  significa que **el cálculo está mal ahora**, no que sea un residuo antiguo.
- Es la tabla de la que come **Success**, es decir, **el panel que ve RRHH del cliente**.
- Nieves Sancho es precisamente la **responsable de RRHH de E2Y**: es de las que miran el panel.
- Si el mismo error afecta a cursos FUNDAE, el porcentaje de asistencia que se reporta puede
  no cuadrar con la realidad en una revisión.

## 3. Lo que NO se sabe

- **El alcance.** Solo se ha mirado un alumno, por casualidad. No se ha barrido.
- Si el desajuste es de `clases_total` (se queda a 0 o corto) o de `clases_completadas`
  (cuenta de más), o de los dos.
- Si hay relación con cursos antiguos / matrículas vencidas.

## 4. Primer paso: medir el alcance

```bash
aula-sql "SELECT COUNT(*) AS filas_incoherentes,
COUNT(DISTINCT userid) AS alumnos, COUNT(DISTINCT courseid) AS cursos
FROM mdl_i3code_acuityZoom_informe
WHERE clases_pendientes < 0 OR clases_completadas > clases_total"

aula-sql "SELECT courseid, COUNT(*) AS filas
FROM mdl_i3code_acuityZoom_informe
WHERE clases_pendientes < 0 OR clases_completadas > clases_total
GROUP BY courseid ORDER BY filas DESC LIMIT 20"
```

⚠️ **Solo lectura.** No tocar la tabla hasta saber cuál de las dos columnas miente: se regenera
en cada sync, así que un `UPDATE` a mano se pierde esa misma noche. **El arreglo va en la
consulta que la genera**, no en los datos.

## 5. Relacionado

- ING-5: el informe cuenta las canceladas como ausencia (mismo generador).
- ING-2: clases que nunca se resuelven — otra fuente de descuadre del mismo panel.
- ING-13: `zoom_participants` cuenta reconexiones como personas distintas.
