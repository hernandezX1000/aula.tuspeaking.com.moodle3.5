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

        $html = '<div style="padding:10px;font-family:Arial,sans-serif;">';

        $html .= '<div style="background:#fff;border:1px solid #ddd;padding:10px;margin-bottom:10px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">';
        $html .= '<input type="text" id="' . $uid . '_search" placeholder="Buscar por ID, acción/grupo, empresa, CIF o denominación…" style="flex:1;min-width:200px;padding:6px;border:1px solid #ccc;border-radius:4px;">';
        $html .= '<select id="' . $uid . '_empresa" style="padding:6px;border:1px solid #ccc;border-radius:4px;"><option value="">Todas las empresas</option>';
        foreach ($empresas as $e) $html .= '<option value="' . htmlspecialchars($e) . '">' . htmlspecialchars($e) . '</option>';
        $html .= '</select>';
        $html .= '<select id="' . $uid . '_modalidad" style="padding:6px;border:1px solid #ccc;border-radius:4px;"><option value="">Todas modalidades</option>';
        foreach ($modalidades as $m) $html .= '<option value="' . htmlspecialchars($m) . '">' . htmlspecialchars($m) . '</option>';
        $html .= '</select>';
        $html .= '<span id="' . $uid . '_count" style="color:' . $teal_dark . ';font-weight:bold;">' . count($cursos) . ' acciones</span>';
        $html .= '</div>';

        if (!empty($cursos)) {
            $html .= '<div style="overflow-x:auto;"><table id="' . $uid . '_table" style="width:100%;border-collapse:collapse;font-size:12px;">';
            $html .= '<thead><tr style="background:' . $teal . ';color:white;">';
            $html .= '<th style="padding:8px;text-align:center;">ID</th>';
            $html .= '<th style="padding:8px;text-align:center;">Acción/Grupo</th>';
            $html .= '<th style="padding:8px;text-align:center;">Tipo de acción</th>';
            $html .= '<th style="padding:8px;text-align:left;">Denom.</th>';
            $html .= '<th style="padding:8px;text-align:center;">Inicio</th>';
            $html .= '<th style="padding:8px;text-align:center;">Fin</th>';
            $html .= '<th style="padding:8px;text-align:left;">Empresa</th>';
            $html .= '<th style="padding:8px;text-align:left;">Razón Social</th>';
            $html .= '<th style="padding:8px;text-align:center;">CIF</th>';
            $html .= '<th style="padding:8px;text-align:center;">Modalidad</th>';
            $html .= '<th style="padding:8px;text-align:center;">Ver</th>';
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
                    : '<span style="color:#ccc;">—</span>';

                // Tipo de acción: Propia / Externa (derivado de gestion_bonificacion).
                if (empty($r->gestion_bonificacion)) {
                    $tipo = '-';
                } else if (stripos($r->gestion_bonificacion, 'externa') !== false) {
                    $tipo = 'Externa';
                } else {
                    $tipo = 'Propia';
                }

                $html .= '<tr style="border-bottom:1px solid #eee;" data-empresa="' . htmlspecialchars($r->empresa) . '" data-modalidad="' . htmlspecialchars($r->modalidad_final ?: '') . '">';
                $html .= '<td style="padding:8px;text-align:center;font-family:monospace;">' . $id_html . '</td>';
                // El buscador filtra por el texto de la fila. FUNDAE escribe el grupo con guion (070-01) en sus
                // exportaciones y aquí se enseña con barra (070/01): se añade la forma con guion, oculta, para que
                // el inspector lo encuentre escriba como lo escriba (T-180, 14/10/2026).
                $accgrupo_guion = !empty($r->c_fundae) ? '<span style="display:none;"> ' . htmlspecialchars($r->c_fundae) . '</span>' : '';
                $html .= '<td style="padding:8px;text-align:center;font-weight:bold;color:' . $teal_dark . ';">' . htmlspecialchars($accgrupo) . $accgrupo_guion . '</td>';
                $html .= '<td style="padding:8px;text-align:center;">' . htmlspecialchars($tipo) . '</td>';
                $html .= '<td style="padding:8px;font-size:11px;">' . htmlspecialchars($r->denom_final) . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;">' . $inicio . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;">' . $fin . '</td>';
                $html .= '<td style="padding:8px;"><strong>' . htmlspecialchars($r->empresa) . '</strong></td>';
                $html .= '<td style="padding:8px;font-size:11px;color:#555;">' . htmlspecialchars($r->razon_social ?: '-') . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;font-family:monospace;">' . htmlspecialchars($r->cif ?: '-') . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;">' . htmlspecialchars($r->modalidad_final ?: '-') . '</td>';
                $html .= '<td style="padding:8px;text-align:center;"><a href="' . $url . '" target="_blank" style="color:' . $teal . ';text-decoration:none;font-weight:bold;">Ver</a></td>';
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
