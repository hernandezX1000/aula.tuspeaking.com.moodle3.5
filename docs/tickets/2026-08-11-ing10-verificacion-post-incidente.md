# ING-10 · Verificación del día siguiente (11-ago-2026)

**Estado:** ABIERTO — cerrar tras ejecutar las 3 comprobaciones.
**Origen:** `docs/incidents/2026-08-10-ing10-webhook-acuity-caido.md`
**Contexto:** B · aula Moodle 3.5 · `ssh coreadmin@46.225.232.27`

El incidente se resolvió el 10-ago, pero **tres cosas quedaron sin poder
verificarse ese mismo día** porque dependían del cron nocturno o de que
ocurriera un error real. Esto es esa comprobación.

---

## 1 · ¿La ingesta completó las 8 clases del 3 al 7 de agosto?

Se recrearon en estado 3 confiando en que el cron de las 04:05 UTC les pondría
la asistencia real de Zoom (la ingesta mira 30 días atrás y esas fechas entran
en la ventana).

```bash
aula-sql "SELECT z.id, z.acuityid, CONCAT(u.firstname,' ',u.lastname) AS alumno,
z.acuity_datetime, z.zoom_clasecompletada AS estado,
z.zoom_participants, z.zoom_duration
FROM mdl_i3code_acuityZoom z JOIN mdl_user u ON u.id = z.studentid
WHERE z.acuityid IN (1748041642,1748710984,1748723885,1748711248,
                     1749299929,1749286009,1748858573,1749284766)
ORDER BY z.acuity_datetime"
```

| Resultado | Qué significa | Qué hacer |
|---|---|---|
| Estado **1** con `zoom_participants` y `zoom_duration` | La clase se dio y quedó acreditada | Nada |
| Estado **2** | Ausencia real | Nada, salvo que el cliente reclame |
| Sigue en **3** con NULL | La ingesta no la alcanzó | **Verificar contra Zoom a mano** — §VERIFICAR UNA CLASE CONTRA ZOOM |

Filas afectadas: **111651–111658**. Alumnos: Nieves Sancho (E2Y, 5),
Marta Casanovas (Velcro, 1), José Luis Carla (CESCE, 1), Rafael Herrador
(Hyatt, 1).

⚠️ Si varias siguen en 3, **no es normal**: mirar `~/cron_ingesta.log` y
comprobar que el cron corrió.

## 2 · ¿El webhook sigue limpio?

```bash
docker exec moodle35-app sh -c "grep newAcuity /var/log/apache2/moodle-access.log | tail -20"

docker exec moodle35-app sh -c "grep newAcuity /var/log/apache2/moodle-access.log \
  | awk '{split(\$4,d,\":\"); print substr(d[1],2), \$9, \$NF}' | sort | uniq -c"
```

Esperado: **solo 200**. Cualquier 500 posterior al 10-ago 11:39 CEST es un
problema nuevo → seguir `RUNBOOK-WEBHOOK-ACUITY.md`.

Y que sigan entrando reservas de verdad:

```bash
aula-sql "SELECT acuityid, studentid, lastmodified FROM own_acuity
WHERE lastmodified >= '2026-08-10 12:00' ORDER BY lastmodified DESC LIMIT 20"
```

## 3 · ¿Funciona el aviso de error?

El nuevo circuito (log en disco + correo por SMTP de Moodle a
`hfernandez@tuspeaking.com`) **se desplegó pero no se estrenó**: no hubo ningún
error real después.

```bash
sudo ls -la /mnt/moodle-data/moodledata/newacuity_errors.log 2>/dev/null \
  && sudo tail -20 /mnt/moodle-data/moodledata/newacuity_errors.log
```

- **El fichero no existe** → no ha habido errores. Es buena señal, pero el
  circuito sigue sin probarse.
- **Existe y hay líneas** → mirar si llegó también el correo. Si hay líneas y
  **no** llegó el correo, el aviso está roto: revisar `email_to_user()`.

## 4 · La reprogramación, aún sin probar

La rama de ING-9 que mueve una clase de hora **no se ha visto funcionar** con un
caso real. La primera vez que un alumno reprograme, comprobar que **no se
duplica** la fila ni los eventos de calendario:

```bash
aula-sql "SELECT acuityid, COUNT(*) AS filas, MAX(modifiedtimes) AS reprogramaciones
FROM own_acuity WHERE lastmodified >= '2026-08-10'
GROUP BY acuityid HAVING filas > 1"
```

Debe devolver **0 filas**. Si devuelve alguna, la reprogramación duplica y hay
que abrir ticket.

---

## Pendientes que NO son de mañana pero siguen abiertos

- [ ] **Rotar la clave de la API de Acuity.** Está en claro en `acuityapi.php` y
      quedó expuesta durante el diagnóstico. Sacarla a configuración.
- [ ] **Las 2 clases del 3-ago de David Pecondon** (GDES, profesora Paola
      Demarchi): sus citas llegan **sin MoodleID**, así que no hay recuperación
      automática. O se corrige el formulario en Acuity, o se insertan a mano con
      la evidencia de Zoom. **Y hay que hablar con la profesora**: mientras las
      reservas se creen así, se repite cada semana.
- [ ] **Alerta en el digest diario** si el ratio de 500 de `newAcuity.php` sube.
      Esta vez nadie se enteró durante tres días.
- [ ] **Citas con zona horaria incorrecta desde Acuity**: José Luis Carla
      (`-0600`) y María Concepción Moreno-Torres (`+0100` en agosto).
- [ ] **El cron de Moodle tardó 97 minutos** el 10-ago. Sin relación con el
      incidente, pero anómalo.
- [ ] **El webhook ya perdía reservas antes del 7-ago**, en bajo volumen. Sin
      investigar.
