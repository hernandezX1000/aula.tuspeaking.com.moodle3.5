# Sistema de Corrección Automática de Entregables compatible con FUNDAE

Corrección automática de writings y audios en Moodle mediante IA, con trazabilidad completa de IP, completion tracking y notificaciones — compatible con requisitos de auditoría FUNDAE.

**Repositorio:** https://github.com/hernandezX1000/aula.tuspeaking.com.moodle3.5  
**Ruta en repo:** `tus-tools/autocorrect/`  
**Producción:** `/home/aulatuspeaking/scripts/` en vl24689.dinaserver.com  
**Estado:** Fase 1 en producción desde junio 2026

---

## Archivos

| Archivo | Descripción |
|---|---|
| `hansel_autocorrect.py` | Daemon principal. Detecta entregas pendientes, evalúa con Claude + Whisper, graba nota vía REST API. Cron cada 2h. |
| `hansel_digest.py` | Genera y envía email de audit diario a las 7:00 AM con cada entrega procesada (nota, tutor, IP, completion). |
| `transcribe_whisper.py` | Script auxiliar para probar transcripción Whisper de forma aislada. |
| `README.md` | Este archivo. |
| `../../docs/autocorrect/SISTEMA-CORRECCION-FUNDAE.md` | Diseño técnico completo, decisiones de arquitectura, roadmap. |

---

## Cómo funciona

```
Cada 2 horas (cron)
  └── hansel_autocorrect.py
        ├── Busca writings sin nota en mdl_assign_grades
        │     ├── Evalúa con Claude (nota + feedback + detección IA)
        │     └── ai_score ≥ 85 → nota=4 + aviso IA en español
        ├── Busca audios sin nota
        │     ├── Transcribe con faster-whisper (CPU local)
        │     └── Evalúa transcripción con Claude
        └── Graba vía mod_assign_save_grade (REST API)
              → IP registrada en mdl_logstore_standard_log
              → Completion activado
              → Notificación enviada al alumno

Cada día 7:00 AM (cron)
  └── hansel_digest.py
        └── Email HTML a hfernandez@tuspeaking.com
              → Tabla con cada entrega: alumno, curso, nota, tutor, IP, completion
              → Errores del log de las últimas 24h
```

---

## Requisitos del servidor

```bash
pip3 install anthropic pymysql faster-whisper --user
```

Variable de entorno: `ANTHROPIC_API_KEY` (inyectada en crontab).

---

## Despliegue

```bash
# Desde Mac — copiar al repo en servidor y hacer commit
scp hansel_autocorrect.py hansel_digest.py aulatuspeaking@aula.tuspeaking.com:/home/aulatuspeaking/www/app/moodle/tus-tools/autocorrect/

ssh aulatuspeaking@aula.tuspeaking.com '
  cd /home/aulatuspeaking/www/app/moodle &&
  git add tus-tools/autocorrect/ &&
  git commit -m "descripción del cambio" &&
  git push &&
  cp tus-tools/autocorrect/hansel_autocorrect.py /home/aulatuspeaking/scripts/ &&
  cp tus-tools/autocorrect/hansel_digest.py /home/aulatuspeaking/scripts/
'
```

---

## Fases

### Fase 1 — En producción ✅

Corrección automática 100% para todas las empresas. La nota se publica directamente al alumno vía REST API.

### Fase 2 — Pendiente 🔲

Panel de validación por tutor para empresas FUNDAE. Ver `docs/autocorrect/PENDIENTES_TECNICOS.md`.

---

## Reglas de negocio clave

- **Nota /10** para inglés, **/100** para francés (Salvi, Bydemes, GDES Frances)
- **Tutor corrector:** Velcro/Capitole → uid 4414 · Resto → uid 14 (Hansel)
- **IA detectada** (ai_score ≥ 85): nota = 4, feedback en español
- **Exclusiones manuales:** set `EXCLUDED_SUBMISSIONS` en `hansel_autocorrect.py`
- **Filtros:** excluye DEMO, Prueba de nivel, Italiano, escritos < 80 chars
- **Ventana temporal:** entregas desde 2026-01-01
