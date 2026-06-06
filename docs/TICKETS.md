# Tickets de desarrollo — aula.tuspeaking.com

Pendientes técnicos. Marcar [x] al completar.

---

## TICKET #1 — Sacar credenciales de BD del código (ALTA)

**Estado:** [ ] Pendiente
**Prioridad:** Alta

**Problema:** La contraseña de la BD está escrita en duro (`new mysqli('localhost','moodle35','<password>','aulatuspeaking35')` o PDO equivalente) en ~51 ficheros propios. Además quedó en el historial de Git que se subió a GitHub (commit 7f48653), aunque el repo es privado.

**Ficheros afectados:** feedback/*, empresas/*, evaluaciones/*, portal/rrhh.php, reportes/admin.php, reportes_1to1/reporte_1to1.py, varios reportes_cesce/*, misclases.php, tutorias_con_profesor.php, webhook_jotform_evaluaciones.php. (Lista completa: `grep -rl "<password>" .` en las carpetas propias.)

**Tareas:**
- [ ] Crear `secrets.php` fuera del repo (ya está en .gitignore vía config.php pattern; verificar)
- [ ] Reemplazar la contraseña en duro por `require secrets.php` + constante, en los 51 ficheros
- [ ] Probar que las páginas afectadas siguen funcionando
- [ ] Una vez todo usa secrets.php, ROTAR la contraseña de BD en MySQL y actualizar secrets.php + config.php
- [ ] (Opcional) Limpiar el historial de Git con git filter-repo para borrar el rastro

**Nota:** Rotar la contraseña es lo que de verdad cierra el riesgo. Limpiar el historial sin rotar no sirve de mucho.

---

## TICKET #2 — Ficheros grandes en el repo (MEDIA)

**Estado:** [ ] Pendiente
**Prioridad:** Media

**Problema:** El repo pesa ~870 MB por los .pptx de `contenido/business_english/` (varios de 50-93 MB). GitHub avisa de que superan los 50 MB recomendados. Ralentiza clones y pushes.

**Opciones:**
- [ ] Opción A: configurar Git LFS para `contenido/**/*.pptx` (si se quieren versionar)
- [ ] Opción B: excluir `contenido/business_english/*.pptx` del repo y respaldarlos por otra vía (son contenido, no código)

---

## TICKET #3 — Token de GitHub expuesto (ALTA)

**Estado:** [ ] Pendiente
**Prioridad:** Alta

**Problema:** Un Personal Access Token con permiso `repo` se usó en texto plano y quedó expuesto. Sigue activo en ~/.git-credentials.

**Tareas:**
- [ ] Crear un token nuevo (sin compartirlo en ningún sitio)
- [ ] Actualizar ~/.git-credentials con el token nuevo
- [ ] Revocar el token expuesto en https://github.com/settings/tokens
