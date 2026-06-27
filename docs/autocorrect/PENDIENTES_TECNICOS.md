# Pendientes técnicos — Fase 2

Sistema de Corrección Automática de Entregables compatible con FUNDAE  
Última actualización: junio 2026

---

## Qué falta para Fase 2 completa

### 1. Tabla de configuración por empresa en Moodle BD

Necesaria para que el autocorrector sepa si una empresa es FUNDAE (modo validación) o no (modo auto).

```sql
CREATE TABLE mdl_ts_autocorrect_config (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    company_key   VARCHAR(100) NOT NULL,   -- keyword del nombre de curso, ej: 'cesce', 'velcro'
    mode          ENUM('auto', 'fundae') NOT NULL DEFAULT 'auto',
    tutor_userid  INT NOT NULL,            -- uid del tutor validador en Moodle
    tutor_wstoken VARCHAR(100),            -- token WS personal del tutor (para que su IP quede registrada)
    active        TINYINT(1) DEFAULT 1,
    created_at    INT,
    UNIQUE KEY uq_company (company_key)
);
```

Alternativa más simple sin BD nueva: fichero `config_empresas.json` en `/home/aulatuspeaking/scripts/` con el mismo contenido. Más fácil de mantener pero sin UI de edición.

---

### 2. Modificación de hansel_autocorrect.py para modo FUNDAE

Cuando la empresa es FUNDAE, en lugar de publicar la nota directamente, guardarla en estado `inmarking` (no visible al alumno).

```python
# En moodle_save_grade(), añadir parámetro workflow_state
def moodle_save_grade(assignment, userid, grade, feedback, workflow_state='graded'):
    params = {
        ...
        'workflowstate': workflow_state,  # 'graded' (auto) o 'inmarking' (FUNDAE)
    }
```

**⚠ Requisito previo:** El "marking workflow" debe estar activado en cada assignment de Moodle para que `workflowstate` funcione. Si no está activado, Moodle ignora el parámetro y publica directamente. Hay que verificar si los assignments de tuSpeaking tienen esta opción habilitada, o activarla masivamente vía SQL:

```sql
UPDATE mdl_assign SET markingworkflow = 1
WHERE course IN (
    SELECT id FROM mdl_course WHERE fullname LIKE '%CESCE%'  -- ejemplo empresa FUNDAE
);
```

---

### 3. Mini-panel de validación para tutores

Interfaz web donde el tutor ve las entregas en estado `inmarking`, revisa la propuesta IA y valida o modifica.

**Stack recomendado:** PHP dentro del repo existente (mismo patrón que `reportes_cesce/`, `admin-panel/`). Ruta sugerida: `tus-tools/autocorrect/panel/index.php`.

**Flujo del panel:**

```
Tutor abre panel → ve lista de entregas pendientes (inmarking)
  ├── Para cada entrega: alumno, curso, nota IA propuesta, feedback IA
  ├── Puede editar nota y feedback
  └── Clic "Validar" →
        JS del navegador llama a mod_assign_save_grade con el token del tutor
        → Moodle registra la IP real del tutor en mdl_logstore_standard_log ✓
        → Entrega pasa a workflow_state = 'graded' (visible al alumno)
```

**Consulta para listar pendientes:**
```sql
SELECT
    ag.assignment, ag.userid, ag.grade,
    u.firstname, u.lastname,
    c.fullname AS course_name, a.name AS assign_name,
    afc.commenttext AS feedback_ia,
    FROM_UNIXTIME(ag.timemodified) AS fecha_correccion_ia
FROM mdl_assign_grades ag
JOIN mdl_user u ON u.id = ag.userid
JOIN mdl_assign a ON a.id = ag.assignment
JOIN mdl_course c ON c.id = a.course
LEFT JOIN mdl_assignfeedback_comments afc
    ON afc.assignment = ag.assignment AND afc.grade = ag.id
WHERE ag.grader IN (14, 4414)
  AND (
    SELECT workflowstate FROM mdl_assign_user_flags
    WHERE assignment = ag.assignment AND userid = ag.userid
  ) = 'inmarking'
ORDER BY ag.timemodified ASC;
```

---

### 4. Tokens WS por tutor (crítico para IP FUNDAE)

**El problema:** Si el servidor llama a `mod_assign_save_grade` en nombre del tutor, la IP que queda en el log es la IP del servidor (82.98.190.208), no la del tutor. Para FUNDAE necesitamos la IP real del tutor.

**Solución:** La llamada a la REST API debe hacerse desde el navegador del tutor (JavaScript), no desde el servidor. El panel envía al navegador el token WS del tutor, el JS hace el POST directamente a `https://aula.tuspeaking.com/app/moodle/webservice/rest/server.php`.

**Tokens necesarios:** Crear un WS token por tutor en Moodle (Admin → Plugins → Web Services → Manage tokens). Guardar en `mdl_ts_autocorrect_config.tutor_wstoken`.

**Tutores a los que crear token:**
- hfernandez@tuspeaking.com (uid 14) — ya tiene token
- live@live.tuspeaking.com (uid 4414) — pendiente de crear
- Tutores externos si los hay (Velcro, Capitole)

---

### 5. Integración con Success para flag FUNDAE automático

En lugar de mantener `mdl_ts_autocorrect_config` a mano, leer de Success si una empresa es FUNDAE. Success ya tiene la BD de empresas.

Requiere: endpoint en Success API que devuelva `{empresa: 'CESCE', fundae: true}` — o consulta directa a la BD de Success desde el script.

Esto es **opcional para Fase 2** — se puede arrancar con el JSON/tabla manual y conectar Success más adelante.

---

## Resumen de componentes a desarrollar

| Componente | Complejidad | Prioridad | Descripción |
|---|---|---|---|
| Activar `markingworkflow` en assignments FUNDAE | Baja | Alta | SQL masivo. Prereq para todo lo demás. |
| `config_empresas.json` o tabla BD | Baja | Alta | Define qué empresas son FUNDAE. |
| Modificar `hansel_autocorrect.py` para `inmarking` | Baja | Alta | 5 líneas de cambio. |
| Token WS por tutor | Baja | Alta | Gestión en admin Moodle. |
| Mini-panel PHP (listado + validación JS) | Media | Alta | ~200 líneas PHP + 50 líneas JS. |
| Integración Success para flag FUNDAE | Alta | Baja | Opcional, se puede hacer en Fase 3. |

---

## Orden de implementación recomendado

1. Verificar si los assignments de empresas FUNDAE tienen `markingworkflow` activado
2. Decidir: JSON de config o tabla SQL
3. Crear token WS para uid 4414 y cualquier tutor externo
4. Modificar `hansel_autocorrect.py` (parámetro `workflow_state`)
5. Construir mini-panel PHP con validación vía JS
6. Prueba end-to-end con una empresa FUNDAE real

---

## Lo que NO hace falta construir

- Base de datos de tokens IA: a 30–40 entregas/mes el coste es ~$0.11/mes. El log existente es suficiente.
- VPN para tutores: descartado (incompatible con Zoom en directo).
- Servidor separado: todo corre en el mismo servidor Moodle.
