# Incidente 2026-08-07 — Sonia Funes (Hyatt): no-show falso y bono bloqueado

- **Alumna:** Sonia Funes Cánovas · `sonia.funes@hyatt.com` · **userid 4827** · curso **4046**
- **Profesora:** Rachel McCobb · userid 4276
- **Empresa:** Hyatt / Inclusive Collection
- **Estado:** resuelto · respuesta al cliente enviada como borrador
- **Instancia de:** `ING-2` (clases atascadas en "Verificando asistencia")
- **Hermano de:** `OPS-4` (Juan Antonio Muñoz, Hyatt — mismo patrón)

---

## 1. Lo que reportó la alumna

No pudo asistir a su clase del **miércoles 05/08 10:30**. Avisó a su profesora
por la **mensajería interna** del aula el mismo día; la profesora dice no haber
recibido ningún aviso. Reservó de nuevo para el **jueves 06/08 12:30** y *"no me
permitió conectarme en el link"*. Pedía por correo mover la clase del 05/08 a la
semana siguiente.

## 2. Estado encontrado (curso 4046, bono de 8)

| Fecha | id | `acuity_canceled` | `zoom_clasecompletada` | `zoom_duration` |
|---|---|---|---|---|
| 09/07, 16/07, 24/07, 31/07 | — | 0 | `1` asistida | 30-31 |
| **05/08 10:30** | **110315** | 0 | **`3` verificando** | 15 |
| **06/08 12:30** | **111388** | 0 | **`0` ausencia** | 23 |
| 07/08, 12/08, 13/08 | — | 0 | `3` | NULL (futuras) |

Informe: `clases_total 8 · comp 4 · noasist 1 · pend 3`.

## 3. Causa raíz — son dos cosas

### 3.1 El 05/08 fue ausencia real, pero quedó invisible

Participantes de Zoom del meeting del 05/08: **solo Rachel**, de 10:30:03 a
10:44:52 (14,8 min). La alumna no entró.

La fila quedó atascada en `zoom_clasecompletada = 3` (instancia de **ING-2**). Al
no ser ni `1` ni `0`, **el informe no la cuenta**: `pendientes = total −
(comp + noasist)`. Efecto colateral: la alumna acumuló **9 citas contra un bono
de 8** sin que ningún control lo detectara.

### 3.2 El 06/08 NO era un no-show

Participantes del meeting `88609489017` (clase 06/08 12:30):

| Participante | Entra | Sale | Min |
|---|---|---|---|
| Rachel MacCobb (`rachel@live8.tuspeaking.com`) | 12:29:46 | 12:52:32 | 22,8 |
| **`4721476`** — nombre numérico, **sin email** | **12:48:08** | 12:50:44 | **2,6** |

Un participante con **nombre numérico y sin email** es alguien que entró **sin
sesión de Moodle iniciada**. Es decir: la alumna sí intentó entrar, peleó ~18
minutos con el acceso, y acabó colándose como invitada cuando la clase ya
terminaba.

**El fallo fue del enlace de acceso, no de la alumna.** Coincide con su relato.

> ⚠️ **Regla general:** un no-show con `zoom_duration > 0` obliga a mirar
> `mdl_i3code_acuityZoom_participants` antes de darlo por bueno.

### 3.3 (secundario) El aviso a la profesora nunca llegó

`mdl_messages`: 5 mensajes de la alumna el 05/08 (ids 21512–21516). Los **cuatro
primeros con `smallmessage` vacío**; solo el quinto trae texto
(*"Hi Rachel, I have booked for tomorrow at 12:30"*).

`mdl_notifications` de userid 4276 entre el 05 y el 07 de agosto: **ninguna
notificación de mensajería**. Solo `local_reminders` y un `assign_notification`.

Pendiente de confirmar (`mdl_user_preferences` con `%instantmessage%` y estado de
los message processors). Ver `NOT-5` en BACKLOG.

## 4. Resolución aplicada

Decisión de Hansel: **anular ambas clases sin consumir bono.** La del 06/08 por
evidencia técnica; la del 05/08 para liberar el hueco que la alumna pedía.

1. Backup: `mdl_i3code_acuityZoom_bak_20260807`, `_informe_bak_20260807`,
   `own_acuity_bak_20260807`.
2. Cancelación en **Acuity** (fuente de la verdad) vía API con
   `admin=true&noEmail=true`, acuityids **1734823867** y **1749351295**.
3. Espejo en BD: `acuity_canceled = 1` en ids 110315 y 111388; `iscancelled='t'`
   en `own_acuity`.
4. **Clave:** `acuity_canceled = 1` **no basta**. El panel del alumno la pinta
   "Cancelada" (mira `acuity_canceled`), pero el informe **sigue contando el
   `estado 0` como ausencia**, y de ahí lee Success → el RRHH de la empresa veía
   una ausencia que la alumna no veía. Se alineó la fila del 06/08 a
   `zoom_clasecompletada = 3`, que es el patrón mayoritario de las canceladas:

   | `acuity_canceled` | `estado` | filas |
   |---|---|---|
   | 1 | 0 | 9 |
   | 1 | 1 | 14 |
   | 1 | 2 | 9 |
   | 1 | **3** | **431** |

5. Informe recalculado **excluyendo canceladas** → `comp 4 · noasist 0 · pend 4`.
6. Verificado en el panel de la alumna: ambas figuran como "Cancelada".

**No se tocó** la clase del 07/08 10:00 (era del día en curso, en `estado 3` con
`zoom_duration NULL`): eso es normal, el sync la resuelve.

## 5. Hallazgos colaterales

- **Cuentas duplicadas.** Existen `3201` (`sonia.funes@hyattic.eu`, datos de 2023)
  y `4827` (`sonia.funes@hyatt.com`, activa). Un `LIMIT 1` en la primera consulta
  devolvió la cuenta de 2023 y se diagnosticó sobre datos equivocados. → `OPS-5`.
- **`zoom_clasecompletada = 2`**: ~4.910 filas con un valor que **no está
  documentado** en ningún runbook. → `ING-4`.
- **9 clases sobre un bono de 8**: ningún control impide que un alumno acumule
  más citas que clases contratadas. → `ING-5`.

## 6. Reglas que salen de aquí

1. **Nunca `LIMIT 1`** para resolver un alumno: listar candidatos y elegir a mano.
2. **No-show con `zoom_duration > 0`** → mirar participantes antes de darlo por
   bueno. Nombre numérico y sin email = fallo de acceso nuestro.
3. **`acuity_canceled = 1` no limpia el informe.** Alinear a `estado 3`.
4. **No tocar clases del día en curso** en `estado 3` con `duration NULL`.
5. `acuity_datetime` es **texto ISO con zona** (`2026-08-05T10:30:00+0200`), no
   DATETIME: filtrar con `LIKE '2026-08%'`.
6. Horas de Zoom en **UTC** (+2 = CEST).

Incorporadas a `Runbooks/RUNBOOK-CANCELACION-CON-RECUPERACION.md` (repo
`hansel-operaciones`) y a la skill `soporte-ops`.

## 7. Pendiente

- **Verificar tras el sync nocturno** que el informe no vuelve a `noasist = 1`.
  Si vuelve, es bug del script de sync → abrir ticket en `ING`.
- Confirmar la causa del aviso de mensajería que no llegó (`NOT-5`).
