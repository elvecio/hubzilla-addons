<?php
/**
 * Name: Smileybutton
 * Description: Adds a smileybutton to the Inputbox
 * Version: 0.1
 * Author: Johannes Schwab , Christian Vogeley
 * ToDo: Add this to comments, Allow to disable on webpages, nicer position of button
 * Maintainer: none
 */


function smileybutton_load() {

	/**
	 * 
	 * Register hooks for jot_tool and plugin_settings
	 *
	 */

	register_hook('jot_tool', 'addon/smileybutton/smileybutton.php', 'show_button');
	register_hook('feature_settings', 'addon/smileybutton/smileybutton.php', 'smileybutton_settings');
	register_hook('feature_settings_post', 'addon/smileybutton/smileybutton.php', 'smileybutton_settings_post');
 
}


function smileybutton_unload() {

	/**
	 *
	 * Delet registered hooks
	 *
	 */

	unregister_hook('jot_tool',    'addon/smileybutton/smileybutton.php', 'show_button');	
	unregister_hook('feature_settings', 'addon/smileybutton/smileybutton.php', 'smileybutton_settings');
	unregister_hook('feature_settings_post', 'addon/smileybutton/smileybutton.php', 'smileybutton_settings_post');
	 
}



function show_button($a, &$b) {

	/**
	 *
	 * Check if it is a local user and he has enabled smileybutton
	 *
	 */

	if(! local_channel()) {
		$nobutton = false;
	} else {
		$nobutton = get_pconfig(local_channel(), 'smileybutton', 'nobutton');
		$deactivated = get_pconfig(local_channel(), 'smileybutton', 'deactivated');
	}

	/**
	 *
	 * Prepare the Smilie-Arrays
	 *
	 */

	$s = list_smilies(true);

	/**
	 *
	 * Generate html for smileylist
	 *
	 */

	$html = "\t<table class=\"smiley-preview\"><tr>\n";
	for($x = 0; $x < count($s['texts']); $x ++) {
		$icon = $s['icons'][$x];
		$icon = str_replace('/>', 'onclick="smileybutton_addsmiley(\'' . $s['texts'][$x] . '\')"/>', $icon);
		$icon = str_replace('class="smiley"', 'class="smiley_preview"', $icon);
		$html .= "<td>" . $icon . "</td>";
		if (($x+1) % (sqrt(count($s['texts']))+1) == 0) {
			$html .= "</tr>\n\t<tr>";
		}
	}
	$html .= "\t</tr></table>\n";

	/**
	 *
	 * Add css to page
	 *
	 */	

	App::$page['htmlhead'] .= '<link rel="stylesheet"  type="text/css" href="' . z_root() . '/addon/smileybutton/smileybutton.css' . '" media="all" />' . "\r\n";

	/**
	 *
	 * Add the button to the Inputbox
	 *
	 */	
	if (! $nobutton and ! $deactivated) {
		$b .= "<div id=\"profile-smiley-wrapper\"  >\n";
		//$b .= "\t<img src=\"" . z_root() . "/addon/smileybutton/icon.gif\" onclick=\"toggle_smileybutton(); return false;\" alt=\"smiley\">\n";
		$b .= "\t<button class=\"btn btn-default btn-sm\" onclick=\"toggle_smileybutton(); return false;\"><i id=\"profile-smiley-button\" class=\"fa fa-smile-o jot-icons\" ></i></button>\n";
		$b .= "\t</div>\n";
	}

 
	/**
	 *
	 * Write the smileies to an (hidden) div
	 *
	 */
	if ($deactivated){
		return;
		}
	else
	{
		if ($nobutton) {
			$b .= "\t<div id=\"smileybutton\">\n";
		} else {
			$b .= "\t<div id=\"smileybutton\" style=\"display:none;\">\n";
		}
	}
	$b .= $html . "\n"; 
	$b .= "</div>\n";

	/**
	 *
	 * Function to show and hide the smiley-list in the hidden div
	 *
	 */

	$b .= "<script>\n"; 

	if (! $nobutton) {
		$b .= "	smileybutton_show = 0;\n";
		$b .= "	function toggle_smileybutton() {\n";
		$b .= "	if (! smileybutton_show) {\n";
		$b .= "		$(\"#smileybutton\").show();\n";
		$b .= "		smileybutton_show = 1;\n";
		$b .= "	} else {\n";
		$b .= "		$(\"#smileybutton\").hide();\n";
		$b .= "		smileybutton_show = 0;\n";
		$b .= "	}}\n";
	} 

	/**
	 *
	 * Function to add the chosen smiley to the inputbox
	 *
	 */

	$b .= "	function smileybutton_addsmiley(text) {\n";
	$b .= "		if(plaintext == 'none') {\n";
	$b .= "			var v = $(\"#profile-jot-text\").val();\n";
	$b .= "			v = v + text;\n";
	$b .= "			$(\"#profile-jot-text\").val(v);\n";
	$b .= "			$(\"#profile-jot-text\").focus();\n";
	$b .= "		} else {\n";
	$b .= "			var v = tinymce.activeEditor.getContent();\n";
	$b .= "			v = v + text;\n";
	$b .= "			tinymce.activeEditor.setContent(v);\n";
	$b .= "			tinymce.activeEditor.focus();\n";
	$b .= "		}\n";
	$b .= "	}\n";
	$b .= "</script>\n";
}





/**
 *
 * Set the configuration
 *
 */

function smileybutton_settings_post($a,$post) {
	if(! local_channel())
		return;
	if($_POST['smileybutton-submit'])
		set_pconfig(local_channel(),'smileybutton','nobutton',intval($_POST['smileybutton']));
		set_pconfig(local_channel(),'smileybutton','deactivated',intval($_POST['deactivated']));

}


/**
 *
 * Add configuration-dialog to form
 *
 */


function smileybutton_settings(&$a,&$s) {

	if(! local_channel())
		return;

	/* Add our stylesheet to the page so we can make our settings look nice */

	//App::$page['htmlhead'] .= '<link rel="stylesheet"  type="text/css" href="' . z_root() . '/addon/smileybutton/smileybutton.css' . '" media="all" />' . "\r\n";

	/* Get the current state of our config variable */

	$nobutton = get_pconfig(local_channel(),'smileybutton','nobutton');
	$checked['nobutton'] = (($nobutton) ? 1 : false);
	$deactivated = get_pconfig(local_channel(),'smileybutton','deactivated');
	$checked['deactivated'] = (($deactivated) ? 1 : false);
	/* Add some HTML to the existing form */

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('deactivated', t('Deactivate the feature'), $checked['deactivated'], '', array(t('No'),t('Yes'))),
	));

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('smileybutton', t('Hide the button and show the smilies directly.'), $checked['nobutton'], '', array(t('No'),t('Yes'))),
	));

	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('smileybutton', t('Smileybutton Settings'), '', t('Submit')),
		'$content'	=> $sc
	));
}

