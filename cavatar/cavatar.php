<?php

/**
 * Name: Cavatar
 * Description: create random cat-avatar for default profile photo
 * Version: 1.0
 *
 */


function cavatar_load() {
	\Zotlabs\Extend\Hook::register('create_channel_photo', 'addon/cavatar/cavatar.php', 'cavatar_channel_photo');
}

function cavatar_unload() {
	\Zotlabs\Extend\Hook::unregister('create_channel_photo', 'addon/cavatar/cavatar.php', 'cavatar_channel_photo');
}


function cavatar_channel_photo(&$x) {

	$x['photo_url'] = z_root() . '/cavatar?f=&seed=' . notags($x['channel']['channel_address']) ;

}


function cavatar_module() {}


function cavatar_init(&$a) {

	require_once('addon/cavatar/cat-avatar-generator.php');
	build_cat($_REQUEST['seed']);

}


