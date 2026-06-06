#!/bin/bash
cd /home/aulatuspeaking/www/app/moodle/reportes_cesce/
umask 000
export PYTHONPATH=/home/aulatuspeaking/.local/lib/python3.9/site-packages
export HOME=/tmp
/usr/bin/python3 /home/aulatuspeaking/www/app/moodle/reportes_cesce/reporte_asistencia_cesce.py "$1" "$2" 2>&1
