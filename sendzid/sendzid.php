<?php


/**
 * Name: Send ZID
 * Description: Provides an optional feature to send your identity to all websites
 * Version: 1.0
 * Author: Mike Macgirvin <mike@zothub.com>
 * Maintainer: none
 */

function sendzid_load() {
	register_hook('get_features','addon/sendzid/sendzid.php','sendzid_get_features');
}

function sendzid_unload() {
	unregister_hook('get_features','addon/sendzid/sendzid.php','sendzid_get_features');
}

function sendzid_get_features(&$a,&$x) {

	$entry = [
		'sendzid',                                                                         
		t('Extended Identity Sharing'),                                                                      
		t('Share your identity with all websites on the internet. When disabled, identity is only shared with $Projectname sites.'),
		false,                                                                              
		get_config('feature_lock','sendzid'),                                              
		feature_level('sendzid',4),          
	];

	if(($x['filtered'] == false) || ($x['filtered'] == true && intval($x['techlevel']) >= intval($entry[5]))) {
		if(! is_array($x['features']['general'])) {
			$x['features']['general'] = [ t('General Features') ];
		}
		$x['features']['general'][] = $entry;
	}

}

