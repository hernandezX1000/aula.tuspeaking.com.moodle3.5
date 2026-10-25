<?php
/**
 * cancelAcuity.php — webhook «Appointment Canceled» de Acuity.
 *
 * SEC-12 (25-oct-2026, T-279). La versión de 2022 (fuera de git, con la clave de Acuity y la conexión a la BD escritas)
 * metía el id recibido tal cual en el SQL (inyección sin sesión), no comprobaba que la cita estuviera cancelada en
 * Acuity (cualquiera podía «cancelar» la clase de otro) y en esta instalación ni siquiera conectaba con la BD.
 * Ahora hace exactamente lo mismo que newAcuity.php: consulta la cita a Acuity y aplica su estado real (si está
 * cancelada, ING-9: eventos ocultos y own_acuity / i3code_acuityZoom marcados). Una petición falsa solo puede igualar
 * el aula a lo que Acuity ya tiene.
 */
require __DIR__ . '/newAcuity.php';
