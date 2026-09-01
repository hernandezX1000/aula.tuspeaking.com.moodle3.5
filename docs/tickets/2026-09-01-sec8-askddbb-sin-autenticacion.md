# SEC-8 — `askddbb.php` ejecutaba SQL por POST sin autenticación

**Fecha:** 2026-09-01 · **Área:** Seguridad · **Prioridad:** Alta
**Ficheros:** `askddbb.php`, `own_CourseAcuity.js`

---

## Cómo apareció

Montando el bono de Eduardo Díaz (Capitole) desde `courseacuity.php`. La página
decía **"✓ Guardado correctamente"** en verde y, al refrescar, la configuración
del curso volvía a estar vacía. Investigando el guardado aparecieron tres cosas,
la última bastante peor que la que se buscaba.

## 1 · Los errores de base de datos se pintaban como éxito

`askddbb.php` no propaga el fallo: lo **devuelve como texto**.

```php
catch (PDOException $e) {
    $result = "Error Base de Datos.<br>Sentencia SQL:<br>" . $sql . "<br>Error:<br>" . $e->getMessage();
}
```

Y `own_CourseAcuity.js` comprobaba:

```js
res = $.parseJSON(result);
if (res) { saveChangesResults(1); }   // "✓ Guardado correctamente"
```

`res` vale `true` si fue bien **o una cadena con el error si fue mal**, y una
cadena no vacía es *truthy*. Resultado: **cualquier error de base de datos se
mostraba en verde como guardado correcto**, y el mensaje real se perdía.

Ocurría en los dos guardados de la página: el de la tabla (`saveChanges`) y el
del modal de creación de Acuity Types.

Que el guardado falló de verdad quedó demostrado al insertar la fila a mano:
`courseid` es UNIQUE en `own_acuity_course`, así que si la página hubiera
escrito algo el INSERT manual habría dado clave duplicada. No lo dio.

⚠️ **La causa del error de SQL sigue sin determinarse**, porque el mensaje nunca
llegó a verse. Con el arreglo, la próxima vez saldrá en consola y en el aviso.

## 2 · La página solo lista los cursos sin configurar

`own_CourseAcuity.js` línea 5: `var showOnlyUnconfigured = true;`, fijo en el
código y sin control en la interfaz. No es un fallo, pero explica que el listado
parezca casi vacío: un curso desaparece de la lista en cuanto tiene `classnmbr`.
No se toca en este ticket.

## 3 · El endpoint no comprobaba absolutamente nada

Este es el motivo de que el ticket sea de seguridad y no de un bug de interfaz.

```php
if(isset($_POST['sql'])) {
    require('./config.php');
    $sql = $_POST['sql'];
    if ($_POST['type'] == "ask")  echo json_encode(askmysql($sql));
    else if ($_POST['type'] == "set") echo json_encode(setMysql($sql));
}
```

Sin `require_login()`, sin comprobación de administrador y sin `sesskey`. El
control de admin estaba en `courseacuity.php`, que es **la página**; el endpoint
es un fichero suelto en la raíz del sitio.

Cualquiera que hiciera un POST a `https://aula.tuspeaking.com/askddbb.php` con
`type=set` y una sentencia SQL **la ejecutaba contra `aulatuspeaking35`** con el
usuario de la aplicación, que tiene permisos completos. Sin sesión y sin conocer
nada del sistema: el nombre del fichero es adivinable, y el 07-ago-2026 ya se
registró un bot escaneando webshells en este mismo host (SEC-7).

Además, **`askddbb.php` lo incluyen otras páginas** (`newAcuity.php`,
`cancelbymail.php`, `formClass.php`, `formFeedback.php`, `reminder.php`,
`tuspeakingvrlogin.php`, `eventupdater.php`, `acuityrecursive.php`). Al hacer
`require`, el bloque de ejecución también corría: un POST con `sql=` a
cualquiera de esas páginas ejecutaba la sentencia. `newAcuity.php` es el webhook
de Acuity, **público por necesidad**.

## Quién usa el endpoint (comprobado antes de tocarlo)

| Página | Quién entra | Usa |
|---|---|---|
| `courseacuity.php` | solo admin (`get_admins()`) | `ask` y **`set`** |
| `own_PrintZoom.php` | profesores y alumnos (`require_login`) | solo `ask` |
| `acuityrecursive.php` | cualquier matriculado (`require_login`) | solo `ask` |
| `newAcuity.php`, `cancelbymail.php`, `reminder.php`… | webhook / sin sesión | ninguno: solo las funciones |

`type=set` lo usa **únicamente** `own_CourseAcuity.js`, que vive en la página de
admin. Eso permite exigir sesión para todo y administrador solo para escribir
sin romper a nadie.

## Arreglo aplicado

**`askddbb.php`** — dos guardas dentro del bloque de ejecución:

1. `basename($_SERVER['SCRIPT_FILENAME']) === 'askddbb.php'` → el bloque solo
   corre cuando se llama directamente al endpoint. Incluido desde otra página
   queda inerte, así que **el webhook no se toca**.
2. `require_login()` para todo y `is_siteadmin()` para `type=set`, que responde
   403 si no lo es.

**`own_CourseAcuity.js`** — `if (res === true)` en los dos guardados, y el error
real a consola (y al aviso del modal) en la rama de fallo.

## Por qué no se hizo más

`require_sesskey()` habría sido lo correcto, pero ninguno de los cuatro puntos
de llamada envía `sesskey`: añadirlo obliga a tocar también `own_PrintZoom.js` y
`acuityrecursive.js`, que usan profesores y alumnos. Queda para una segunda
vuelta.

Y el fondo sigue ahí: **la página construye SQL en el navegador y lo manda a un
ejecutor genérico**. Con sesión y admin el riesgo baja mucho, pero un usuario con
sesión sigue pudiendo lanzar cualquier `SELECT` contra toda la base de datos.
Lo que cierra eso de verdad es sustituir el endpoint genérico por operaciones
concretas. No entra en este ticket.

## Verificación (obligatoria — `php -l` no basta)

1. `courseacuity.php` guarda un cambio y, si falla, **enseña el error real** en
   lugar de un verde.
2. `own_PrintZoom.php` abre para un **profesor** (no admin) y muestra sus clases.
3. `acuityrecursive.php` abre para un alumno matriculado.
4. **`newAcuity.php` sigue procesando una reserva** — reprocesar una cita real y
   comprobar en la BD, como manda `RUNBOOK-WEBHOOK-ACUITY.md`. Es el punto que
   más duele si se rompe: ING-9 dejó tres días sin registrar reservas.
5. Un POST sin sesión a `askddbb.php` ya no ejecuta nada.

## Rollback

Copias previas al despliegue, en el propio servidor:

```
/mnt/moodle-data/moodle-code/askddbb.php.pre-sec8
/mnt/moodle-data/moodle-code/own_CourseAcuity.js.pre-sec8
```

## Relacionado

- **SEC-7** — bot escaneando webshells en este host (07-ago-2026).
- **SEC-2** — credenciales de BD en duro; el mismo endpoint las usa vía `$CFG`.
- **ING-9 / ING-10** — precedentes de romper `newAcuity.php` y perder reservas.
