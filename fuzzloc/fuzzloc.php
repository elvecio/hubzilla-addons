<?php
/**
 * Name: Fuzzy Location
 * Description: If you have browser location enabled for your posts, provide a bit of fuzziness to your actual location
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */


function fuzzloc_load() {

	/**
	 * 
	 * Our plugin will attach in three places.
	 * The first is just prior to storing a local post.
	 *
	 */

	register_hook('post_local', 'addon/fuzzloc/fuzzloc.php', 'fuzzloc_post_hook');

	/**
	 *
	 * Then we'll attach into the plugin settings page, and also the 
	 * settings post hook so that we can create and update
	 * user preferences.
	 *
	 */

	register_hook('feature_settings', 'addon/fuzzloc/fuzzloc.php', 'fuzzloc_settings');
	register_hook('feature_settings_post', 'addon/fuzzloc/fuzzloc.php', 'fuzzloc_settings_post');

	logger("loaded fuzzloc");
}


function fuzzloc_unload() {

	/**
	 *
	 * unload unregisters any hooks created with register_hook
	 * during load. It may also delete configuration settings
	 * and any other cleanup.
	 *
	 */

	unregister_hook('post_local',    'addon/fuzzloc/fuzzloc.php', 'fuzzloc_post_hook');
	unregister_hook('feature_settings', 'addon/fuzzloc/fuzzloc.php', 'fuzzloc_settings');
	unregister_hook('feature_settings_post', 'addon/fuzzloc/fuzzloc.php', 'fuzzloc_settings_post');


	logger("removed fuzzloc");
}



function fuzzloc_post_hook($a, &$item) {

	/**
	 *
	 * An item was posted on the local system.
	 * We are going to look for specific items:
	 *      - A status post by a profile owner
	 *      - The profile owner must have allowed our plugin
	 *
	 */


	if(! local_channel())   /* non-zero if this is a logged in user of this system */
		return;

	if(local_channel() != $item['uid'])    /* Does this person own the post? */
		return;

	if($item['parent'])   /* If the item has a parent, this is a comment or something else, not a status post. */
		return;

	if(! $item['coord'])
		return;

	$active = get_pconfig(local_channel(), 'fuzzloc', 'enable');
	if(! $active)
		return;

	$maxfuzz = intval(get_pconfig(local_channel(), 'fuzzloc', 'maxfuzz'));
	if(! intval($maxfuzz))
		return;


	$minfuzz = intval(get_pconfig(local_channel(), 'fuzzloc', 'minfuzz', 0));

	if($maxfuzz < $minfuzz) {
		// I'm sorry Dave. I'm afraid I can't do that.
		return;
	}

	logger('fuzzloc invoked',LOGGER_DEBUG);

	$coord = trim($item['coord']);
    $coord = str_replace(array(',','/','  '),array(' ',' ',' '),$coord);

	$lat = (float) trim(substr($coord, 0, strpos($coord, ' ')));
    $lon = (float) trim(substr($coord, strpos($coord, ' ')+1));

	$dir1 = intval(mt_rand(0,1));
	$dir2 = intval(mt_rand(0,1));

	$offset1 = mt_rand($minfuzz,$maxfuzz);
	if($dir1)
		$offset1 = 0 - $offset1;

	$offset2 = mt_rand($minfuzz,$maxfuzz);
	if($dir2)
		$offset2 = 0 - $offset2;

	// $fuzz is in meters. 

	$lat = $lat + fuzzloc_mtod($offset1,$lat);
    $lon = $lon + fuzzloc_mtod($offset2,$lat);

	$item['coord'] = $lat . ' ' .  $lon;

}


function fuzzloc_mtod($meters, $latitude)
{
    return $meters / (111.32 * 1000 * cos($latitude * (3.1415 / 180)));
}


/**
 *
 * Callback from the settings post function.
 * $post contains the $_POST array.
 * We will make sure we've got a valid user account
 * and if so set our configuration setting for this person.
 *
 */

function fuzzloc_settings_post($a,$post) {
	if(! local_channel())
		return;
	if($_POST['fuzzloc-submit']) {
		set_pconfig(local_channel(),'fuzzloc','enable',intval($_POST['fuzzloc']));
		set_pconfig(local_channel(),'fuzzloc','minfuzz',intval($_POST['minfuzz']));
		set_pconfig(local_channel(),'fuzzloc','maxfuzz',intval($_POST['maxfuzz']));
		info( t('Fuzzloc Settings updated.') . EOL);
	}
}


/**
 *
 * Called from the Plugin Setting form. 
 * Add our own settings info to the page.
 *
 */



function fuzzloc_settings(&$a,&$s) {

	if(! local_channel())
		return;

	/* Get the current state of our config variable */

	$enabled = get_pconfig(local_channel(),'fuzzloc','enable');

	$checked = (($enabled) ? 1 : false);

	/* Add some HTML to the existing form */

	$sc .= '<div class="descriptive-text">' . t('Fuzzloc allows you to blur your precise location if your channel uses browser location mapping.') . '</div><br>';

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('fuzzloc', t('Enable Fuzzloc Plugin'), $checked, '', array(t('No'),t('Yes'))),
	));

	$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
		'$field'	=> array('minfuzz', t('Minimum offset in meters'), get_pconfig(local_channel(),'fuzzloc','minfuzz')),
	));

	$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
		'$field'	=> array('maxfuzz', t('Maximum offset in meters'), get_pconfig(local_channel(),'fuzzloc','maxfuzz')),
	));


	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('fuzzloc',t('Fuzzloc Settings'), '', t('Submit')),
		'$content'	=> $sc
	));
}
