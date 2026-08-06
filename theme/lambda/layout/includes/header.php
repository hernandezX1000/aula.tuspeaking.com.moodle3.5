<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Parent theme: Bootstrapbase by Bas Brands
 * Built on: Essential by Julian Ridden
 *
 * @package   theme_lambda
 * @copyright 2018 redPIthemes
 *
 */

$login_link = theme_lambda_get_setting('login_link');
$login_custom_url = theme_lambda_get_setting('custom_login_link_url');
$login_custom_txt = theme_lambda_get_setting('custom_login_link_txt');
$home_button = theme_lambda_get_setting('home_button');
$shadow_effect = theme_lambda_get_setting('shadow_effect');
$auth_googleoauth2 = theme_lambda_get_setting('auth_googleoauth2');
$haslogo = (!empty($PAGE->theme->settings->logo));
$hasheaderprofilepic = (empty($PAGE->theme->settings->headerprofilepic)) ? false : $PAGE->theme->settings->headerprofilepic;
$moodle_global_search = 0;

$checkuseragent = '';
if (!empty($_SERVER['HTTP_USER_AGENT'])) {
    $checkuseragent = $_SERVER['HTTP_USER_AGENT'];
}
$username = get_string('username');
if (strpos($checkuseragent, 'MSIE 8')) {$username = str_replace("'", "&prime;", $username);}
?>

<?php if($PAGE->theme->settings->socials_position==1) { ?>
    	<div class="container-fluid socials-header"> 
    	<?php require_once(dirname(__FILE__).'/socials.php');?>
        </div>
<?php
} ?>

<header id="page-header" class="clearfix">              	
		<?php 
			if ($PAGE->theme->settings->page_centered_logo==0) {require_once(dirname(__FILE__).'/header_var1.php');}
			else {require_once(dirname(__FILE__).'/header_var2.php');}
		?>               
</header>
<header role="banner" class="navbar" <?php if (($PAGE->theme->settings->headercolor != "#ffffff") || (!empty($PAGE->theme->settings->header_background))) { ?> style="padding: 0;" <?php } ?>>
    <nav role="navigation" class="navbar-inner">
        <div class="container-fluid">
            <?php
                if ($home_button == 'shortname') { 
                    $home_button_string = '<a class="brand" href="'.$CFG->wwwroot.'">'.$SITE->shortname.'</a>'; 
                }
                else if ($home_button == 'home') { 
                    $home_button_string = '<a class="brand" href="'.$CFG->wwwroot.'">'.get_string('home').'</a>'; 
                }
                else if ($home_button == 'frontpagedashboard') { 
                    if (isloggedin()) {
                        $home_button_string = '<a class="brand" href="'.$CFG->wwwroot.'">'.get_string('mymoodle', 'admin').'</a>'; 
                    }
                    else {
                        $home_button_string = '<a class="brand" href="'.$CFG->wwwroot.'">'.get_string('frontpage', 'admin').'</a>'; 
                    }
                }
                else { // Fallback, should not happen
                    $home_button_string = '<a class="brand" href="'.$CFG->wwwroot.'">'.$SITE->shortname.'</a>'; 
                }
                echo $home_button_string;
            ?>
            <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </a>
            <div class="nav-collapse collapse">
                <?php echo $OUTPUT->custom_menu(); ?>
                <ul class="nav pull-right">
                    <li><?php echo $OUTPUT->page_heading_menu(); ?></li>
                </ul>
                
                <?php
				$moodle_release = $CFG->version;
				if ($moodle_release > 2015111610) {
					if (!empty($CFG->enableglobalsearch) && has_capability('moodle/search:query', context_system::instance())) {
						$moodle_global_search = 1;
					}
				}?>
                
                <form id="search" action="<?php if ($moodle_global_search) {echo $CFG->wwwroot.'/search/index.php';} else {echo $CFG->wwwroot.'/course/search.php';} ?>" >
                <div class="divider pull-left"></div>
                	<label for="coursesearchbox" class="lambda-sr-only"><?php if ($moodle_global_search) {echo get_string('search', 'search');} else {echo get_string('searchcourses');} ?></label>						
					<input id="coursesearchbox" type="text" onFocus="if(this.value =='<?php if ($moodle_global_search) {echo get_string('search', 'search');} else {echo get_string('searchcourses');} ?>' ) this.value=''" onBlur="if(this.value=='') this.value='<?php if ($moodle_global_search) {echo get_string('search', 'search');} else {echo get_string('searchcourses');} ?>'" value="<?php if ($moodle_global_search) {echo get_string('search', 'search');} else {echo get_string('searchcourses');} ?>" <?php if ($moodle_global_search) {echo 'name="q"';} else {echo 'name="search"';} ?> >
					<button type="submit"><span class="lambda-sr-only"><?php echo get_string('submit'); ?></span></button>						
				</form>
                
            </div>
        </div>
    </nav>
</header>

<?php if ($shadow_effect) {
	if ($moodle_release > 2017051500) { ?>
		<div class="container-fluid"><img src="<?php echo $OUTPUT->image_url('bg/lambda-shadow', 'theme'); ?>" class="lambda-shadow" alt=""></div>
    <?php } else { ?>
		<div class="container-fluid"><img src="<?php echo $OUTPUT->pix_url('bg/lambda-shadow', 'theme'); ?>" class="lambda-shadow" alt=""></div>
<?php }} ?>