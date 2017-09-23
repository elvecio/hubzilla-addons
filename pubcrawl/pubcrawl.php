<?php

/**
 * Name: PubCrawl
 * Description: An unapologetically non-compliant ActivityPub Protocol implemention
 * 
 */

/**
 * This connector is undergoing heavy development at the moment. If you think some shortcuts were taken 
 * - you are probably right. These will be cleaned up and moved to generalised interfaces once we actually 
 * get communication flowing. 
 */

require_once('addon/pubcrawl/as.php');



function pubcrawl_load() {
	Zotlabs\Extend\Hook::register_array('addon/pubcrawl/pubcrawl.php', [
		'module_loaded'              => 'pubcrawl_load_module',
		'webfinger'                  => 'pubcrawl_webfinger',
		'channel_mod_init'           => 'pubcrawl_channel_mod_init',
		'profile_mod_init'           => 'pubcrawl_profile_mod_init',
		'follow_mod_init'            => 'pubcrawl_follow_mod_init',
		'item_mod_init'              => 'pubcrawl_item_mod_init',
		'thing_mod_init'             => 'pubcrawl_thing_mod_init',
		'locs_mod_init'              => 'pubcrawl_locs_mod_init',
		'follow_allow'               => 'pubcrawl_follow_allow',
		'discover_channel_webfinger' => 'pubcrawl_discover_channel_webfinger',
		'permissions_create'         => 'pubcrawl_permissions_create',
		'permissions_accept'         => 'pubcrawl_permissions_accept',
		'connection_remove'          => 'pubcrawl_connection_remove',
		'notifier_hub'               => 'pubcrawl_notifier_process',
		'feature_settings_post'      => 'pubcrawl_feature_settings_post',
		'feature_settings'           => 'pubcrawl_feature_settings',
		'channel_links'              => 'pubcrawl_channel_links',
		'personal_xrd'               => 'pubcrawl_personal_xrd',
		'queue_deliver'              => 'pubcrawl_queue_deliver'
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

function pubcrawl_channel_links(&$b) {
	$c = channelx_by_nick($b['channel_address']);
	if($c && get_pconfig($c['channel_id'],'system','activitypub_allowed')) {
		$b['channel_links'][] = [
			'rel' => 'alternate',
			'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
			'url' => z_root() . '/channel/' . $c['channel_address']
		];
		$b['channel_links'][] = [
			'rel' => 'alternate',
			'type' => 'application/activity+json',
			'url' => z_root() . '/channel/' . $c['channel_address']
		];
	}
}

function pubcrawl_webfinger(&$b) {
	if(! $b['channel'])
		return;

	$b['result']['links'][] = [ 
		'rel'  => 'self', 
		'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
	$b['result']['links'][] = [ 
		'rel'  => 'self', 
		'type' => 'application/activity+json',
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
}

function pubcrawl_personal_xrd(&$b) {

	if(! intval(get_pconfig($b['user']['channel_id'],'system','activitypub_allowed')))
		return;

	$s = '<Link rel="self" type="application/ld+json" href="' . z_root() . '/channel/' . $b['user']['channel_address'] . '" />';
	$s = '<Link rel="self" type="application/activity+json" href="' . z_root() . '/channel/' . $b['user']['channel_address'] . '" />';

	$b['xml'] = str_replace('</XRD>', $s . "\n" . '</XRD>',$b['xml']);

}

function pubcrawl_discover_channel_webfinger(&$b) {

	$url      = $b['address'];
	$x        = $b['webfinger'];
	$protocol = $b['protocol'];

	if($protocol && strtolower($protocol) !== 'activitypub')
		return;

    if(strpos($url,'@') && $x && array_key_exists('links',$x) && $x['links']) {
        foreach($x['links'] as $link) {
            if(array_key_exists('rel',$link) && array_key_exists('type',$link)) {
                if($link['rel'] === 'self' && ($link['type'] === 'application/activity+json' || strpos($link['type'],'ld+json') !== false)) {
					$url = $x['href'];
                }
            }
        }
    }
	
	if(($url) && (strpos($url,'http') === 0)) {
		$x = as_fetch($url);
		if(! $x) {
			return;
		}
	}
	else {
		return;
	}

	$AS = new \Zotlabs\Lib\ActivityStreams($x);

	if(! $AS->is_valid()) {
		return;
	}

	// Now find the actor and see if there is something we can follow	

	$person_obj = null;
	if($AS->type === 'Person') {
		$person_obj = $AS->data;
	}
	elseif($AS->obj && $AS->obj['type'] === 'Person') {
		$person_obj = $AS->object;
	}
	else {
		return;
	}

	as_actor_store($url,$person_obj);

	$b['success'] = true;

}


function pubcrawl_load_module(&$b) {

	//logger('module: ' . \App::$query_string);

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
	if($b['module'] === 'ap_probe') {
		require_once('addon/pubcrawl/Mod_Ap_probe.php');
		$b['controller'] = new \Zotlabs\Module\Ap_probe();
		$b['installed'] = true;
	}
	if($b['module'] === 'apschema') {
		require_once('addon/pubcrawl/Mod_Apschema.php');
		$b['controller'] = new \Zotlabs\Module\Apschema();
		$b['installed'] = true;
	}
	if($b['module'] === 'followers') {
		require_once('addon/pubcrawl/Mod_Followers.php');
		$b['controller'] = new \Zotlabs\Module\Followers();
		$b['installed'] = true;
	}
	if($b['module'] === 'following') {
		require_once('addon/pubcrawl/Mod_Following.php');
		$b['controller'] = new \Zotlabs\Module\Following();
		$b['installed'] = true;
	}
}


function pubcrawl_is_as_request() {

	if(strpos($_SERVER['HTTP_ACCEPT'],'activity') !== false) {
		logger('Accept: ' . $_SERVER['HTTP_ACCEPT'], LOGGER_DATA);
	}

	if($_REQUEST['module_format'] === 'json')
		return true;

	$x = getBestSupportedMimeType([
		'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
		'application/activity+json',
		'application/ld+json;profile="http://www.w3.org/ns/activitystreams"'
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
    $data_type = 'application/activity+json';
    $encoding  = 'base64url';
    $algorithm = 'RSA-SHA256';
    $keyhash   = base64url_encode(z_root() . '/channel/' . $channel['channel_address']);

    $data = str_replace(array(" ","\t","\r","\n"),array("","","",""),$data);

    // precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

    $precomputed = '.' . base64url_encode($data_type,false) . '.YmFzZTY0dXJs.UlNBLVNIQTI1Ng==';

    $signature  = base64url_encode(rsa_sign($data . $precomputed,$channel['channel_prvkey']));

    return ([
        'data'       => $data,
		'data_type'  => $data_type,
        'encoding'   => $encoding,
        'alg'        => $algorithm,
		'sigs'       => [
			'value'  => $signature,
			'key_id' => $keyhash
		]
	]);

}

function pubcrawl_channel_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$chan = channelx_by_nick(argv(1));
		if(! $chan)
			http_status_exit(404, 'Not found');

		if(! get_pconfig($chan['channel_id'],'system','activitypub_allowed'))
			http_status_exit(404, 'Not found');

		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
			]], asencode_person($chan));


		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;

		$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . $chan['channel_address'],true);
		echo $ret;
		logger('channel: ' . $ret, LOGGER_DATA);
		killme();
	}
}

function pubcrawl_notifier_process(&$arr) {

	if($arr['hub']['hubloc_network'] !== 'activitypub')
		return;

	logger('upstream: ' . intval($arr['upstream']));

	logger('notifier_array: ' . print_r($arr,true), LOGGER_ALL, LOG_INFO);

	// allow this to be set per message

	if($arr['mail']) {
		logger('Cannot send mail to activitypub.');
		return;
	}

	if(array_key_exists('target_item',$arr) && is_array($arr['target_item'])) {

		if(intval($arr['target_item']['item_obscured'])) {
			logger('Cannot send raw data as an activitypub activity.');
			return;
		}

		if(strpos($arr['target_item']['postopts'],'nopub') !== false) {
			return;
		}
	}

	$allowed = get_pconfig($arr['channel']['channel_id'],'system','activitypub_allowed');

	if(! intval($allowed)) {
		logger('pubcrawl: disallowed for channel ' . $arr['channel']['channel_name']);
		return;
	}

	if($arr['location'])
		return;

	$target_item = $arr['target_item'];

	$prv_recips = $arr['env_recips'];

	$msg = array_merge(['@context' => [
		ACTIVITYSTREAMS_JSONLD_REV,
		'https://w3id.org/security/v1',
		z_root() . ZOT_APSCHEMA_REV
	]], asencode_activity($target_item));
	
	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$arr['channel']);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);


	if($prv_recips) {
		$hashes = array();

		// re-explode the recipients, but only for this hub/pod

		foreach($prv_recips as $recip)
			$hashes[] = "'" . $recip['hash'] . "'";

		$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s'
			and xchan_hash in (" . implode(',', $hashes) . ") and xchan_network = 'activitypub' ",
			dbesc($arr['hub']['hubloc_url'])
		);

		if(! $r) {
			logger('activitypub_process_outbound: no recipients');
			return;
		}

		foreach($r as $contact) {

			// is $contact connected with this channel - and if the channel is cloned, also on this hub?
			$single = deliverable_singleton($arr['channel']['channel_id'],$contact);

			if(! $arr['normal_mode'])
				continue;

			if($single) {
				$qi = pubcrawl_queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
				if($qi)
					$arr['queued'][] = $qi;
			}
			continue;
		}

	}
	else {

		// public message

		// See if we can deliver all of them at once

		$x = get_xconfig($arr['hub']['hubloc_hash'],'activitypub','collections');
		if($x && $x['sharedInbox']) {
			logger('using publicInbox delivery for ' . $arr['hub']['hubloc_url'], LOGGER_DEBUG);
			$contact['hubloc_callback'] = $x['sharedInbox'];
			$qi = pubcrawl_queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
			if($qi) {
				$arr['queued'][] = $qi;
			}
		}
		else {

			$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s' and xchan_network = 'activitypub' ",
				dbesc($arr['hub']['hubloc_url'])
			);

			if(! $r) {
				logger('activitypub_process_outbound: no recipients');
				return;
			}

		



			foreach($r as $contact) {

				$single = deliverable_singleton($arr['channel']['channel_id'],$contact);

				if($single) {
					$qi = pubcrawl_queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
					if($qi)
						$arr['queued'][] = $qi;
				}
			}	
		}
	}
	
	return;

}


function pubcrawl_queue_message($msg,$sender,$recip,$message_id = '') {


    $allowed = get_pconfig($sender['channel_id'],'system','activitypub_allowed',1);

    if(! intval($allowed)) {
        return false;
    }
        
	$dest_url = $recip['hubloc_callback'];

    logger('URL: ' . $dest_url, LOGGER_DEBUG);
	logger('DATA: ' . jindent($msg), LOGGER_DATA);

    if(intval(get_config('system','activitypub_test')) || intval(get_pconfig($sender['channel_id'],'system','activitypub_test'))) {
        logger('test mode - delivery disabled');
        return false;
    }

    $hash = random_string();

    logger('queue: ' . $hash . ' ' . $dest_url, LOGGER_DEBUG);
	queue_insert(array(
        'hash'       => $hash,
        'account_id' => $sender['channel_account_id'],
        'channel_id' => $sender['channel_id'],
        'driver'     => 'pubcrawl',
        'posturl'    => $dest_url,
        'notify'     => '',
        'msg'        => $msg
    ));

    if($message_id && (! get_config('system','disable_dreport'))) {
        q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_result, dreport_time, dreport_xchan, dreport_queue ) values ( '%s','%s','%s','%s','%s','%s','%s' ) ",
            dbesc($message_id),
            dbesc($dest_url),
            dbesc($dest_url),
            dbesc('queued'),
            dbesc(datetime_convert()),
            dbesc($sender['channel_hash']),
            dbesc($hash)
        );
    }

    return $hash;

}


function pubcrawl_connection_remove(&$x) {

	$recip = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
		intval($x['abook_id'])
	);

	if((! $recip) || $recip[0]['xchan_network'] !== 'activitypub')
		return; 

	$channel = channelx_by_n($recip[0]['abook_channel']);
	if(! $channel)
		return;

	// send an unfollow activity to the followee's inbox

	$orig_activity = get_abconfig($recip[0]['abook_channel'],$recip[0]['xchan_hash'],'pubcrawl','follow_id');

	if($orig_activity && $recip[0]['abook_pending']) {

		// was never approved

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV

			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . '#reject',
				'type'  => 'Reject',
				'actor' => asencode_person($channel),
				'object'     => [
					'type'   => 'Follow',
					'id'     => $orig_activity,
					'actor'  => $recip[0]['xchan_hash'],
					'object' => asencode_person($channel)
				],
				'to' => [ $recip[0]['xchan_hash'] ]
		]);
		del_abconfig($recip[0]['abook_channel'],$recip[0]['xchan_hash'],'pubcrawl','follow_id');

	}
	else {

		// send an unfollow

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . '#Undo',
				'type'  => 'Undo',
				'actor' => asencode_person($channel),
				'object'     => [
					'id'     => z_root() . '/follow/' . $recip[0]['abook_id'],
					'type'   => 'Follow',
					'actor'  => asencode_person($channel),
					'object' => $recip[0]['xchan_hash']
				],
				'to' => [ $recip[0]['xchan_hash'] ]
			]
		);
	}

	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$channel);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($channel['channel_id'],$recip[0]);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($recip[0]['xchan_hash'])
	);

	if($single && $h) {
		$qi = pubcrawl_queue_message($jmsg,$channel,$h[0]);
		if($qi) {
			\Zotlabs\Daemon\Master::Summon([ 'Deliver' , $qi ]);
		}
	}
		
}




function pubcrawl_permissions_create(&$x) {

	// send a follow activity to the followee's inbox

	if($x['recipient']['xchan_network'] !== 'activitypub') {
		return;
	}

	$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1'
		]], 
		[
			'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'],
			'type'   => 'Follow',
			'actor'  => asencode_person($x['sender']),
			'object' => $x['recipient']['xchan_url'],
			'to'     => [ $x['recipient']['xchan_hash'] ]
	]);


	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$x['sender']);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($x['sender']['channel_id'],$x['recipient']);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($x['recipient']['xchan_hash'])
	);

	if($single && $h) {
		$qi = pubcrawl_queue_message($jmsg,$x['sender'],$h[0]);
		if($qi)
			$x['deliveries'] = $qi;
	}
		
	$x['success'] = true;

}


function pubcrawl_permissions_accept(&$x) {

	// send an accept activity to the followee's inbox

	if($x['recipient']['xchan_network'] !== 'activitypub') {
		return;
	}

	// we currently are not handling send of reject follow activities; this is permitted by protocol

	$accept = get_abconfig($x['recipient']['abook_channel'],$x['recipient']['xchan_hash'],'pubcrawl','their_follow_id');
	if(! $accept)
		return;

	$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]], 
		[
			'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'],
			'type'   => 'Accept',
			'actor'  => asencode_person($x['sender']),
			'object' => [
				'type'   => 'Follow',
				'id'     => $accept,
				'actor'  => $x['recipient']['xchan_hash'],
				'object' => z_root() . '/channel/' . $x['sender']['channel_address']
			],
			'to' => [ $x['recipient']['xchan_hash'] ]
	]);

	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$x['sender']);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($x['sender']['channel_id'],$x['recipient']);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($x['recipient']['xchan_hash'])
	);

	if($single && $h) {
		$qi = pubcrawl_queue_message($jmsg,$x['sender'],$h[0]);
		if($qi)
			$x['deliveries'] = $qi;
	}
		
	$x['success'] = true;

}


function pubcrawl_profile_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$chan = channelx_by_nick(argv(1));
		if(! $chan)
			http_status_exit(404, 'Not found');

		if(! get_pconfig($chan['channel_id'],'system','activitypub_allowed'))
			http_status_exit(404, 'Not found');


		$x = [
			'@context' => [ 
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			],
			'type' => 'Profile',
			'describes' => asencode_person($chan)
		];
				
		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . $chan['channel_address'],true);
		echo $ret;
		killme();

	}
}


function pubcrawl_item_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$item_id = argv(1);
		if(! $item_id)
			http_status_exit(404, 'Not found');


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

		if(! in_array(activity_obj_mapper($items[0]['obj_type']), [ 'Note', 'Article' ])) {
			http_status_exit(418, "I'm a teapot"); 
		}

		$chan = channelx_by_n($items[0]['uid']);

		if(! $chan)
			http_status_exit(404, 'Not found');

		if(! perm_is_allowed($chan['channel_id'],get_observer_hash(),'view_stream'))
			http_status_exit(403, 'Forbidden');


		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
			]], asencode_item($items[0]));


		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . $chan['channel_address'],true);
		echo $ret;
		killme();

	}
}


function pubcrawl_thing_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$item_id = argv(1);
		if(! $item_id)
			return;

		$r = q("select * from obj where obj_type = %d and obj_obj = '%s' limit 1",
			intval(TERM_OBJ_THING),
			dbesc($item_id)
		);

		if(! $r)
			return;

		$chan = channelx_by_n($r[0]['obj_channel']);

		if(! $chan)
			http_status_exit(404, 'Not found');

		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
			]],
			[ 
				'type' => 'Object',
				'id'   => z_root() . '/thing/' . $r[0]['obj_obj'],
 				'name' => $r[0]['obj_term']
			]
		);

		if($r[0]['obj_image'])
			$x['image'] = $r[0]['obj_image'];


		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . $chan['channel_address'],true);
		echo $ret;
		killme();
	}
}


function pubcrawl_locs_mod_init($x) {
	
	if(pubcrawl_is_as_request()) {
		$channel_address = argv(1);
		if(! $channel_address)
			return;

		$chan = channelx_by_nick($channel_address);

		if(! $chan)
			http_status_exit(404, 'Not found');

		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
			]],
			[ 
				'type' => 'nomadicHubs',
				'id'   => z_root() . '/locs/' . $chan['channel_address']
			]
		);

		$locs = zot_encode_locations($chan);
		if($locs) {
			$x['nomadicLocations'] = [];
			foreach($locs as $loc) {
				$x['nomadicLocations'][] = [
					'id'      => $loc['url'] . '/locs/' . substr($loc['address'],0,strpos($loc['address'],'@')),
					'type'            => 'nomadicLocation',
					'locationAddress' => 'acct:' . $loc['address'],
					'locationPrimary' => (boolean) $loc['primary'],
					'locationDeleted' => (boolean) $loc['deleted']
				];
			}
		}

		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . $chan['channel_address'],true);
		echo $ret;
		killme();
	}
}



function pubcrawl_follow_mod_init($x) {

	if(pubcrawl_is_as_request() && argc() == 2) {
		$abook_id = intval(argv(1));
		if(! $abook_id)
			return;
		$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
			intval($abook_id)
		);
		if (! $r)
			return;

		$chan = channelx_by_n($r[0]['abook_channel']);

		if(! $chan)
			http_status_exit(404, 'Not found');

		$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'     => z_root() . '/follow/' . $r[0]['abook_id'],
				'type'   => 'Follow',
				'actor'  => asencode_person($chan),
				'object' => $r[0]['xchan_url']
		]);
				

		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . $chan['channel_address'],true);
		echo $ret;
		killme();

	}
}


function pubcrawl_queue_deliver(&$b) {

	$outq = $b['outq'];
	$base = $b['base'];
	$immediate = $b['immediate'];


	if($outq['outq_driver'] === 'pubcrawl') {
		$b['handled'] = true;

		$channel = channelx_by_n($outq['outq_channel']);

		$retries = 0;

		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$ret = $outq['outq_msg'];
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		$xhead = \Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],z_root() . '/channel/' . $channel['channel_address'],false);
	
		$result = z_post_url($outq['outq_posturl'],$outq['outq_msg'],$retries,[ 'headers' => $xhead ]);

		if($result['success'] && $result['return_code'] < 300) {
			logger('deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
			if($base) {
				q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
					dbesc(datetime_convert()),
					dbesc($base)
				);
			}
			q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
				dbesc('accepted for delivery'),
				dbesc(datetime_convert()),
				dbesc($outq['outq_hash'])
			);
			remove_queue_item($outq['outq_hash']);

			// server is responding - see if anything else is going to this destination and is piled up 
			// and try to send some more. We're relying on the fact that do_delivery() results in an 
			// immediate delivery otherwise we could get into a queue loop. 

			if(! $immediate) {
				$x = q("select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
					dbesc($outq['outq_posturl'])
				);

				$piled_up = array();
				if($x) {
					foreach($x as $xx) {
						 $piled_up[] = $xx['outq_hash'];
					}
				}
				if($piled_up) {
					do_delivery($piled_up);
				}
			}
		}
	}
}

function pubcrawl_feature_settings_post(&$b) {

	if($_POST['pubcrawl-submit']) {
		set_pconfig(local_channel(),'system','activitypub_allowed',intval($_POST['activitypub_allowed']));
		
		info( t('ActivityPub Protocol Settings updated.') . EOL);
	}
}


function pubcrawl_feature_settings(&$s) {

	$ap_allowed = get_pconfig(local_channel(),'system','activitypub_allowed');

	$sc = '<div>' . t('The ActivityPub protocol does not support location independence. Connections you make within that network may be unreachable from alternate channel locations.') . '</div><br>';

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('activitypub_allowed', t('Enable the ActivityPub protocol for this channel'), $ap_allowed, '', $yes_no),
	));


	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('pubcrawl', t('ActivityPub Protocol Settings'), '', t('Submit')),
		'$content'	=> $sc
	));

	return;

}

