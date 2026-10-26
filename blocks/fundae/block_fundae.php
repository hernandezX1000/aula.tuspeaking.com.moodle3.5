<?php
defined('MOODLE_INTERNAL') || die();

class block_fundae extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_fundae');
    }

    public function get_content() {
        global $DB, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';

        // Vista AUDITOR FUNDAE (ver TICKET-FUNDAE-01, Fase 1).
        // Fuente: mdl_fundae filtrada a bonificables (solo Moodle 3.5; lo que esté en Learn
        // no vive aquí y no debe aparecer). Enriquecida con fundae_oficial_stg (FUENTE DE LA
        // VERDAD del portal FUNDAE) por LEFT JOIN sobre c_fundae:
        //   - id_grupo  -> el "ID" de FUNDAE (sale en blanco si esa acción aún no reconcilia)
        //   - denominación / modalidad / fechas -> COALESCE(oficial, interno)  [corrige modalidad]
        // OJO: fundae_oficial_stg NO lleva prefijo Moodle -> se referencia SIN llaves {}.
        $sql = "SELECT f.*,
                       o.id_grupo AS id_grupo,
                       COALESCE(o.denominacion_oficial, f.denominacion_oficial, c.shortname, c.fullname) AS denom_final,
                       COALESCE(o.modalidad, f.modalidad) AS modalidad_final,
                       COALESCE(o.fecha_inicio, f.fecha_inicio) AS inicio_final,
                       COALESCE(o.fecha_fin, f.fecha_fin) AS fin_final,
                       c.fullname AS nombre_curso, c.shortname AS nombre_corto
                  FROM {fundae} f
                  JOIN {course} c ON f.courseid = c.id
                  LEFT JOIN fundae_oficial_stg o ON o.c_fundae = f.c_fundae
                 WHERE f.bonificable = 1 AND f.validado = 1
                 ORDER BY f.empresa, f.c_fundae";
        $cursos = $DB->get_records_sql($sql);

        // Opciones de filtro (empresa / modalidad) tomadas de lo que realmente se muestra.
        $empresas = array();
        $modalidades = array();
        foreach ($cursos as $r) {
            if (!empty($r->empresa))        $empresas[$r->empresa] = true;
            if (!empty($r->modalidad_final)) $modalidades[$r->modalidad_final] = true;
        }
        ksort($empresas);
        ksort($modalidades);
        $empresas = array_keys($empresas);
        $modalidades = array_keys($modalidades);

        $teal = '#00bcd4';
        $teal_dark = '#008ba3';
        $teal_light = '#f7f9fa';
        $uid = 'fundae_' . uniqid();

        $html = '<style>.ts-fundae-panel{font-size:.9375rem}.ts-fundae-panel .ts-tabla{width:100%;border-collapse:collapse;font-size:.875rem}.ts-fundae-panel .ts-tabla thead tr{background:#008ba3;color:#fff}.ts-fundae-panel .ts-tabla th{padding:.5rem;font-weight:600;white-space:nowrap}.ts-fundae-panel .ts-tabla td{padding:.5rem;border-bottom:1px solid #dee2e6;vertical-align:middle}.ts-fundae-panel .ts-tabla tbody tr:hover{background:#e0f7fa}.ts-fundae-panel .ts-sec{font-size:.8125rem;color:#6c757d}.ts-fundae-panel .ts-num{font-variant-numeric:tabular-nums}.ts-fundae-panel .ts-ver{color:#008ba3;font-weight:600;text-decoration:none}.ts-fundae-panel .ts-ver:hover{color:#006b7f;text-decoration:underline}</style><div class="ts-fundae-panel" style="padding:4px 0;">';
        // T-279e (26/10/2026): actas SEPE 10/06 [P2] y 14/10 [2] — ver con antelación y entrar en directo en las tutorías.
        $html .= '<div style="margin:0 0 10px;"><a class="btn btn-primary" href="' . (new moodle_url('/tutorias_programadas_todas.php'))->out() . '">'
               . 'Tutorías programadas · todas las acciones (ver y entrar en directo)</a></div>';

        $html .= '<div class="card card-body p-2 mb-2" style="display:flex;flex-direction:row;flex-wrap:wrap;gap:.5rem;align-items:center;">';
        $html .= '<input type="text" id="' . $uid . '_search" placeholder="Buscar por ID, acción/grupo, empresa, CIF o denominación…" class="form-control" style="flex:1;min-width:200px;width:auto;">';
        $html .= '<select id="' . $uid . '_empresa" class="custom-select" style="width:auto;"><option value="">Todas las empresas</option>';
        foreach ($empresas as $e) $html .= '<option value="' . htmlspecialchars($e) . '">' . htmlspecialchars($e) . '</option>';
        $html .= '</select>';
        $html .= '<select id="' . $uid . '_modalidad" class="custom-select" style="width:auto;"><option value="">Todas modalidades</option>';
        foreach ($modalidades as $m) $html .= '<option value="' . htmlspecialchars($m) . '">' . htmlspecialchars($m) . '</option>';
        $html .= '</select>';
        $html .= '<span id="' . $uid . '_count" style="color:' . $teal_dark . ';font-weight:bold;">' . count($cursos) . ' acciones</span>';
        $html .= '</div>';

        if (!empty($cursos)) {
            $html .= '<div style="overflow-x:auto;"><table id="' . $uid . '_table" class="ts-tabla">';
            $html .= '<thead><tr>';
            $html .= '<th class="text-center">ID</th>';
            $html .= '<th class="text-center">Acción/Grupo</th>';
            $html .= '<th class="text-center">Tipo de acción</th>';
            $html .= '<th>Denom.</th>';
            $html .= '<th class="text-center">Inicio</th>';
            $html .= '<th class="text-center">Fin</th>';
            $html .= '<th>Empresa</th>';
            $html .= '<th>Razón Social</th>';
            $html .= '<th class="text-center">CIF</th>';
            $html .= '<th class="text-center">Modalidad</th>';
            $html .= '<th class="text-center">Ver</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($cursos as $r) {
                $url = $CFG->wwwroot . '/course/view.php?id=' . $r->courseid;
                $inicio = $r->inicio_final ? date('d/m/Y', strtotime($r->inicio_final)) : '-';
                $fin    = $r->fin_final ? date('d/m/Y', strtotime($r->fin_final)) : '-';

                // Acción/Grupo al estilo FUNDAE (AFF/Grupo con "/").
                $accgrupo = !empty($r->c_fundae) ? str_replace('-', '/', $r->c_fundae) : '-';

                // ID de grupo FUNDAE; en blanco (muted) si aún no reconcilia con el oficial.
                $id_html = !empty($r->id_grupo)
                    ? '<strong>' . htmlspecialchars($r->id_grupo) . '</strong>'
                    : '<span class="text-muted">—</span>';

                // Tipo de acción: Propia / Externa (derivado de gestion_bonificacion).
                if (empty($r->gestion_bonificacion)) {
                    $tipo = '-';
                } else if (stripos($r->gestion_bonificacion, 'externa') !== false) {
                    $tipo = 'Externa';
                } else {
                    $tipo = 'Propia';
                }

                $html .= '<tr data-empresa="' . htmlspecialchars($r->empresa) . '" data-modalidad="' . htmlspecialchars($r->modalidad_final ?: '') . '">';
                $html .= '<td class="text-center ts-num">' . $id_html . '</td>';
                // El buscador filtra por el texto de la fila. FUNDAE escribe el grupo con guion (070-01) en sus
                // exportaciones y aquí se enseña con barra (070/01): se añade la forma con guion, oculta, para que
                // el inspector lo encuentre escriba como lo escriba (T-180, 14/10/2026).
                $accgrupo_guion = !empty($r->c_fundae) ? '<span style="display:none;"> ' . htmlspecialchars($r->c_fundae) . '</span>' : '';
                $html .= '<td class="text-center ts-num" style="font-weight:600;color:' . $teal_dark . ';">' . htmlspecialchars($accgrupo) . $accgrupo_guion . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($tipo) . '</td>';
                $html .= '<td>' . htmlspecialchars($r->denom_final) . '</td>';
                $html .= '<td class="text-center ts-num">' . $inicio . '</td>';
                $html .= '<td class="text-center ts-num">' . $fin . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($r->empresa) . '</strong></td>';
                $html .= '<td class="ts-sec">' . htmlspecialchars($r->razon_social ?: '-') . '</td>';
                $html .= '<td class="text-center ts-num ts-sec">' . htmlspecialchars($r->cif ?: '-') . '</td>';
                $html .= '<td class="text-center ts-num">' . htmlspecialchars($r->modalidad_final ?: '-') . '</td>';
                $html .= '<td class="text-center"><a href="' . $url . '" target="_blank" class="ts-ver">Ver</a></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';

            $html .= '<script>(function(){var s=document.getElementById("' . $uid . '_search"),e=document.getElementById("' . $uid . '_empresa"),m=document.getElementById("' . $uid . '_modalidad"),c=document.getElementById("' . $uid . '_count"),t=document.getElementById("' . $uid . '_table"),r=t.querySelectorAll("tbody tr");function f(){var q=s.value.toLowerCase(),ev=e.value,mv=m.value,v=0;r.forEach(function(x){var tx=x.textContent.toLowerCase(),ms=q===""||tx.indexOf(q)!==-1,me=ev===""||x.dataset.empresa===ev,mm=mv===""||x.dataset.modalidad===mv;if(ms&&me&&mm){x.style.display="";v++}else x.style.display="none"});c.textContent=v+" acciones"}s.addEventListener("input",f);e.addEventListener("change",f);m.addEventListener("change",f);})();</script>';
        }

        $html .= '</div>';
        $this->content->text = $html;
        return $this->content;
    }

    public function applicable_formats() { return array('all' => true); }
    public function instance_allow_multiple() { return false; }
    public function has_config() { return false; }
}
