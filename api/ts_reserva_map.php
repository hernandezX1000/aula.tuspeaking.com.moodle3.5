<?php
/**
 * ts_reserva_map.php — Mapa de courseid (Moodle) → URL DIRECTA de reserva en Acuity
 * Pega aquí, tal cual, el “Direct Client Scheduling Link” de cada tipo en Acuity.
 * - Para citas: suele ser https://tuspeaking.as.me/schedule.php?appointmentType=XXXXXXXX
 * - Para clases/eventos (group classes): Acuity puede dar otro formato/slug. Pega la URL tal cual.
 *
 * NOTA: No cierres con "?>" al final. Devuelve un array asociativo.
 */
return [

    // === EJEMPLOS REALES ===
    // Francés Principiante (courseid 2660)
    2660 => 'https://tuspeaking.as.me/schedule.php?appointmentType=83307280',

    // === AÑADE TUS CURSOS AQUÍ (COPIA/PEGA Y EDITA) ===
    // 3001 => 'https://tuspeaking.as.me/schedule.php?appointmentType=90000001', // cita individual
    // 3002 => 'https://tuspeaking.as.me/schedule.php?appointmentType=90000002', // cita individual
    // 3100 => 'https://tuspeaking.as.me/schedule/some-class-slug',             // clase/grupo (URL con slug)
    // 3200 => 'https://tuspeaking.as.me/schedule.php?appointmentType=91234567', // otro curso

    // Puedes repetir la misma URL para varios cursos si comparten el mismo tipo de reserva:
    // 3301 => 'https://tuspeaking.as.me/schedule.php?appointmentType=83307280',
    // 3302 => 'https://tuspeaking.as.me/schedule.php?appointmentType=83307280',

];
