<?php


/**
 * Name: Adult Photo Flag
 * Description: Provides an optional feature to hide individual photos from the default album view
 * Version: 1.0
 * Author: Mike Macgirvin <mike@zothub.com>
 * Maintainer: none
 */

function adultphotoflag_load() {
	register_hook('get_features','addon/adultphotoflag/adultphotoflag.php','adultphotoflag_get_features');
}

function adultphotoflag_unload() {
	unregister_hook('get_features','addon/adultphotoflag/adultphotoflag.php','adultphotoflag_get_features');
}

function adultphotoflag_get_features(&$a,&$x) {

	$entry = [
		'adult_photo_flagging', 
		t('Flag Adult Photos'),  
		t('Provide photo edit option to hide inappropriate photos from default album view'),
		false,
		get_config('feature_lock','adult_photo_flagging'),                                              
		feature_level('adult_photo_flagging',2),          
	];

	$x['features']['general'][] = $entry;


}

