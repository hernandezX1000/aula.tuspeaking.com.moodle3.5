#!/bin/bash
cd /home/aulatuspeaking/www/app/moodle/reportes_1to1
export PYTHONPATH=/home/aulatuspeaking/.local/lib/python3.9/site-packages:$PYTHONPATH
python3 reporte_1to1.py "$@"
