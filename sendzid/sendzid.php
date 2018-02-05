<?php


/**
 * Name: Send ZID
 * Description: Provides an optional feature to send your identity to all websites
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */

function sendzid_load() {
	\Zotlabs\Extend\Hook::Register('get_features','addon/sendzid/sendzid.php','sendzid_get_features');
	\Zotlabs\Extend\Hook::Register('zidify','addon/sendzid/sendzid.php','sendzid_zidify');
}

function sendzid_unload() {
	\Zotlabs\Extend\Hook::unregister_by_file('addon/sendzid/sendzid.php');
}

function sendzid_get_features(&$x) {

	$entry = [
		'sendzid',                                                                         
		t('Extended Identity Sharing'),                                                                      
		t('Share your identity with all websites on the internet. When disabled, identity is only shared with $Projectname sites.'),
		false,                                                                              
		get_config('feature_lock','sendzid'),                                              
		feature_level('sendzid',4),          
	];

	$x['features']['general'][] = $entry;

}

function sendzid_zidify(&$x) {
	if(local_channel() && feature_enabled(local_channel(),'sendzid')) {
		$x['zid'] = true;
	}
}