# Sistema de Corrección Automática de Entregables compatible con FUNDAE

**Proyecto:** aula.tuspeaking.com · Moodle 3.5  
**Repositorio:** https://github.com/hernandezX1000/aula.tuspeaking.com.moodle3.5  
**Carpeta en repo:** `tus-tools/autocorrect/`  
**Servidor:** `/home/aulatuspeaking/scripts/`  
**Estado actual:** Fase 1 completada — en producción desde junio 2026

---

## Qué hace el sistema

Detecta y corrige automáticamente las entregas escritas y de audio de los alumnos en Moodle, asigna una nota, genera feedback personalizado en inglés, marca la actividad como completada y registra la corrección con IP del corrector a través de la REST API de Moodle. Genera un email de audit diario a las 7:00 AM.

Compatible con FUNDAE en Fase 1 gracias al uso de la REST API (trazabilidad de IP, log de eventos Moodle, completion tracking). La validación humana por tutor es Fase 2.

---

## Arquitectura

```
[Moodle DB]
    │
    ├── mdl_assign_submission       (entregas del alumno)
    ├── mdl_assignsubmission_onlinetext  (texto writing)
    ├── mdl_files                   (fichero audio)
    ├── mdl_assign_grades           (notas — lectura y escritura vía API)
    ├── mdl_assignfeedback_comments (feedback — vía API)
    ├── mdl_course_modules_completion (completion — vía API)
    └── mdl_logstore_standard_log   (IP y eventos — solo via API/web)

[hansel_autocorrect.py] — cron cada 2h
    │
    ├── fetch_pending_writings()  → SQL SELECT (solo lectura)
    ├── call_claude_writing()     → Anthropic API (claude-haiku-4-5)
    │       └── ai_score ≥ 85 → nota=4 + aviso IA en español
    ├── fetch_pending_audio()     → SQL SELECT
    ├── transcribe_audio()        → faster-whisper (CPU local, model=base)
    ├── call_claude_audio()       → Anthropic API
    └── moodle_save_grade()       → Moodle REST API (mod_assign_save_grade)
            └── registra IP, dispara eventos, activa completion, envía notificación

[hansel_digest.py] — cron cada día 7:00 AM
    └── email HTML a hfernandez@tuspeaking.com
            ├── Entregas procesadas en 24h
            ├── Nota · Tutor · IP registrada · Completion status
            └── Errores del log
```

---

## Archivos del sistema

| Archivo | Ubicación en repo | Descripción |
|---|---|---|
| `hansel_autocorrect.py` | `tus-tools/autocorrect/` | Daemon principal. Writings + audio. |
| `hansel_digest.py` | `tus-tools/autocorrect/` | Email audit diario 7am. |
| `transcribe_whisper.py` | `tus-tools/autocorrect/` | Script auxiliar de prueba Whisper. |
| `SISTEMA-CORRECCION-FUNDAE.md` | `docs/autocorrect/` | Este documento. |

**Despliegue en servidor:** `/home/aulatuspeaking/scripts/` (copiar tras git pull).

---

## Configuración de cron (servidor)

```cron
# Autocorrector — cada 2 horas
0 */2 * * * ANTHROPIC_API_KEY=<ver secrets> /usr/bin/python3 /home/aulatuspeaking/scripts/hansel_autocorrect.py >> /home/aulatuspeaking/logs/hansel_autocorrect.log 2>&1

# Digest diario — 7:00 AM
0 7 * * * /usr/bin/python3 /home/aulatuspeaking/scripts/hansel_digest.py >> /home/aulatuspeaking/logs/hansel_digest.log 2>&1
```

---

## Reglas de negocio implementadas

### Detección de entregas
- Solo entregas con `status = 'submitted'` y `latest = 1`
- Desde 2026-01-01 en adelante
- Excluye: cursos DEMO, Prueba de nivel, Italiano
- Mínimo 80 caracteres para writings

### Puntuación
- **Inglés:** nota /10 (1.0–10.0, pasos de 0.5)
- **Francés** (Salvi, Bydemes, GDES Frances): nota /100 (10–100)
- El prompt de Claude devuelve siempre /10; el script convierte a /100 cuando corresponde

### Tutor corrector asignado
- Cursos Velcro o Capitole → `grader = 4414` (Tutors tuSpeaking, live@live.tuspeaking.com)
- Resto → `grader = 14` (hfernandez@tuspeaking.com, Hansel)

### Detección de IA
- `ai_score ≥ 85` → nota = 4 (o 40 si /100) + mensaje de aviso en español
- Lista de exclusión manual `EXCLUDED_SUBMISSIONS`: alumnos con IA confirmada que no llegan al umbral

### Audio
- Transcripción con faster-whisper (modelo `base`, int8, CPU)
- Si la transcripción está vacía → nota por nivel (fallback)
- Idioma audio: inglés por defecto; francés si el curso contiene keywords French

---

## Diseño técnico: por qué REST API y no SQL directo

| | SQL directo | REST API `mod_assign_save_grade` |
|---|---|---|
| IP registrada en logstore | ❌ No | ✔ Sí |
| Completion automático | ❌ Manual | ✔ Automático |
| Notificación al alumno | ❌ No | ✔ Sí |
| Eventos Moodle (audit) | ❌ No | ✔ Sí |
| Complejidad | Baja | Media |

La migración a REST API (Fase 1) es el requisito mínimo para cumplimiento FUNDAE: el sistema de log de Moodle registra automáticamente la IP del token que realiza la corrección.

**Token Moodle WS usado:** `hfernandez` (user 14) — las correcciones quedan registradas bajo su IP en `mdl_logstore_standard_log`.

---

## Estado de fases

### Fase 1 — COMPLETADA (junio 2026)

- [x] Detección automática de writings pendientes
- [x] Evaluación con Claude AI (nota + feedback + detección IA)
- [x] Transcripción de audio con Whisper + evaluación Claude
- [x] Grabación de nota via REST API (IP, completion, notificación)
- [x] Filtros de exclusión (DEMO, italiano, IA confirmada)
- [x] Email digest diario 7:00 AM con audit completo
- [x] Cron cada 2h en producción

### Fase 2 — PENDIENTE (diseño completado, pendiente de implementar)

Panel de control con dos modos por empresa:

**Modo A — Auto (empresas NO FUNDAE):**
El comportamiento actual. La corrección IA se publica directamente al alumno.

**Modo B — Validación con tutor (empresas FUNDAE):**
1. IA corrige y genera nota + feedback (en `workflow_state = 'inmarking'`, no visible al alumno)
2. El tutor asignado recibe notificación con la propuesta de corrección
3. El tutor revisa desde el mini-panel, puede modificar nota/feedback
4. El tutor valida → REST API publica (`workflow_state = 'graded'`) con la IP del tutor
5. La IP que queda en `mdl_logstore_standard_log` es la del tutor real

**Componentes Fase 2:**
- Tabla de configuración por empresa: `mdl_ts_autocorrect_config` (empresa_id, mode, tutor_id)
- Mini-panel web (PHP o React): lista entregas en estado `inmarking`, acción Validar/Modificar
- Token WS por tutor o uso de credenciales del tutor para la llamada final a `mod_assign_save_grade`
- Integración con Success para leer si la empresa es FUNDAE

---

## Coste de tokens IA (estimación trimestre actual)

Volumen estimado: **30–40 entregas/mes** (trimestre 2026.1)

| Concepto | Cálculo | Coste mensual |
|---|---|---|
| Writing evaluation — input | 40 × 1.500 tokens × $0,80/MTok | ~$0,05 |
| Writing evaluation — output | 40 × 250 tokens × $4/MTok | ~$0,04 |
| Audio evaluation — input | 10 × 1.000 tokens × $0,80/MTok | ~$0,01 |
| Audio evaluation — output | 10 × 250 tokens × $4/MTok | ~$0,01 |
| **Total** | | **~$0,11/mes** |

Modelo: claude-haiku-4-5-20251001. Whisper corre en CPU local del servidor (coste = 0).

**Conclusión: no se necesita base de datos de tokens.** A este volumen el coste es insignificante (<$2/trimestre). El log existente (`hansel_autocorrect.log`) ya registra cada llamada. Si el volumen escala x10 (400/mes) el coste sería ~$1/mes — aún irrelevante. Revisar si supera 5.000 entregas/mes.

---

## Dependencias en el servidor

```
Python 3.9 (Debian 11)
  ├── anthropic         (pip3 install anthropic --user)
  ├── pymysql           (pip3 install pymysql --user)
  └── faster-whisper    (pip3 install faster-whisper --user)
```

Variables de entorno necesarias:
- `ANTHROPIC_API_KEY` — inyectada directamente en el cron (ver crontab)

---

## Workflow de despliegue

```bash
# 1. Editar scripts localmente en ~/Proyectos/Hansel/
# 2. Copiar al repo clonado y hacer push
cp ~/Proyectos/Hansel/hansel_autocorrect.py \
   ~/ruta/al/repo/tus-tools/autocorrect/

cd ~/ruta/al/repo
git add tus-tools/autocorrect/
git commit -m "feat(autocorrect): descripción del cambio"
git push

# 3. Desplegar al servidor
ssh aulatuspeaking@aula.tuspeaking.com '
  cd /home/aulatuspeaking/www/app/moodle && git pull &&
  cp tus-tools/autocorrect/hansel_autocorrect.py /home/aulatuspeaking/scripts/ &&
  cp tus-tools/autocorrect/hansel_digest.py /home/aulatuspeaking/scripts/
'
```

---

## Pendientes técnicos menores

- [ ] Mover `ANTHROPIC_API_KEY` del crontab a un fichero `.env` fuera del repo (`/home/aulatuspeaking/secrets/autocorrect.env`) y cargar con `source` en el cron
- [ ] Añadir `--dry-run` al cron de staging si se crea un entorno de pruebas
- [ ] Ampliar `EXCLUDED_SUBMISSIONS` con un sistema de marcado desde Moodle en lugar de edición manual del script
