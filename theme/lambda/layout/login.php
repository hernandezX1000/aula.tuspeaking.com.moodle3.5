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
 
$custom_login=$PAGE->theme->settings->custom_login;
$login_attr = "";
if ($custom_login==1) {$login_attr = "login_lambda";}
$centered_logo=$PAGE->theme->settings->page_centered_logo;
if (($custom_login==1) && ($centered_logo==1)) {$login_attr .= " centered_logo";}
$haslogo = (!empty($PAGE->theme->settings->logo));

echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google web fonts -->
    <?php require_once(dirname(__FILE__).'/includes/fonts.php'); ?>
</head>

<body <?php echo $OUTPUT->body_attributes("$login_attr"); ?>>

<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div id="wrapper" style="background: transparent none repeat scroll 0 0; border: medium none;" <?php if ($custom_login==1) {echo 'style="background: transparent none repeat scroll 0 0; border: medium none;"';} ?> >

<?php if ($custom_login==0) {?>
<?php require_once(dirname(__FILE__).'/includes/header.php'); ?>
<?php } else { ?>

<div id ="page-header-nav" class="clearfix" style="color:rgb(0,177,198)">
       
    <div class="container-fluid">    
    <div class="row-fluid">
    <!-- HEADER: LOGO AREA -->
        	
            <?php if (!$haslogo) { ?>
            	<div class="span6">
              		<h1 id="title" style="line-height: 2em"><?php echo $SITE->fullname; ?></h1>
                </div>
            <?php } else { ?>
                <div class="logo-header">
                	<a class="logo" href="<?php echo $CFG->wwwroot; ?>" title="<?php print_string('home'); ?>">
                    <?php 
					echo html_writer::empty_tag('img', array('src'=>$PAGE->theme->setting_file_url('logo', 'logo'), 'class'=>'logo', 'alt'=>'logo'));
					?>
                    </a>
                </div>
            <?php } ?> 
            
    </div>
    </div>
               
</div>

<?php } ?>

<div id="page" class="container-fluid">
    <div id="page-content" class="row-fluid" <?php if ($custom_login==0) {echo 'style="background-clip:padding-box;border: 8px solid rgba(255, 255, 255, 0.35);background-color: rgba(255, 255, 255, 0.85);border-radius: 3px;"';}?>>
		<section id="region-main" class="span4 offset4" style="max-width:450px;background-clip:padding-box;border: 8px solid rgba(255, 255, 255, 0.35);background-color: rgba(255, 255, 255, 0.85);border-radius: 3px;">
        
            <?php
            echo $OUTPUT->course_content_header();
            echo $OUTPUT->main_content();
            echo $OUTPUT->course_content_footer();
            ?>
        </section>
    </div>
    
    <a href="#top" class="back-to-top"><i class="fa fa-chevron-circle-up fa-3x"></i><span class="lambda-sr-only"><?php echo get_string('back'); ?></span></a>
    
</div>

	<footer id="page-footer" class="container-fluid" style="display:none;"<?php if ($custom_login==1) echo 'style="display:none;"';?>>
		<?php require_once(dirname(__FILE__).'/includes/footer.php'); echo $OUTPUT->login_info();?>
	</footer>

    <?php echo $OUTPUT->standard_end_of_body_html() ?>

<!--[if lte IE 9]>
<script src="<?php echo $CFG->wwwroot;?>/theme/lambda/javascript/ie/iefix.js"></script>
<![endif]-->

</body>
</html>