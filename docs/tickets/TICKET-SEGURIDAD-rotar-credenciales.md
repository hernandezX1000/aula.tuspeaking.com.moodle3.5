# [TICKET-SEGURIDAD] Rotar credenciales expuestas + refactor a secrets.php + limpiar histórico

**Fecha apertura:** 2026-08-06
**Prioridad:** 🟡 Media (repo PRIVADO → exposición contenida; no es breach activo, pero es deuda de seguridad real)
**Estado:** Abierto — pendiente de sesión dedicada (NO hacer con prisa: la rotación de BD puede tumbar el aula)
**Referencia:** `docs/AUDITORIA-COBERTURA-GIT.md` §1 (riesgo) y §8 (refactor)

## Problema

Hay credenciales reales **en el histórico de git** (no basta con dejar de trackearlas):

| Secreto | Ficheros trackeados que lo contienen |
|---|---|
| **Password BD `TuspeakingFix2025!`** (user moodle35) | `misclases.php` · (y en duro fuera de git: `config.php`, `admin-panel/config.php`, `db_api.php`, `monitor_zoom.php`, `tutorias_con_profesor.php`, `webhook_jotform_evaluaciones.php`, `local/misclases/lib.php`, `portal/rrhh.php`, todos los `feedback/*`, `empresas/*`, `evaluaciones/*`, `reportes*/*`) |
| **Acuity API key `7727…4693`** | `newAcuity.php` · `admin/cli/i3code_download_zoomdata.php` · `empresas/acuity_zoom.php` · (fuera de git: `acuityapi.php`, `cancelAcuity.php`, `modifyAcuity.php`, `modifyAndCreateAcuity.php`) |
| **Password demo `Success2026!`** | `admin-panel/demo_manager.php` |
| **Zoom JWT (key+secret)** | `own_ZoomAPIToken.php` (JWT DEPRECADO por Zoom; ya no se usa, la ingesta va por S2S OAuth `get_config('zoom')`) |

## Plan por riesgo (hacer en este orden)

### 🟢 Fase 1 — `secrets.php` (SEGURO, no rota nada, el sitio sigue igual)
- Crear `secrets.php` (gitignored) con las credenciales; que `config.php` y los scripts custom lean de ahí (o de `$CFG->dbpass`) en vez del literal.
- Verificar que el sitio funciona igual antes de tocar ninguna clave.
- Beneficio: centraliza; rotar pasa a ser un cambio en 1 sitio.

### 🟡 Fase 2 — rotar credenciales contenidas
- **Acuity API key:** generar nueva en el panel de Acuity → `secrets.php`. Probar reservar/cancelar + ingesta.
- **`Success2026!`:** cambiar la cuenta demo → `secrets.php`.
- **Zoom JWT:** eliminar/neutralizar `own_ZoomAPIToken.php` (dead code; ya no se llama tras quitar patchZoom).

### 🔴 Fase 3 — rotar password de BD (ALTO RIESGO, con ventana + rollback)
- `ALTER USER 'moodle35'@'%' IDENTIFIED BY '<nueva>'` en MariaDB (contenedor `moodle35-db`).
- Actualizar: `config.php`, el **env del contenedor** (`DB_MOODLE_PASSWORD` en `~/tuspeaking-platform/services/moodle35-staging/.env`), y todos los scripts (ya deberían leer de `secrets.php` tras Fase 1).
- Reiniciar/verificar pieza a pieza. **Rollback:** revertir el ALTER USER a la clave anterior.

### 🔴 Fase 4 — limpiar histórico
- `git filter-repo` o BFG para borrar los literales de todos los commits.
- Force-push. Repo privado de 1 usuario → asumible, pero coordinar (re-clonar).
- Hacer SOLO cuando los ficheros ya no contengan secretos (post Fases 1-3).

## Notas
- Mientras no se rote: considerar Acuity key, password BD, Success2026 y Zoom JWT **comprometidas** (visibles para quien tenga acceso al repo).
- No mezclar con otras tareas: es delicado, merece foco.
