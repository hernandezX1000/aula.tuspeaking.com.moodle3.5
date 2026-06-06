#!/bin/bash
# Elimina reportes de más de 7 días
find /home/aulatuspeaking/www/app/moodle/reportes_cesce/ -name "Reporte_*.xlsx" -mtime +7 -delete
echo "$(date): Limpieza completada" >> /home/aulatuspeaking/www/app/moodle/reportes_cesce/limpieza.log
