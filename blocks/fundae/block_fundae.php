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

        $total = $DB->count_records('fundae');
        $bonificables = $DB->count_records('fundae', ['bonificable' => 1]);
        $bonificadas = $DB->count_records('fundae', ['accion_bonificada' => 1]);

        $sql = "SELECT f.*, c.fullname as nombre_curso, c.shortname as nombre_corto,
                (CASE 
                  WHEN f.c_fundae IS NULL OR f.c_fundae = '' 
                  THEN (SELECT COUNT(DISTINCT ue.userid) 
                        FROM {enrol} e 
                        JOIN {user_enrolments} ue ON e.id = ue.enrolid
                        JOIN {user} u ON ue.userid = u.id
                        JOIN {role_assignments} ra ON ra.userid = u.id
                        JOIN {context} ctx ON ra.contextid = ctx.id AND ctx.instanceid = c.id AND ctx.contextlevel = 50
                        JOIN {role} r ON ra.roleid = r.id AND r.shortname = 'student'
                        WHERE e.courseid = c.id AND u.email NOT LIKE '%tuspeaking.com')
                  ELSE (SELECT COUNT(DISTINCT gm.userid) 
                        FROM {groups} g 
                        JOIN {groups_members} gm ON g.id = gm.groupid 
                        WHERE g.courseid = c.id AND g.name = f.c_fundae)
                END) as num_participantes,
                (SELECT COUNT(DISTINCT gm2.userid) FROM {groups} g2 JOIN {groups_members} gm2 ON g2.id = gm2.groupid WHERE g2.courseid = c.id AND g2.name = 'NO-FUNDAE') as num_no_fundae
                FROM {fundae} f
                JOIN {course} c ON f.courseid = c.id
                ORDER BY f.empresa, f.bonificable DESC, f.c_fundae";
        $cursos = $DB->get_records_sql($sql);

        $empresas = $DB->get_fieldset_sql("SELECT DISTINCT empresa FROM {fundae} WHERE empresa IS NOT NULL ORDER BY empresa");
        $modalidades = $DB->get_fieldset_sql("SELECT DISTINCT modalidad FROM {fundae} WHERE modalidad IS NOT NULL ORDER BY modalidad");
        
        $teal = '#00bcd4';
        $teal_dark = '#008ba3';
        $teal_light = '#f7f9fa';
        $uid = 'fundae_' . uniqid();
        
        $html = '<div style="padding:10px;font-family:Arial,sans-serif;">';
        $html .= '<div style="background:' . $teal_light . ';padding:12px;border-left:4px solid ' . $teal . ';margin-bottom:15px;">';
        $html .= '<strong style="color:' . $teal_dark . ';">FUNDAE 2026</strong> &mdash; ';
        $html .= '<span style="margin-right:15px;">Total: <strong>' . $total . '</strong></span>';
        $html .= '<span style="margin-right:15px;">Bonificables: <strong style="color:#00aa44;">' . $bonificables . '</strong></span>';
        $html .= '<span>Acciones bonificadas: <strong style="color:' . $teal_dark . ';">' . $bonificadas . '</strong></span>';
        $html .= '</div>';
        
        $html .= '<div style="background:#fff;border:1px solid #ddd;padding:10px;margin-bottom:10px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">';
        $html .= '<input type="text" id="' . $uid . '_search" placeholder="Buscar..." style="flex:1;min-width:200px;padding:6px;border:1px solid #ccc;border-radius:4px;">';
        $html .= '<select id="' . $uid . '_empresa" style="padding:6px;border:1px solid #ccc;border-radius:4px;"><option value="">Todas las empresas</option>';
        foreach ($empresas as $e) $html .= '<option value="' . htmlspecialchars($e) . '">' . htmlspecialchars($e) . '</option>';
        $html .= '</select>';
        $html .= '<select id="' . $uid . '_modalidad" style="padding:6px;border:1px solid #ccc;border-radius:4px;"><option value="">Todas modalidades</option>';
        foreach ($modalidades as $m) $html .= '<option value="' . htmlspecialchars($m) . '">' . htmlspecialchars($m) . '</option>';
        $html .= '</select>';
        $html .= '<select id="' . $uid . '_bonif" style="padding:6px;border:1px solid #ccc;border-radius:4px;"><option value="">Todos</option><option value="si">Bonificables</option><option value="no">No bonificables</option></select>';
        $html .= '<span id="' . $uid . '_count" style="color:' . $teal_dark . ';font-weight:bold;">' . count($cursos) . ' cursos</span>';
        $html .= '</div>';
        
        if (!empty($cursos)) {
            $html .= '<div style="overflow-x:auto;"><table id="' . $uid . '_table" style="width:100%;border-collapse:collapse;font-size:12px;">';
            $html .= '<thead><tr style="background:' . $teal . ';color:white;">';
            $html .= '<th style="padding:8px;text-align:left;">Empresa</th>';
            $html .= '<th style="padding:8px;text-align:left;">Razón Social</th>';
            $html .= '<th style="padding:8px;text-align:center;">CIF</th>';
            $html .= '<th style="padding:8px;text-align:center;">C/Fundae</th>';
            $html .= '<th style="padding:8px;text-align:center;">Grupo</th>';
            $html .= '<th style="padding:8px;text-align:left;">Denominación</th>';
            $html .= '<th style="padding:8px;text-align:center;">F. Inicio</th>';
            $html .= '<th style="padding:8px;text-align:center;">F. Fin</th>';
            $html .= '<th style="padding:8px;text-align:center;">Modalidad</th>';

            $html .= '<th style="padding:8px;text-align:center;">NO-FUNDAE</th>';
            $html .= '<th style="padding:8px;text-align:center;">Bonificable</th>';
                        $html .= '<th style="padding:8px;text-align:center;">Gestión</th>';
            $html .= '<th style="padding:8px;text-align:center;">URL</th>';
            $html .= '</tr></thead><tbody>';
            
            foreach ($cursos as $r) {
                $rowstyle = '';
                if (!$r->bonificable) $rowstyle = 'background:#fef5f5;color:#999;';
                
                $url = $CFG->wwwroot . '/course/view.php?id=' . $r->courseid;
                $fecha_inicio = $r->fecha_inicio ? date('d/m/Y', strtotime($r->fecha_inicio)) : '-';
                $fecha_fin = $r->fecha_fin ? date('d/m/Y', strtotime($r->fecha_fin)) : '-';
                
                $bonif_html = $r->bonificable ? '<span style="color:#00aa44;font-weight:bold;">SÍ</span>' : '<span style="color:#cc0000;font-weight:bold;">NO</span>';
                $accion_html = $r->accion_bonificada ? '<span style="color:#00aa44;font-weight:bold;">SÍ</span>' : '<span style="color:#999;">NO</span>';
                $gestion_html = $r->gestion_bonificacion ? '<span style="background:' . $teal_light . ';padding:2px 6px;border-radius:3px;font-size:10px;">' . htmlspecialchars($r->gestion_bonificacion) . '</span>' : '-';
                

                
                $data_bonif = $r->bonificable ? 'si' : 'no';
                
                $html .= '<tr style="border-bottom:1px solid #eee;' . $rowstyle . '" data-empresa="' . htmlspecialchars($r->empresa) . '" data-modalidad="' . htmlspecialchars($r->modalidad ?: '') . '" data-bonif="' . $data_bonif . '">';
                $html .= '<td style="padding:8px;"><strong>' . htmlspecialchars($r->empresa) . '</strong></td>';
                $html .= '<td style="padding:8px;font-size:11px;color:#555;">' . htmlspecialchars($r->razon_social ?: '-') . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;font-family:monospace;">' . htmlspecialchars($r->cif ?: '-') . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-weight:bold;color:' . $teal_dark . ';">' . htmlspecialchars($r->c_fundae ?: '-') . '</td>';
                $html .= '<td style="padding:8px;text-align:center;">' . htmlspecialchars($r->grupo ?: '-') . '</td>';
                $html .= '<td style="padding:8px;font-size:11px;">' . htmlspecialchars($r->denominacion_oficial ?: $r->nombre_corto ?: $r->nombre_curso) . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;">' . $fecha_inicio . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;">' . $fecha_fin . '</td>';
                $html .= '<td style="padding:8px;text-align:center;font-size:11px;">' . htmlspecialchars($r->modalidad ?: '-') . '</td>';

                $no_fundae_html = $r->num_no_fundae > 0 ? '<span style="background:#ff9800;color:white;padding:2px 8px;border-radius:10px;font-weight:bold;">' . $r->num_no_fundae . '</span>' : '<span style="color:#ccc;">-</span>';
                $html .= '<td style="padding:8px;text-align:center;">' . $no_fundae_html . '</td>';
                $html .= '<td style="padding:8px;text-align:center;">' . $bonif_html . '</td>';
                                $html .= '<td style="padding:8px;text-align:center;">' . $gestion_html . '</td>';
                $html .= '<td style="padding:8px;text-align:center;"><a href="' . $url . '" target="_blank" style="color:' . $teal . ';text-decoration:none;font-weight:bold;">Ver</a></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
            
            $html .= '<script>(function(){var s=document.getElementById("' . $uid . '_search"),e=document.getElementById("' . $uid . '_empresa"),m=document.getElementById("' . $uid . '_modalidad"),b=document.getElementById("' . $uid . '_bonif"),c=document.getElementById("' . $uid . '_count"),t=document.getElementById("' . $uid . '_table"),r=t.querySelectorAll("tbody tr");function f(){var q=s.value.toLowerCase(),ev=e.value,mv=m.value,bv=b.value,v=0;r.forEach(function(x){var tx=x.textContent.toLowerCase(),ms=q===""||tx.indexOf(q)!==-1,me=ev===""||x.dataset.empresa===ev,mm=mv===""||x.dataset.modalidad===mv,mb=bv===""||x.dataset.bonif===bv;if(ms&&me&&mm&&mb){x.style.display="";v++}else x.style.display="none"});c.textContent=v+" cursos"}s.addEventListener("input",f);e.addEventListener("change",f);m.addEventListener("change",f);b.addEventListener("change",f);})();</script>';
        }

        $html .= '</div>';
        $this->content->text = $html;
        return $this->content;
    }

    public function applicable_formats() { return array('all' => true); }
    public function instance_allow_multiple() { return false; }
    public function has_config() { return false; }
}
