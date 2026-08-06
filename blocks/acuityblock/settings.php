<?php
$settings->add(new admin_setting_heading(
            'headerconfig',
            get_string('headerconfig', 'block_acuityblock'),
            get_string('descconfig', 'block_acuityblock')
        ));
 
$settings->add(new admin_setting_configcheckbox(
            'acuityblock/Allow_HTML',
            get_string('labelallowhtml', 'block_acuityblock'),
            get_string('descallowhtml', 'block_acuityblock'),
            '0'
        ));