# ING-10 · El webhook de Acuity dejó de registrar reservas (8–10 ago 2026)

**Contexto:** B · aula Moodle 3.5 (aula.tuspeaking.com)
**Severidad:** alta — pérdida silenciosa de reservas de toda la cartera
**Detectado por:** incidencia de una alumna (Ana Pantoja, CENIEH), 10-ago 09:16
**Resuelto:** 10-ago-2026, ~11:20 CEST
**Commits:** `1b1e12c` y su enmienda, rama `dev`

---

## Síntoma

Los alumnos reservaban en Acuity, recibían su correo de confirmación (lo envía
Acuity, no el aula) y **la clase no aparecía en "Mis clases"**: sin fila, sin
enlace y sin botón para conectarse.

`POST /newAcuity.php` respondía **HTTP 500**.

| Día | 200 | 500 |
|---|---|---|
| 06-ago | 15 | 0 |
| 07-ago | 7 | 0 |
| **08-ago** | **0** | **63** |
| 09-ago | 2 | 70 |
| 10-ago (hasta 10:30) | 5 | 133 |

---

## Causa raíz

El 07-ago se desplegó **ING-7**, que eliminó la creación de dos eventos de
calendario de cortesía para cuentas inactivas (`userid 2` Carmen, suspendida;
`userid 48` Guillermo, borrado en 2018). El fix era correcto: el evento de la
cuenta borrada abortaba el cron de recordatorios.

Pero **no se ajustó el `INSERT` de `own_acuity`** (newAcuity.php, línea 362),
que seguía escribiendo cinco identificadores de evento:

```php
VALUES (..., $userIDs[2], $userIDs[3], $userIDs[4], NOW())
                          ↑ Carmen     ↑ Guillermo
```

Al no existir ya esas posiciones, la sentencia salía con dos huecos vacíos:

```
VALUES (1751194360, 3232, 5797, 638391, 2235, 638392, null, 638393, , , NOW())
SQLSTATE[42000]: 1064 ... near ' , NOW())'
```

Los eventos de calendario **sí se creaban**; el fallo llegaba después, al
guardar la reserva.

---

## Por qué estuvo tres días en silencio

Cuando `newAcuity.php` detecta un error, **solo intenta avisar por correo** a
`soporte.tuspeaking@gmail.com` usando `mail()` de PHP. El contenedor
`moodle35-app` **no tiene agente de correo** (`/usr/sbin/sendmail` no existe),
así que:

- el aviso nunca salió — cientos de correos perdidos;
- el 500 que veía Acuity venía precisamente de ese `mail()` fallido;
- **no quedó ningún rastro en disco**: el mensaje de error no se escribe en
  ningún log.

El error solo se pudo leer reproduciendo la petición a mano con el modo de
depuración del propio fichero (`?veradm=si948`).

> Moodle sí envía correo con normalidad: usa SMTP de Gmail
> (`smtp.gmail.com:465`). Solo falla `mail()` nativo de PHP.

---

## Solución aplicada

`ceventid` y `geventid` pasan a **0** en el `INSERT`. No `NULL`: ambas columnas
son `NOT NULL` en `own_acuity`, y el propio código de ING-9 ya interpreta el 0
como "no hay evento" (`(int)$campo > 0`).

Desplegado por el procedimiento correcto: edición en el repo → commit → push →
`scp` → `md5sum` servidor vs origen → `php -l`.

---

### Dónde queda ahora el rastro

`newAcuity.php` escribe los errores en `$CFG->dataroot . '/newacuity_errors.log'`
y avisa por `email_to_user()` a **hfernandez@tuspeaking.com** (usuario 14).
Además responde **200** aunque haya error: el 500 provocaba reintentos de Acuity
y cada reintento duplicaba los eventos de calendario.

En el servidor ese log es:

```
/mnt/moodle-data/moodledata/newacuity_errors.log
```

> ⚠️ **Trampa detectada al hacerlo:** `/mnt/moodle-data/moodle-code/config.php`
> está **vacío (0 bytes)**. El config real se monta encima desde
> `/home/coreadmin/tuspeaking-platform/services/moodle35-staging/config-staging.php`
> (solo lectura) y define `$CFG->dataroot = '/var/moodledata'`, que es el bind
> de `/mnt/moodle-data/moodledata`. Quien busque la configuración en el árbol de
> código no la encontrará.

---

## Recuperación de datos

28 reservas rehechas relanzando el webhook por GET
(`newAcuity.php?id=<acuityid>`), que es el mecanismo de reproceso que el propio
código previó:

| Alumno | Clases | Fechas |
|---|---|---|
| Ana Pantoja (5797) | 15 | 10–20 ago |
| María Concepción Moreno-Torres (5801) | 4 | 18, 20 y 28 ago |
| Paula Rodríguez (5062) | 2 | 26 y 27 ago |
| Jordi López (1985) | 7 | 8 sep – 20 oct |

Todas verificadas en `mdl_i3code_acuityZoom` con estado 3 y meeting ID.

Además, la clase cancelada del 4-ago de Ana quedó alineada
(`acuity_canceled = 1`) al reprocesarla: la rama de cancelación de ING-9
funciona correctamente.

---

## Lecciones

1. **Un fix que quita algo tiene que revisar quién lo consumía.** ING-7 eliminó
   dos eventos sin tocar la sentencia que los guardaba. `php -l` no detecta esto:
   solo lo detecta ejecutar una reserva real.
2. **Un error que solo viaja por correo no existe.** Hay que escribir a disco
   *siempre*, y usar el envío de Moodle (`email_to_user`, que va por SMTP) en
   lugar de `mail()`.
3. **Nada se despliega un viernes por la tarde sin una prueba de extremo a
   extremo.** ING-7 e ING-9 se subieron el 07-ago entre las 17:55 y las 19:52.
4. **Vigilancia:** nadie miró el porcentaje de 500 del webhook durante tres
   días. El digest debería incluirlo.

---

## Pendiente

- [ ] **804 eventos de calendario huérfanos** →
      `docs/ops/2026-08-10-ing10-limpiar-eventos-huerfanos.sql`
- [ ] **Escribir los errores del webhook a fichero** y sustituir `mail()` por
      `email_to_user()`. Sin esto, el próximo fallo también será mudo.
- [ ] **Alerta sobre el ratio de 500 de `newAcuity.php`** en el digest diario.
- [ ] **Rotar la clave de la API de Acuity**: está en claro en `acuityapi.php`
      y quedó expuesta durante el diagnóstico. Sacarla a configuración.
- [ ] **10 reservas ausentes con fecha del 3 al 7 de agosto** — anteriores al
      fallo, causa distinta. Dos de ellas son David Pecondon y Nieves Sancho,
      casos ya conocidos de clases impartidas fuera de Acuity. Revisar contra
      Zoom, no relanzar (crearían clases "agendadas" con fecha vencida).
- [ ] **El webhook ya perdía reservas antes del 7-ago**, en bajo volumen.
      Investigar.
- [ ] Dos firmas distintas de webhook en Acuity (`AcuityScheduling` y
      `AcuityScheduling-production`). Comprobar si hay dos configurados y si
      uno sobra.
- [ ] Cita de María Concepción con offset `+0100` en agosto (zona horaria
      incorrecta desde Acuity).
- [ ] El cron de Moodle tardó **97 minutos** en completarse el 10-ago. Sin
      relación con este incidente, pero anómalo.
