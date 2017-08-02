<?php

/**
 * Name: PubCrawl (WIP; developers only - not yet functional)
 * Description: An unapologetically non-compliant ActivityPub Protocol implemention
 * 
 */

/**
 * This connector is undergoing heavy development at the moment. If you think some shortcuts were taken 
 * - you are probably right. These will be cleaned up and moved to generalised interfaces once we actually 
 * get communication flowing. 
 */

require_once('addon/pubcrawl/as.php');
require_once('addon/pubcrawl/ActivityStreams.php');


function pubcrawl_load() {
	Zotlabs\Extend\Hook::register_array('addon/pubcrawl/pubcrawl.php', [
		'module_loaded'              => 'pubcrawl_load_module',
		'channel_mod_init'           => 'pubcrawl_channel_mod_init',
		'profile_mod_init'           => 'pubcrawl_profile_mod_init',
		'item_mod_init'              => 'pubcrawl_item_mod_init',
		'follow_allow'               => 'pubcrawl_follow_allow',
		'discover_channel_webfinger' => 'pubcrawl_discover_channel_webfinger'

	]);
}

function pubcrawl_unload() {
	Zotlabs\Extend\Hook::unregister_by_file('addon/pubcrawl/pubcrawl.php');
}


function pubcrawl_follow_allow(&$b) {

	if($b['xchan']['xchan_network'] !== 'activitypub')
		return;

	$allowed = get_pconfig($b['channel_id'],'system','activitypub_allowed');
	if($allowed === false)
		$allowed = 1;
	$b['allowed'] = $allowed;
	$b['singleton'] = 1;  // this network does not support channel clones

}

function pubcrawl_discover_channel_webfinger(&$b) {

logger('pubcrawl discover');

	$url = $b['address'];

logger('url: ' . $url);

	if($url) {
		$x = as_fetch($url);
		if(! $x)
			return;
	}

	$AS = new ActivityStreams($x);

	if(! $AS->is_valid())
		return;

logger('as_data: ' . print_r($AS->data,true));

	// Now find the actor and see if there is something we can follow	

	$person_obj = null;
	if($AS->type === 'Person') {
		$person_obj = $AS->data;
	}
	elseif($AS->object && $AS->object['type'] === 'Person') {
		$person_obj = $AS->object;
	}
	else {
		return;
	}

	$name = $person_obj['name'];
	if(! $name)
		$name = t('unknown');

	if($person_obj['icon']) {
		if(is_array($person_obj['icon'])) {
			if(array_key_exists('url',$person_obj['icon']))
				$icon = $person_obj['icon']['url'];
			else
				$icon = $person_obj['icon'][0]['url'];
		}
		else
			$icon = $person_obj['icon'];
	}

	if($person_obj['url'] && $person_obj['url']['href'])
		$profile = $person_obj['url']['href'];
	else
		$profile = $url;


	$inbox = $person_obj['inbox'];

	$collections = [];

	if($inbox) {
		$collectons['inbox'] = $inbox;
		if($person['outbox'])
			$collections['outbox'] = $person_obj['outbox'];
		if($person['publicInbox'])
			$collections['publicInbox'] = $person_obj['publicInbox'];
		if($person['followers'])
			$collections['followers'] = $person_obj['followers'];
		if($person['following'])
			$collections['following'] = $person_obj['following'];
	}


	// @todo fetch pubkey

	$r = q("select * from xchan where xchan_hash = '%s' limit 1",
		dbesc($person_obj['id'])
	);
	
	if(! $r) {
		// create a new record
		$r = xchan_store_lowlevel(
			[
				'xchan_hash'         => $url,
				'xchan_guid'         => $url,
				'xchan_pubkey'       => $pubkey,
				'xchan_addr'         => '',
				'xchan_url'          => $profile,
				'xchan_name'         => $name,
				'xchan_name_date'    => datetime_convert(),
				'xchan_network'      => 'activitypub'
			]
		);
	}
	else {
		// update existing record
		$r = q("update xchan set xchan_name = '%s', xchan_network = '%s', xchan_name_date = '%s' where xchan_hash = '%s'",
			dbesc($name),
			dbesc('activitypub'),
			dbesc(datetime_convert()),
			dbesc($url)
		);
	}

	if($collections) {
		set_xconfig($url,'activitypub','collections',$collections);
	}

	$r = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($url)
	);

	if(! $r) {
		$r = hubloc_store_lowlevel(
			[
				'hubloc_guid'     => $url,
				'hubloc_hash'     => $url,
				'hubloc_addr'     => '',
				'hubloc_network'  => 'activitypub',
				'hubloc_url'      => $url,
				'hubloc_host'     => $hostname,
				'hubloc_callback' => $inbox,
				'hubloc_updated'  => datetime_convert(),
				'hubloc_primary'  => 1
			]
		);
	}

	if(! $icon)
		$icon = get_default_profile_photo(300);

	$photos = import_xchan_photo($icon,$url);
	$r = q("update xchan set xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s'",
		dbescdate(datetime_convert('UTC','UTC',$arr['photo_updated'])),
		dbesc($photos[0]),
		dbesc($photos[1]),
		dbesc($photos[2]),
		dbesc($photos[3]),
		dbesc($url)
	);
	$b['success'] = true;

}


function pubcrawl_load_module(&$b) {
	if($b['module'] === 'inbox') {
		require_once('addon/pubcrawl/Mod_Inbox.php');
		$b['controller'] = new \Zotlabs\Module\Inbox();
		$b['installed'] = true;
	}
	if($b['module'] === 'outbox') {
		require_once('addon/pubcrawl/Mod_Outbox.php');
		$b['controller'] = new \Zotlabs\Module\Outbox();
		$b['installed'] = true;
	}
	if($b['module'] === 'activity') {
		require_once('addon/pubcrawl/Mod_Activity.php');
		$b['controller'] = new \Zotlabs\Module\Activity();
		$b['installed'] = true;
	}
	if($b['module'] === 'nullbox') {
		require_once('addon/pubcrawl/Mod_Nullbox.php');
		$b['controller'] = new \Zotlabs\Module\Nullbox();
		$b['installed'] = true;
	}
}


function pubcrawl_is_as_request() {

	if($_REQUEST['module_format'] === 'json')
		return true;

	$x = getBestSupportedMimeType([
		'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
		'application/ld+json;profile="http://www.w3.org/ns/activitystreams"',
		'application/activity+json'
	]);

	return(($x) ? true : false);

}


function pubcrawl_magic_env_allowed() {

	$x = getBestSupportedMimeType([
		'application/magic-envelope+json'
	]);

	return(($x) ? true : false);
}

function pubcrawl_salmon_sign($data,$channel) {

  	$data      = base64url_encode($data, false); // do not strip padding
    $data_type = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
    $encoding  = 'base64url';
    $algorithm = 'RSA-SHA256';
    $keyhash   = base64url_encode(hash('sha256',salmon_key($channel['channel_pubkey'])),true);

    $data = str_replace(array(" ","\t","\r","\n"),array("","","",""),$data);

    // precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

    $precomputed = '.' . base64url_encode($data_type,false) . '.YmFzZTY0dXJs.UlNBLVNIQTI1Ng==';

    $signature  = base64url_encode(rsa_sign($data . $precomputed,$channel['channel_prvkey']));

    return ([
        'data'      => $data,
		'data_type' => $data_type,
        'encoding'  => $encoding,
        'alg'       => $algorithm,
		'sigs'      => [
			'value' => $signature,
			'key_id' => $keyhash
		]
	]);

}

function pubcrawl_channel_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$chan = channelx_by_nick(argv(1));
		if(! $chan)
			return;

		$x = array_merge(['@context' => [
			'https://www.w3.org/ns/activitystreams',
			[ 'me' => 'http://salmon-protocol.org/ns/magic-env' ]
			]], asencode_person($chan));

		if(pubcrawl_magic_env_allowed()) {
			$x = pubcrawl_salmon_sign(json_encode($x),$chan);
			header('Content-Type: application/magic-envelope+json');
			json_return_and_die($x);

		}
		else {
			header('Content-Type: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
			json_return_and_die($x);
		}
	}
}


function pubcrawl_profile_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$chan = channelx_by_nick(argv(1));
		if(! $chan)
			return;
		$x = [
			'@context' => [ 'https://www.w3.org/ns/activitystreams',
				[ 'me' => 'http://salmon-protocol.org/ns/magic-env' ]
			],
			'type' => 'Profile',
			'describes' => asencode_person($chan)
		];
				
		if(pubcrawl_magic_env_allowed()) {
			$x = pubcrawl_salmon_sign(json_encode($x),$chan);
			header('Content-Type: application/magic-envelope+json');
			json_return_and_die($x);
		}
		else {
			header('Content-Type: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
			json_return_and_die($x);
		}
	}
}


function pubcrawl_item_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$item_id = argv(1);
		if(! $item_id)
			return;

		$item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 
			and item.item_delayed = 0 and item.item_blocked = 0 ";

		$sql_extra = item_permissions_sql(0);

		$r = q("select * from item where mid like '%s' $item_normal $sql_extra limit 1",
			dbesc($item_id . '%')
		);
		if(! $r) {
			$r = q("select * from item where mid like '%s' $item_normal limit 1",
				dbesc($item_id . '%')
			);
			if($r) {
				http_status_exit(403, 'Forbidden');
			}
			http_status_exit(404, 'Not found');
		}

		xchan_query($r,true);
		$items = fetch_post_tags($r,true);

		// Wrong object type

		if(activity_obj_mapper($items[0]['obj_type']) !== 'Note') {
			http_status_exit(418, "I'm a teapot"); 
		}

		$x = array_merge(['@context' => [
			'https://www.w3.org/ns/activitystreams',
			[ 'me' => 'http://salmon-protocol.org/ns/magic-env' ],
			[ 'zot' => 'http://purl.org/zot/protocol' ]
			]], asencode_item($items[0]));

		header('Content-Type: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
		json_return_and_die($x);

	}
}
