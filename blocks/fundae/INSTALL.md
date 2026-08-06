# Instalación de Bloque FUNDAE - Proceso Real

## Resumen
Documento que registra los pasos reales para instalar y hacer funcionar el bloque FUNDAE en Moodle 3.5.

## Pasos de instalación

### 1. Copiar bloque a Moodle
```bash
cp -r block_fundae_dev/fundae /home/aulatuspeaking/.ftp-users/moodle/blocks/
```

### 2. Detectar el plugin en Moodle
```bash
php admin/cli/upgrade.php --non-interactive
```
O desde web: Admin → Notificaciones → Instalar.

### 3. Crear capabilities (CRÍTICO)
Sin estas capabilities el bloque NO aparece en la lista de "Agregar bloque":

```sql
INSERT INTO mdl_capabilities (name, captype, contextlevel, component, riskbitmask) VALUES
('block/fundae:myaddinstance', 'write', 10, 'block_fundae', 0),
('block/fundae:addinstance', 'write', 80, 'block_fundae', 0),
('block/fundae:view', 'read', 80, 'block_fundae', 0);
```

### 4. Asignar permisos a roles
```sql
INSERT INTO mdl_role_capabilities (contextid, roleid, capability, permission, timemodified, modifierid)
SELECT 1, r.id, 'block/fundae:addinstance', 1, UNIX_TIMESTAMP(), 0
FROM mdl_role r WHERE r.shortname IN ('manager', 'editingteacher');

INSERT INTO mdl_role_capabilities (contextid, roleid, capability, permission, timemodified, modifierid)
SELECT 1, r.id, 'block/fundae:myaddinstance', 1, UNIX_TIMESTAMP(), 0
FROM mdl_role r WHERE r.shortname IN ('user', 'manager', 'editingteacher');

INSERT INTO mdl_role_capabilities (contextid, roleid, capability, permission, timemodified, modifierid)
SELECT 1, r.id, 'block/fundae:view', 1, UNIX_TIMESTAMP(), 0
FROM mdl_role r WHERE r.shortname IN ('user', 'manager', 'editingteacher', 'student', 'teacher');
```

### 5. Limpiar todas las cachés
```bash
rm /home/aulatuspeaking/www/app/moodle/data/cache/core_component.php
rm -rf /home/aulatuspeaking/www/app/moodle/data/cache/*
rm -rf /home/aulatuspeaking/www/app/moodle/data/localcache/*
rm -rf /home/aulatuspeaking/www/app/moodle/data/sessions/*
php admin/cli/purge_caches.php
```

### 6. Agregar bloque al usuario FUNDAE (área personal)
```sql
INSERT INTO mdl_block_instances (
  blockname, parentcontextid, showinsubcontexts, requiredbytheme,
  pagetypepattern, defaultregion, defaultweight, configdata, timecreated, timemodified
) VALUES (
  'fundae', 
  563849,   -- context ID del usuario Inspector FUNDAE (ID 5676)
  0, 0, 
  'my-index',  -- pagetypepattern del área personal
  'content',   -- región (NO usar 'side-pre' en my-index)
  0, '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
);
```

### 7. Eliminar welcome page duplicado del Inspector FUNDAE
```sql
-- Los bloques HTML con iframe de dashboard_welcome.php
DELETE FROM mdl_block_instances WHERE id IN (59082, 59161);
```

## Problemas encontrados y soluciones

### Problema 1: Bloque no aparece en "Agregar bloque"
**Causa:** Faltaba `block/fundae:view` capability.
**Diagnóstico:** Ejecutar `$block->is_empty()` daba error sobre `has_capability(NULL)`.

### Problema 2: Bloque visible solo en curso, no en área personal
**Causa:** Región `side-pre` no existe en layout `my-index`.
**Solución:** Cambiar a `content`.

### Problema 3: Caché de Moodle no se actualizaba
**Causa:** Moodle cachea componentes en `core_component.php`.
**Solución:** Eliminar manualmente ese archivo + purge_caches.

## Verificación
```bash
php -r "
define('CLI_SCRIPT', true);
require('/home/aulatuspeaking/.ftp-users/moodle/config.php');
require_once(\$CFG->libdir.'/blocklib.php');
\$blocks = core_component::get_plugin_list('block');
echo isset(\$blocks['fundae']) ? 'OK' : 'FAIL';
"
```

## Cambios en BD
- 1 tabla nueva: `mdl_fundae`
- 3 capabilities nuevas: `block/fundae:*`
- N permisos en `mdl_role_capabilities`
- 1 instancia de bloque en área personal Inspector FUNDAE
