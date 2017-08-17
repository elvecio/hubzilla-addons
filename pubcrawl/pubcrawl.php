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
require_once('addon/pubcrawl/ActivityStreams.php');
require_once('addon/pubcrawl/HTTPSig.php');


function pubcrawl_load() {
	Zotlabs\Extend\Hook::register_array('addon/pubcrawl/pubcrawl.php', [
		'module_loaded'              => 'pubcrawl_load_module',
		'webfinger'                  => 'pubcrawl_webfinger',
		'channel_mod_init'           => 'pubcrawl_channel_mod_init',
		'profile_mod_init'           => 'pubcrawl_profile_mod_init',
		'follow_mod_init'            => 'pubcrawl_follow_mod_init',
		'item_mod_init'              => 'pubcrawl_item_mod_init',
		'follow_allow'               => 'pubcrawl_follow_allow',
		'discover_channel_webfinger' => 'pubcrawl_discover_channel_webfinger',
		'permissions_create'         => 'pubcrawl_permissions_create',
		'notifier_hub'               => 'pubcrawl_notifier_process',
		'feature_settings_post'      => 'pubcrawl_feature_settings_post',
		'feature_settings'           => 'pubcrawl_feature_settings',
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

function pubcrawl_webfinger(&$b) {
	$b['result']['links'][] = [ 
		'rel'  => 'self', 
		'type' => 'application/activity+json', 
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
}


function pubcrawl_discover_channel_webfinger(&$b) {

	$url = $b['address'];

	$protocol = $b['protocol'];
	if($protocol && strtolower($protocol) !== 'activitypub')
		return;


	if($url) {
		$x = as_fetch($url);
		if(! $x)
			return;
	}

	$AS = new ActivityStreams($x);

	if(! $AS->is_valid())
		return;

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

	as_actor_store($person_obj);


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
		'application/activity+json',
		'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
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
			$headers = [];
			$headers['Content-Type'] = 'application/activity+json' ;
			$ret = json_encode($x);
			$hash = HTTPSig::generate_digest($ret,false);
			$headers['Digest'] = 'SHA-256=' . $hash;  
			HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . argv(1),true);
			echo $ret;
			killme();
		}
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
		'https://www.w3.org/ns/activitystreams',
		[ 'me' => 'http://salmon-protocol.org/ns/magic-env' ],
		[ 'zot' => 'http://purl.org/zot/protocol' ]
	]], asencode_activity($target_item));
	

	$jmsg = json_encode($msg);

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
	
	return;

}


function pubcrawl_queue_message($msg,$sender,$recip,$message_id = '') {


    $allowed = get_pconfig($sender['channel_id'],'system','activitypub_allowed',1);

    if(! intval($allowed)) {
        return false;
    }

//    if($public_batch)
  //      $dest_url = $recip['hubloc_callback'] . '/public';
//    else
        
	$dest_url = $recip['hubloc_callback'];

    logger('URL: ' . $dest_url, LOGGER_DEBUG);

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


function pubcrawl_permissions_create(&$x) {

	// send a follow activity to the followee's inbox

	if($x['recipient']['xchan_network'] !== 'activitypub') {
		return;
	}

	$msg = array_merge(['@context' => [
			'https://www.w3.org/ns/activitystreams',
			[ 'me' => 'http://salmon-protocol.org/ns/magic-env' ],
			[ 'zot' => 'http://purl.org/zot/protocol' ]
		]], 
		[
			'id' => z_root() . '/follow/' . $x['recipient']['abook_id'],
			'type' => 'Follow',
			'actor' => asencode_person($x['sender']),
			'object' => asencode_person($x['recipient'])
	]);

	$jmsg = json_encode($msg);

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
			header('Content-Type: application/activity+json');
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

		header('Content-Type: application/activity+json');
		json_return_and_die($x);

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

		$x = array_merge(['@context' => [
				'https://www.w3.org/ns/activitystreams',
				[ 'me' => 'http://salmon-protocol.org/ns/magic-env' ],
				[ 'zot' => 'http://purl.org/zot/protocol' ]
			]], 
			[
				'id' => z_root() . '/follow/' . $r[0]['abook_id'],
				'type' => 'Follow',
				'actor' => asencode_person($chan),
				'object' => asencode_person($r[0])
		]);
				
		if(pubcrawl_magic_env_allowed()) {
			$x = pubcrawl_salmon_sign(json_encode($x),$chan);
			header('Content-Type: application/magic-envelope+json');
			json_return_and_die($x);
		}
		else {
			header('Content-Type: application/activity+json');
			json_return_and_die($x);
		}
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
		$headers['Content-Type'] = 'application/activity+json';
		$ret = $outq['outq_msg'];
		$hash = HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		$xhead = HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],z_root() . '/channel/' . argv(1),false);
	
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

