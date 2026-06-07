# Receta de auditoría periódica del hosting

Comprobaciones de salud del servidor aula.tuspeaking.com.
Recomendado: ejecutar mensualmente. Conectar por SSH y lanzar cada bloque.

> Nota: la contraseña de BD lleva `!`, usar comillas simples. Pendiente de
> moverla a ~/.my.cnf (ver TICKETS.md #1).

## 1. Salud del sistema

    uptime          # load average: si supera el nº de CPUs, hay sobrecarga
    free -h         # RAM: vigilar que quede 'available'; OJO swap en 0
    df -h /         # disco: vigilar a partir del 85%
    top -bn1 | head -20

## 2. Espacio: dónde pesa el disco

    du -h --max-depth=1 /home/aulatuspeaking/www/app/moodle/data | sort -rh | head -12
    # filedir = archivos reales de cursos (NO borrar a mano)

## 3. Basura y caché

    # .bak (se pueden borrar, ya hay Git):
    find /home/aulatuspeaking/www/app/moodle -maxdepth 2 -name "*.bak*" | wc -l
    # cache de Moodle (normal < 50MB / pocos miles de ficheros):
    du -sh /home/aulatuspeaking/www/app/moodle/data/cache
    # Purgar cache de forma SEGURA (nunca con rm):
    php /home/aulatuspeaking/www/app/moodle/admin/cli/purge_caches.php

## 4. Velocidad web (lanzar desde el Mac, sin SSH)

    curl -o /dev/null -s -w "Total: %{time_total}s  TTFB: %{time_starttransfer}s\n" https://aula.tuspeaking.com
    # TTFB < 0.2s = muy bueno; > 1-2s = lento

## 5. Cron de Moodle

    mysql -u moodle35 -p'<password>' aulatuspeaking35 -e "SELECT FROM_UNIXTIME(MAX(lastruntime)) FROM mdl_task_scheduled;"
    # Debe ser de hoy/ayer. Tareas que fallan:
    mysql -u moodle35 -p'<password>' aulatuspeaking35 -e "SELECT classname, faildelay FROM mdl_task_scheduled WHERE faildelay > 0;"

## 6. Backups

    crontab -l                                   # ver tareas programadas
    ls -lah /home/aulatuspeaking/backups/db_daily/ | tail -8
    # Confirmar backup de HOY:
    ls -la /home/aulatuspeaking/backups/db_daily/db_$(date +%Y%m%d).sql.gz

## Última auditoría: 2026-06-07 — TODO OK
- CPU ociosa, RAM holgada, disco 75% (filedir 247G legítimo)
- TTFB 88ms, cache limpia (13M), cron al día, backups diarios verificados
