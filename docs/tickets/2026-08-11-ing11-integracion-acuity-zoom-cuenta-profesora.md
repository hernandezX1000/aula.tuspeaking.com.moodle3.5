# ING-11 — La integración Acuity↔Zoom cuelga de la cuenta personal de una profesora

- **Fecha de detección:** 2026-08-11
- **Contexto:** B · aula Moodle 3.5 (aula.tuspeaking.com)
- **Área:** Ingesta / Reservas
- **Prioridad:** Alta
- **Estado:** 🔴 abierto — **no ejecutar en horario de clases**
- **Impacto:** punto único de fallo para **todas** las reservas de Acuity de toda la
  cartera. Si cae, Moodle deja de recibir clases con sala de Zoom.

---

## Síntoma

En el detalle de cualquier cita de Acuity, el bloque de Zoom muestra:

```
Meeting created by: paola@live.tuspeaking.com (Zoom)
```

Aparece igual en citas de profesoras que no son Paola.

## Qué es realmente (y qué NO es)

Ese campo **no es el anfitrión de la reunión**: es la cuenta de Zoom con la que se
autorizó la integración Acuity↔Zoom. Acuity crea cada sala *a través* de esa conexión,
pero **a nombre del usuario de Zoom asignado al calendario** de cada profesora.

Verificado el 11-ago-2026 contra la API de Zoom sobre 7 salas de una misma alumna
(Nieves Sancho, userid 449) repartidas entre tres profesoras:

| Meeting ID | `host_email` | Calendario Acuity |
|---|---|---|
| 89117290092 | clara@live4.tuspeaking.com | Clara Blomfield |
| 88624846333 | clara@live4.tuspeaking.com | Clara Blomfield |
| 85488224876 | clara@live4.tuspeaking.com | Clara Blomfield |
| 87122226939 | amber@live5.tuspeaking.com | Amber Gendron |
| 83669051524 | dehlia@live8.tuspeaking.com | Dehlia Williams |
| 85632133906 | dehlia@live8.tuspeaking.com | Dehlia Williams |
| 84865223839 | dehlia@live8.tuspeaking.com | Dehlia Williams |

➡️ **El mapeo calendario → usuario de Zoom funciona bien.** No hay salas mal asignadas
y no hay que tocar nada de urgencia. El problema es de **dependencia**, no de datos.

## El problema

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://api.zoom.us/v2/users/paola@live.tuspeaking.com"
# rol: Member | tipo: 1 (básica) | estado: active
```

La integración está autorizada con la cuenta **personal de una profesora**, que además
es un usuario **Member** de tipo básico — ni Owner ni Admin.

Si esa cuenta se desactiva, se suspende, cambia de contraseña o se le revoca el token
OAuth, **Acuity deja de crear salas de Zoom para toda la cartera**, y lo hará en
silencio. Mismo patrón que dejó Cal.com sirviendo cero slots por un refresh token de
Google revocado.

Encadenado: sin sala de Zoom, `newAcuity.php` registra la reserva sin enlace útil, el
alumno no tiene botón para conectarse y la ingesta nocturna no encuentra asistencia que
bajar. Un fallo en la cuenta de una profesora se convierte en un fallo de servicio.

## Lo que NO sirve

`tools@tuspeaking.com` es la cuenta de administración de **Acuity**, no un usuario de
Zoom (`User does not exist: tools@tuspeaking.com`). El vínculo lo tiene que autorizar un
usuario de Zoom.

## Arreglo

**Owner del Zoom de la empresa: `hfernandez@tuspeaking.com`.** La integración debe colgar
de ahí, no de ninguna cuenta de profesora.

Dos opciones, en orden de preferencia:

1. **Usuario de servicio dedicado** (p. ej. `integraciones@tuspeaking.com`) con rol
   Admin, y reautorizar la integración con él. No depende de ninguna persona; es la
   solución buena.
2. **Reautorizar con el Owner** (`hfernandez@tuspeaking.com`). Más rápido, sigue atado a
   una persona, pero al menos es la persona que no se va.

### Procedimiento (⚠️ fuera de horario de clases — fin de semana)

1. **Antes de desconectar nada**, exportar el mapeo actual calendario → usuario de Zoom
   de **todos** los calendarios de Acuity. Sin esa lista, si el mapeo se pierde al
   reconectar hay que rehacerlo a ciegas.
2. Comprobar que las citas ya creadas conservan su `host_email` (deberían: las reuniones
   ya existen en Zoom y no se recrean).
3. Desconectar y reconectar la integración de Zoom en Acuity con la cuenta nueva.
4. Rehacer el mapeo calendario → usuario de Zoom si se ha perdido.
5. **Prueba real**: crear una reserva de prueba en el calendario de una profesora y
   comprobar por API que el `host_email` es el suyo y **no** el de la cuenta de
   servicio. Es la única prueba válida.
6. Comprobar que `newAcuity.php` sigue registrando en `own_acuity` y
   `mdl_i3code_acuityZoom`, y que `acuity_location` trae el enlace de la sala.
7. Dejar constancia del cambio aquí y en la bitácora.

### Rollback

Reautorizar de nuevo con `paola@live.tuspeaking.com` y restaurar el mapeo desde la lista
del paso 1.

### Verificación posterior recomendada

Un check periódico que avise si la integración deja de crear salas: comparar reservas
futuras de Acuity contra `mdl_i3code_acuityZoom.zoom_meetingid` no nulo. Encaja en el
digest de estado (MON-2b).

---

## Notas

- Detectado de rebote el 11-ago-2026 reprogramando una clase de Nieves Sancho
  (cita Acuity 1749286157, fila Moodle 111440).
- Relacionado: `TICKET-SEGURIDAD-rotar-credenciales.md` — la `apiKey` de Acuity sigue en
  claro en `acuityapi.php`, y el `userID` va sin comillas (numérico) en la línea 2.
