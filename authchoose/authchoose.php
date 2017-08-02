<?php

/**
 * Name: Auth Choose
 * Description: Allow magic authentication only to websites of your immediate connections.
 *
 */


function authchoose_load() {
	Zotlabs\Extend\Hook::register('zid','addon/authchoose/authchoose.php','authchoose_zid');
	Zotlabs\Extend\Hook::register('feature_settings','addon/authchoose/authchoose.php','authchoose_feature_settings');
	Zotlabs\Extend\Hook::register('feature_settings_post','addon/authchoose/authchoose.php','authchoose_feature_settings_post');

}

function authchoose_unload() {
	Zotlabs\Extend\Hook::unregister('zid','addon/authchoose/authchoose.php','authchoose_zid');
	Zotlabs\Extend\Hook::unregister('feature_settings','addon/authchoose/authchoose.php','authchoose_feature_settings');
	Zotlabs\Extend\Hook::unregister('feature_settings_post','addon/authchoose/authchoose.php','authchoose_feature_settings_post');


}

function authchoose_zid(&$x) {
	static $friends = [];

	$c = App::get_channel();
	if(! $c)
		return;

	$enabled = get_pconfig($c['channel_id'],'authchoose','enable');
	if(! $enabled)
		return;

	if(! array_key_exists($c['channel_id'],$friends)) {
		$r = q("select distinct hubloc_url from hubloc left join abook on hubloc_hash = abook_xchan where abook_id = %d",
			intval($c['channel_id'])
		);
		if($r)
			$friends[$c['channel_id']] = $r;
	}
	if($friends[$c['channel_id']]) {
		foreach($friends[$c['channel_id']] as $n) {
			if(strpos($x['url'],$n['hubloc_url']) !== false) {
				return; 
			}
		}
		$x['result'] = $x['url'];
	}
}

function authchoose_feature_settings(&$x) {

	if(! local_channel())
		return;

	/* Get the current state of our config variable */

	$enabled = get_pconfig(local_channel(),'authchoose','enable');

	$checked = (($enabled) ? 1 : false);

	/* Add some HTML to the existing form */

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('enable', t('Only authenticate automatically to sites of your friends'), $checked, t('By default you are automatically authenticated anywhere in the network'), array(t('No'),t('Yes'))),
	));

	$x .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('authchoose',t('Authchoose Settings'), '', t('Submit')),
		'$content'	=> $sc
	));


}

function authchoose_feature_settings_post($x) {

	if(! local_channel())
		return;

	if($_POST['authchoose-submit']) {
		set_pconfig(local_channel(),'authchoose','enable',intval($_POST['enable']));
		info( t('Atuhchoose Settings updated.') . EOL);
	}

}
