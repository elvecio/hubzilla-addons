<?php


/**
 * Name: GNU-Social Protocol
 * Description: GNU-Social Protocol
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */


require_once('include/crypto.php');
require_once('include/items.php');
require_once('include/queue_fn.php');
require_once('include/feedutils.php');

function gnusoc_install() {

	//ensure pubsubhubbub plugin is installed

	$x = in_array('pubsubhubbub',App::$plugins);
	if(! $x) {
		App::$plugins[] = 'pubsubhubbub';
		install_plugin('pubsubhubbub');
		set_config('system','addon',implode(', ',App::$plugins));
	}
}


function gnusoc_load() {
	register_hook('module_loaded', 'addon/gnusoc/gnusoc.php', 'gnusoc_load_module');
	register_hook('webfinger', 'addon/gnusoc/gnusoc.php', 'gnusoc_webfinger');
	Zotlabs\Extend\Hook::register('discover_channel_webfinger', 'addon/gnusoc/gnusoc.php', 'gnusoc_discover_channel_webfinger',0,(-5));
	register_hook('personal_xrd', 'addon/gnusoc/gnusoc.php', 'gnusoc_personal_xrd');
	register_hook('follow_allow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_allow');
	register_hook('feature_settings_post', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings_post');
	register_hook('feature_settings', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings');
	register_hook('follow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_local');
	register_hook('create_identity', 'addon/gnusoc/gnusoc.php', 'gnusoc_create_identity');
	register_hook('accept_follow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_local');
	register_hook('permissions_create', 'addon/gnusoc/gnusoc.php', 'gnusoc_permissions_create');
	register_hook('queue_deliver', 'addon/gnusoc/gnusoc.php', 'gnusoc_queue_deliver');
    register_hook('notifier_process','addon/gnusoc/gnusoc.php','gnusoc_notifier_process');
	register_hook('follow_from_feed','addon/gnusoc/gnusoc.php','gnusoc_follow_from_feed');
	register_hook('atom_entry','addon/gnusoc/gnusoc.php','gnusoc_atom_entry');
	register_hook('import_author','addon/gnusoc/gnusoc.php','gnusoc_import_author');
	register_hook('parse_atom','addon/gnusoc/gnusoc.php','gnusoc_parse_atom');
	register_hook('atom_feed','addon/gnusoc/gnusoc.php','gnusoc_atom_feed');
	register_hook('cron_daily','addon/gnusoc/gnusoc.php','gnusoc_cron_daily');
	register_hook('can_comment_on_post','addon/gnusoc/gnusoc.php','gnusoc_can_comment_on_post');
	register_hook('connection_remove','addon/gnusoc/gnusoc.php','gnusoc_connection_remove');


//	register_hook('notifier_hub', 'addon/gnusoc/gnusoc.php', 'gnusoc_process_outbound');
//	register_hook('permissions_update', 'addon/gnusoc/gnusoc.php', 'gnusoc_permissions_update');

}

function gnusoc_unload() {
	unregister_hook('module_loaded', 'addon/gnusoc/gnusoc.php', 'gnusoc_load_module');
	unregister_hook('webfinger', 'addon/gnusoc/gnusoc.php', 'gnusoc_webfinger');
	unregister_hook('discover_channel_webfinger', 'addon/gnusoc/gnusoc.php', 'gnusoc_discover_channel_webfinger');
	unregister_hook('personal_xrd', 'addon/gnusoc/gnusoc.php', 'gnusoc_personal_xrd');
	unregister_hook('follow_allow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_allow');
	unregister_hook('feature_settings_post', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings_post');
	unregister_hook('feature_settings', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings');
	unregister_hook('follow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_local');
	unregister_hook('create_identity', 'addon/gnusoc/gnusoc.php', 'gnusoc_create_identity');
	unregister_hook('accept_follow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_local');
	unregister_hook('permissions_create', 'addon/gnusoc/gnusoc.php', 'gnusoc_permissions_create');
	unregister_hook('queue_deliver', 'addon/gnusoc/gnusoc.php', 'gnusoc_queue_deliver');
    unregister_hook('notifier_process','addon/gnusoc/gnusoc.php','gnusoc_notifier_process');
	unregister_hook('follow_from_feed','addon/gnusoc/gnusoc.php','gnusoc_follow_from_feed');
	unregister_hook('atom_entry','addon/gnusoc/gnusoc.php','gnusoc_atom_entry');
	unregister_hook('import_author','addon/gnusoc/gnusoc.php','gnusoc_import_author');
	unregister_hook('parse_atom','addon/gnusoc/gnusoc.php','gnusoc_parse_atom');
	unregister_hook('atom_feed','addon/gnusoc/gnusoc.php','gnusoc_atom_feed');
	unregister_hook('cron_daily','addon/gnusoc/gnusoc.php','gnusoc_cron_daily');
	unregister_hook('can_comment_on_post','addon/gnusoc/gnusoc.php','gnusoc_can_comment_on_post');
	unregister_hook('connection_remove','addon/gnusoc/gnusoc.php','gnusoc_connection_remove');

}




function gnusoc_load_module(&$a, &$b) {
	if($b['module'] === 'salmon') {
		require_once('addon/gnusoc/salmon.php');
		$b['installed'] = true;
	}
}



function gnusoc_webfinger(&$a,&$b) {
	$b['result']['links'][] = array('rel' => 'salmon', 'href' => z_root() . '/salmon/' . $b['channel']['channel_address']);
	$b['result']['links'][] = array('rel' => 'http://salmon-protocol.org/ns/salmon-replies', 'href' => z_root() . '/salmon/' . $b['channel']['channel_address']);
	$b['result']['links'][] = array('rel' => 'http://salmon-protocol.org/ns/salmon-mention', 'href' => z_root() . '/salmon/' . $b['channel']['channel_address']);
}

function gnusoc_personal_xrd(&$a,&$b) {
	$b['xml'] = str_replace('</XRD>',
		'<Link rel="salmon" href="' . z_root() . '/salmon/' . $b['user']['channel_address'] . '" />' . "\r\n" .  '<Link rel="http://salmon-protocol.org/ns/salmon-replies" href="' . z_root() . '/salmon/' . $b['user']['channel_address'] . '" />' . "\r\n" .  '<Link rel="http://salmon-protocol.org/ns/salmon-mention" href="' . z_root() . '/salmon/' . $b['user']['channel_address'] . '" />' . "\r\n" . '</XRD>', $b['xml']);

}


function gnusoc_follow_allow(&$a, &$b) {

	if($b['xchan']['xchan_network'] !== 'gnusoc')
		return;

	$allowed = get_pconfig($b['channel_id'],'system','gnusoc_allowed',1);
	$b['allowed'] = $allowed;
	$b['singleton'] = 1;  // this network does not support channel clones
}


function gnusoc_follow_local(&$a,&$b) {

	// we are calling this function to handle both
	// 'follow_local' and 'accept_follow' and which both 
	// have slightly different hook params

	if(array_key_exists('channel',$b)) {
		$channel = $b['channel'];
	}	
	else {
		$channel = channelx_by_n($b['channel_id']);
	}

	require_once('addon/pubsubhubbub/pubsubhubbub.php');

	if($b['abook']['abook_xchan'] && $b['abook']['xchan_network'] === 'gnusoc') {
		$hubs = get_xconfig($b['abook']['abook_xchan'],'system','push_hubs');
		if($hubs) {
			foreach($hubs as $hub) {
				pubsubhubbub_subscribe($hub,$channel,$b['abook'],'','subscribe');
			}
		}
	}
}

function gnusoc_cron_daily($a,&$b) {

	// resubscribe periodically so that it doesn't expire
	// should probably cache the channel lookup

	$r = q("select abook_channel, abook_xchan, abook_id, xchan_hash from abook left join xchan on abook_xchan = xchan_hash where xchan_network = 'gnusoc'");
	if($r) {
		require_once('addon/pubsubhubbub/pubsubhubbub.php');
		foreach($r as $rv) {
			$channel = channelx_by_n($rv['abook_channel']);
			if($channel) {
				$hubs = get_xconfig($rv['abook_xchan'],'system','push_hubs');
				if($hubs) {
					foreach($hubs as $hub) {
						pubsubhubbub_subscribe($hub,$channel,$rv,'','subscribe');
					}
				}
			}
		}
	}
}



function gnusoc_connection_remove(&$a,&$b) {
		  
	$r = q("SELECT abook.*, xchan.*
		FROM abook left join xchan on abook_xchan = xchan_hash
		WHERE abook_channel = %d and abook_id = %d LIMIT 1",
		intval($b['channel_id']),
		intval($b['abook_id'])
	);

	if((! $r) || ($r[0]['xchan_network'] !== 'gnusoc'))
		return;

	require_once('addon/pubsubhubbub/pubsubhubbub.php');

	$channel = channelx_by_n($b['channel_id']);
	
	$hubs = get_xconfig($r[0]['xchan_hash'],'system','push_hubs');
	if($hubs) {
		foreach($hubs as $hub) {
			pubsubhubbub_subscribe($hub,$channel,$r[0],'','unsubscribe');
		}
	}

}


function gnusoc_feature_settings_post(&$a,&$b) {

	if($_POST['gnusoc-submit']) {
		set_pconfig(local_channel(),'system','gnusoc_allowed',intval($_POST['gnusoc_allowed']));
		info( t('GNU-Social Protocol Settings updated.') . EOL);
	}
}


function gnusoc_create_identity($a,$b) {

	if(get_config('system','gnusoc_allowed')) {
		set_pconfig($b,'system','gnusoc_allowed','1');
	}

}


function gnusoc_feature_settings(&$a,&$s) {
	$gnusoc_allowed = get_pconfig(local_channel(),'system','gnusoc_allowed');
	if($gnusoc_allowed === false)
		$gnus_allowed = get_config('gnusoc','allowed');	

	$sc = '<div>' . t('The GNU-Social protocol does not support location independence. Connections you make within that network may be unreachable from alternate channel locations.') . '</div><br>';

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('gnusoc_allowed', t('Enable the GNU-Social protocol for this channel'), $gnusoc_allowed, '', $yes_no),
	));

	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('gnusoc', '<img src="addon/gnusoc/gnusoc-32.png" style="width:auto; height:1em; margin:-3px 5px 0px 0px;">' . t('GNU-Social Protocol Settings'), '', t('Submit')),
		'$content'	=> $sc
	));

	return;

}

function get_salmon_key($uri,$keyhash) {
	$ret = array();

	logger('Fetching salmon key for ' . $uri, LOGGER_DEBUG, LOG_INFO);

	$x = webfinger_rfc7033($uri,true);

	logger('webfinger returns: ' . print_r($x,true), LOGGER_DATA, LOG_DEBUG);

	if($x && array_key_exists('links',$x) && $x['links']) {
		foreach($x['links'] as $link) {
			if(array_key_exists('rel',$link) && $link['rel'] === 'magic-public-key') {
				$ret[] = $link['href'];
			}
		}
	}

	else {
		$arr = old_webfinger($uri);

		logger('old webfinger returns: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);

		if(is_array($arr)) {
			foreach($arr as $a) {
				if($a['@attributes']['rel'] === 'magic-public-key') {
					$ret[] = $a['@attributes']['href'];
				}
			}
		}
		else {
			return '';
		}
	}

	// We have found at least one key URL
	// If it's inline, parse it - otherwise get the key

	if(count($ret)) {
		for($x = 0; $x < count($ret); $x ++) {
			if(substr($ret[$x],0,5) === 'data:') {
				$ret[$x] = convert_salmon_key($ret[$x]);
			}
		}
	}


	logger('Key located: ' . print_r($ret,true), LOGGER_DEBUG, LOG_INFO);

	if(count($ret) == 1) {

		// We only found one one key so we don't care if the hash matches.
		// If it's the wrong key we'll find out soon enough because
		// message verification will fail. This also covers some older
		// software which don't supply a keyhash. As long as they only
		// have one key we'll be right.

		return $ret[0];
	}
	else {
		foreach($ret as $a) {
			$hash = base64url_encode(hash('sha256',$a));
			if($hash == $keyhash)
				return $a;
		}
	}

	return '';
}



function slapper($owner,$url,$slap) {


	// does contact have a salmon endpoint?

	if(! strlen($url))
		return;

	if(! $owner['channel_prvkey']) {
		logger(sprintf("channel '%s' (%d) does not have a salmon private key. Send failed.",
		$owner['channel_address'],$owner['channel_id']));
		return;
	}

	logger('slapper called for '  .$url . '. Data: ' . $slap, LOGGER_DATA, LOG_DEBUG);

	// create a magic envelope


	$data      = base64url_encode($slap, false); // do not strip padding 
	$data_type = 'application/atom+xml';
	$encoding  = 'base64url';
	$algorithm = 'RSA-SHA256';
	$keyhash   = base64url_encode(hash('sha256',salmon_key($owner['channel_pubkey'])),true);

	$data = str_replace(array(" ","\t","\r","\n"),array("","","",""),$data);

	// precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

	$precomputed = '.YXBwbGljYXRpb24vYXRvbSt4bWw=.YmFzZTY0dXJs.UlNBLVNIQTI1Ng==';

	$signature  = base64url_encode(rsa_sign($data . $precomputed,$owner['channel_prvkey']));

	$salmon_tpl = get_markup_template('magicsig.tpl','addon/gnusoc/');

	$salmon = replace_macros($salmon_tpl,array(
		'$data'      => $data,
		'$encoding'  => $encoding,
		'$algorithm' => $algorithm,
		'$keyhash'   => $keyhash,
		'$signature' => $signature
	));

	logger('salmon: ' . $salmon, LOGGER_DATA);

	$hash = random_string();

	queue_insert(array(
   		'hash'       => $hash,
        'account_id' => $owner['channel_account_id'],
   		'channel_id' => $owner['channel_id'],
        'driver'     => 'slap',
   		'posturl'    => $url,
   		'notify'     => '',
   		'msg'        => $salmon,
	));

	return $hash;

}


function gnusoc_queue_deliver(&$a,&$b) {
   $outq = $b['outq'];
    if($outq['outq_driver'] !== 'slap')
        return;

    $b['handled'] = true;

	$headers = array(
		'Content-type: application/magic-envelope+xml',
		'Content-length: ' . strlen($outq['outq_msg']));

    $counter = 0;
	$result = z_post_url($outq['outq_posturl'], $outq['outq_msg'], $counter, array('headers' => $headers, 'novalidate' => true));
    if($result['success'] && $result['return_code'] < 300) {
        logger('slap_deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
        if($b['base']) {
            q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
                dbesc(datetime_convert()),
                dbesc($b['base'])
            );
        }
        q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s' limit 1",
            dbesc('accepted for delivery'),
            dbesc(datetime_convert()),
            dbesc($outq['outq_hash'])
        );

        remove_queue_item($outq['outq_hash']);
    }
    else {
        logger('slap_deliver: queue post returned ' . $result['return_code']
            . ' from ' . $outq['outq_posturl'],LOGGER_DEBUG);
            update_queue_item($outq['outq_hash']);
    }
    return;

}



function gnusoc_remote_follow($channel,$xchan) {

	$slap = replace_macros(get_markup_template('follow_slap.tpl','addon/gnusoc/'),array(
		'$author' => atom_render_author('author',$channel),
		'$object' => atom_render_author('as:object',$xchan),
		'$name' => xmlify($channel['channel_name']),
		'$nick' => xmlify($channel['channel_address']),
		'$profile_page' => xmlify(z_root() . '/channel/' . $channel['channel_address']),
		'$thumb' => xmlify($channel['xchan_photo_l']),
		'$item_id' => z_root() . '/display/' . xmlify(random_string()),
		'$title' => xmlify(t('Follow')),
		'$published' => datetime_convert('UTC','UTC','now',ATOM_TIME),
		'$type' => 'html',
		'$content' => xmlify(sprintf( t('%1$s is now following %2$s'),$channel['channel_name'],$xchan['xchan_name'])),
		'$remote_profile' => xmlify($xchan['xchan_url']),
		'$remote_photo' => xmlify($xchan['xchan_photo_l']),
		'$remote_thumb' => xmlify($xchan['xchan_photo_m']),
		'$remote_nick' => xmlify(substr($xchan['xchan_addr'],0,strpos($xchan['xchan_addr'],'@'))),
		'$remote_name' => xmlify($xchan['xchan_name']),
		'$verb' => xmlify(ACTIVITY_FOLLOW),
		'$ostat_follow' => ''
	));


	logger('follow xml: ' . $slap, LOGGER_DATA);

	$deliver = '';

	$y = q("select * from hubloc where hubloc_hash = '%s'",
		dbesc($xchan['xchan_hash'])
	);


	if($y) {
		$deliver = slapper($channel,$y[0]['hubloc_callback'],$slap);
	}

	return $deliver;
}

function gnusoc_permissions_create(&$a,&$b) {
    if($b['recipient']['xchan_network'] === 'gnusoc') {
        $b['deliveries'] = gnusoc_remote_follow($b['sender'],$b['recipient']);
        if($b['deliveries'])
            $b['success'] = 1;
    }
}




function gnusoc_notifier_process(&$a,&$b) {

	logger('notifier process gnusoc');

	//	logger('notifier data: ' . print_r($b,true));

    if(! ($b['normal_mode']))
        return;

    if($b['mail'] || $b['packet_type'] !== 'undefined')
        return;

	if($b['private'] && (! $b['upstream']))
		return;

	if($b['target_item']['public_policy']) {
		logger('non-public post');
		return;
	}

	if($b['top_level_post']) {
		// should have been processed by pubsubhubub
		logger('not a comment');
		return;
	}

    $channel = $b['channel'];

	if(! perm_is_allowed($channel['channel_id'],'','view_stream'))
		return;


	if($b['upstream']) {
		$r = q("select * from abook left join hubloc on abook_xchan = hubloc_hash where hubloc_network = 'gnusoc' and abook_channel = %d and hubloc_hash = '%s'",
			intval($channel['channel_id']),
			dbesc(trim($b['recipients'][0],"'"))
		);
	}
	else {
	    // find gnusoc subscribers following this $owner
		$r = q("select * from abook left join hubloc on abook_xchan = hubloc_hash where hubloc_network = 'gnusoc' and abook_channel = %d",
			intval($channel['channel_id'])
		);
	}

	if(! $r)
		return;

	$recips = array();
	foreach($r as $rr) {
		if(perm_is_allowed($channel['channel_id'],$rr['hubloc_hash'],'view_stream'))
			$recips[] = $rr;

	}

	if(! $recips)
		return;

	$slap = atom_entry($b['target_item'],'html',null,null,false);
	if($b['upstream']) {
		$slap = str_replace('</entry>', '<link rel="mentioned" ostatus:object-type="http://activitystrea.ms/schema/1.0/person" href="' . $r[0]['hubloc_guid'] . '"/></entry>',$slap);
	}

	logger('slap: ' . $slap, LOGGER_DATA);




	$slap = str_replace('<entry>','<entry xmlns="http://www.w3.org/2005/Atom"
      xmlns:thr="http://purl.org/syndication/thread/1.0"
      xmlns:at="http://purl.org/atompub/tombstones/1.0"
      xmlns:media="http://purl.org/syndication/atommedia"
      xmlns:dfrn="http://purl.org/macgirvin/dfrn/1.0" 
      xmlns:zot="http://purl.org/zot"
      xmlns:as="http://activitystrea.ms/spec/1.0/"
      xmlns:georss="http://www.georss.org/georss" 
      xmlns:poco="http://portablecontacts.net/spec/1.0" 
      xmlns:ostatus="http://ostatus.org/schema/1.0" 
	  xmlns:statusnet="http://status.net/schema/api/1/" >',$slap);

 
	foreach($recips as $recip) {
		$h = slapper($channel,$recip['hubloc_callback'],$slap);
        $b['queued'][] = $h;
	}
}



function gnusoc_follow_from_feed(&$a,&$b) {

	$item     = $b['item'];
	$importer = $b['channel'];
	$xchan    = $b['xchan'];
	$author   = $b['author'];

	if($b['caught'])
		return;

	$b['caught'] = true;

	logger('follow activity received');

	logger('author link: ' . $author['author_link']);

	$id = $item['obj']['id'];
	if((! $id) || (strpos($id,z_root()) === false) || (! strpos($id,$importer['channel_address']))) {
		logger('follow was for somebody else: ' . $id);
		return;
	}

	if(($author) && ($xchan) && (! array_key_exists('xchan_hash',$xchan))) {

		$r = q("select * from xchan where xchan_guid = '%s' limit 1",
   			dbesc($author['author_link'])
		);
		if(! $r) {
			if(discover_by_webbie($author['author_link'])) {
				$r = q("select * from xchan where xchan_guid = '%s' limit 1",
					dbesc($author['author_link'])
   				);
				if(! $r) {
					logger('discovery failed');
					return;
				}
			}
		}
		$xchan = $r[0];
	}

	$x = \Zotlabs\Access\PermissionRoles::role_perms('social');
	$their_perms = \Zotlabs\Access\Permissions::FilledPerms($x['perms_connect']);
	$their_perms['post_comments'] = 1;

	$r = q("select * from abook where abook_channel = %d and abook_xchan = '%s' limit 1",
		intval($importer['channel_id']),
		dbesc($xchan['xchan_hash'])
	);

	$p = \Zotlabs\Access\Permissions::connect_perms($importer['channel_id']);
	$my_perms  = $p['perms'];
	$automatic = $p['automatic'];


	if($r) {
		$contact = $r[0];

		$abook_instance = $contact['abook_instance'];
		if(strpos(z_root(),$abook_instance) === false) {
			if($abook_instance)
				$abook_instance .= ',';
			$abook_instance .= z_root();

			$r = q("update abook set abook_instance = '%s', abook_not_here = 0 where abook_id = %d and abook_channel = %d",
				dbesc($abook_instance),
				intval($contact['abook_id']),
				intval($importer['channel_id'])
			);
		}

		foreach($their_perms as $k => $v)
			set_abconfig($importer['channel_id'],$contact['abook_xchan'],'their_perms',$k,$v);
	}
	else {

		$closeness = get_pconfig($importer['channel_id'],'system','new_abook_closeness');
		if($closeness === false)
			$closeness = 80;
		
		$r = abook_store_lowlevel(
			[
				'abook_account'   => intval($importer['channel_account_id']),
				'abook_channel'   => intval($importer['channel_id']),
				'abook_xchan'     => $xchan['xchan_hash'],
				'abook_closeness' => intval($closeness),
				'abook_created'   => datetime_convert(),
				'abook_updated'   => datetime_convert(),
				'abook_connected' => datetime_convert(),
				'abook_dob'       => NULL_DATE,
				'abook_pending'   => intval(($automatic) ? 0 : 1),
				'abook_instance'  => z_root()
			]
		);

		if($r) {

			if($my_perms)
				foreach($my_perms as $k => $v)
					set_abconfig($importer['channel_id'],$xchan['xchan_hash'],'my_perms',$k,$v);

			if($their_perms)
				foreach($their_perms as $k => $v)
					set_abconfig($importer['channel_id'],$xchan['xchan_hash'],'their_perms',$k,$v);

			logger("New GNU-Social follower received for {$importer['channel_name']}");

			$new_connection = q("select * from abook left join xchan on abook_xchan = xchan_hash left join hubloc on hubloc_hash = xchan_hash where abook_channel = %d and abook_xchan = '%s' order by abook_created desc limit 1",
				intval($importer['channel_id']),
				dbesc($xchan['xchan_hash'])
			);
		
			if($new_connection) {
				\Zotlabs\Lib\Enotify::submit(array(
					'type'       => NOTIFY_INTRO,
					'from_xchan'   => $xchan['xchan_hash'],
					'to_xchan'     => $importer['channel_hash'],
					'link'         => z_root() . '/connedit/' . $new_connection[0]['abook_id'],
				));

				if($default_perms) {
					// Send back a sharing notification to them
					$deliver = gnusoc_remote_follow($importer,$new_connection[0]);
					if($deliver)
						Zotlabs\Daemon\Master::Summon(array('Deliver',$deliver));
				}

				$clone = array();
				foreach($new_connection[0] as $k => $v) {
					if(strpos($k,'abook_') === 0) {
						$clone[$k] = $v;
					}
				}
				unset($clone['abook_id']);
				unset($clone['abook_account']);
				unset($clone['abook_channel']);

				$abconfig = load_abconfig($importer['channel_id'],$clone['abook_xchan']);
	
		 		if($abconfig)
					$clone['abconfig'] = $abconfig;

				build_sync_packet($importer['channel_id'], array('abook' => array($clone)));

			}
		}
	}

	return;
}

function gnusoc_atom_entry($a,&$b) {
	$item = $b['item'];

	if($item['parent']) {
		$conv = get_iconfig($item['parent'],'ostatus','conversation',$item['parent_mid']);
	}
	else {
		$conv = $item['parent_mid'];
	}

	$conv_link = z_root() . '/display/' . $conv;

	if(! strpos($conv,':'))
		$conv = 'X-ZOT:' . $conv;

	$o = '<link rel="ostatus:conversation" href="' . xmlify($conv_link) . '"/>' . "\r\n";
	$o .= '<ostatus:conversation>' . xmlify($conv) . '</ostatus:conversation>' . "\r\n";

	$b['entry'] = str_replace('</entry>', $o . '</entry>', $b['entry']);

}

function gnusoc_atom_feed($a,&$b) {
    $x = preg_match('|' . 'href\=(.*?)' . z_root() . '/channel/(.*?) |',$b,$matches);

	$y = preg_match('|' . z_root() . '/photo/profile/l/(.*?)"|',$b,$matches2);

    if($x) {
        $b = str_replace('</generator>','</generator>' . "\r\n  " .
        '<link rel="salmon" href="' . z_root() . '/salmon/' . $matches[2] . ' />' . "\r\n  " .
        '<link rel="http://salmon-protocol.org/ns/salmon-replies" href="' . z_root() . '/salmon/' . $matches[2] . ' />' . 
		"\r\n  " .
        '<link rel="http://salmon-protocol.org/ns/salmon-mention" href="' . z_root() . '/salmon/' . $matches[2] . ' />',$b);
    }
	if($y) {
		$b = str_replace('</generator>','</generator>' . "\r\n  " .
		'<logo>' . z_root() . '/photo/profile/l/' . $matches2[1] . '</logo>',$b);
	}
}

function gnusoc_parse_atom($a,&$b) {

	$item = $b['item'];

  	$rawconv = $item->get_item_tags(NAMESPACE_OSTATUS,'conversation');
    if($rawconv && $rawconv[0]['data']) {
		// this is currently done inside include/feedutils
		// set_iconfig($b['result'],'ostatus','conversation',normalise_id(unxmlify($rawconv[0]['data'])),true);
		$b['result']['comment_policy'] = 'authenticated';
	}

	if($b['result']['app'] === 'web')
		$b['result']['app'] = 'GNU-Social';

	$mastscope = $item->get_item_tags('http://mastodon.social/schema/1.0','scope');
	if($mastscope)
		$b['result']['title'] = '';

}

function gnusoc_discover_channel_webfinger($a,&$b) {

	// allow more advanced protocols to win, use this as last resort
	// if there more than one protocol is supported

	if($b['success'])
		return;

	require_once('include/network.php');

	$webbie = $b['address'];

	$result = array();
	$network = null;
	$gnusoc = false;

	$salmon_key = null;
	$salmon = '';
	$atom_feed = null;
	$uri = '';
	$avatar = '';

	$x = $b['webfinger'];

	if($x && array_key_exists('links',$x) && $x['links']) {
		foreach($x['links'] as $link) {
			if(array_key_exists('rel',$link)) {
				if($link['rel'] == 'magic-public-key') {
					if(substr($link['href'],0,5) === 'data:') {
						$salmon_key = convert_salmon_key($link['href']);
					}
				}
				if($link['rel'] == 'salmon') {
					$has_salmon = true;
					$salmon = $link['href'];
				}
				if($link['rel'] == 'http://schemas.google.com/g/2010#updates-from') {
					$atom_feed = $link['href'];
				}
			}
		}
	}

	if(! ($salmon_key && $atom_feed)) {
		$x = old_webfinger($webbie);
		if($x) {
			logger('old_webfinger: ' . print_r($x,true));
			foreach($x as $link) {
				if($link['@attributes']['rel'] === 'salmon')
					$salmon = unamp($link['@attributes']['href']);
				if($link['@attributes']['rel'] === NAMESPACE_FEED)
					$atom_feed = unamp($link['@attributes']['href']);
				if($link['@attributes']['rel'] === 'http://webfinger.net/rel/profile-page')
					$uri = unamp($link['@attributes']['href']);
			}
		}
	}

	if($atom_feed && $salmon_key)
		$gnusoc = true;

	if(! $gnusoc)
		return;

	$k = z_fetch_url($atom_feed);
	if($k['success'])
		$feed_meta = feed_meta($k['body']);
		
	if($feed_meta) {

		// stash any discovered pubsubhubbub hubs in case we need to follow them
		// this will save an expensive lookup later

		if($feed_meta['hubs'] && $b['address']) {
			set_xconfig($b['address'],'system','push_hubs',$feed_meta['hubs']);
			set_xconfig($b['address'],'system','feed_url',$atom_feed);
		}
		if($feed_meta['author']['author_name']) {
			$fullname = $feed_meta['author']['author_name'];
		}
		if(! $avatar) {
			if($feed_meta['author']['author_photo'])
				$avatar = $feed_meta['author']['author_photo'];
		}

		if((! $uri) && ($feed_meta['author']['author_uri']))
			$uri = $feed_meta['author']['author_uri'];

	}

	if($uri) {
		$m = parse_url($uri);
		$base = $m['scheme'] . '://' . $m['host'];
		$host = $m['host'];
	}

	if(! $fullname)
		$fullname = $b['address'];


	if(! ($uri && $fullname && $salmon_key && $salmon))
		return false;

	$r = q("select * from xchan where xchan_hash = '%s' limit 1",
		dbesc($b['address'])
	);

	if($r) {
		$r = q("update xchan set xchan_name = '%s', xchan_network = '%s', xchan_name_date = '%s' where xchan_hash = '%s'",
			dbesc($fullname),
			dbesc('gnusoc'),
			dbesc(datetime_convert()),
			dbesc($b['address'])
		);
	}
	else {
		$r = xchan_store_lowlevel(
			[
				'xchan_hash'		 => $b['address'],
				'xchan_guid'		 => $uri,
				'xchan_pubkey'       => $salmon_key,
				'xchan_addr'		 => $b['address'],
				'xchan_url'          => $uri,
				'xchan_name'		 => $fullname,
				'xchan_name_date'    => datetime_convert(),
				'xchan_network'      => 'gnusoc'
			]
		);
	}
	$r = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($b['address'])
	);

	if(! $r) {
		$r = hubloc_store_lowlevel(
			[
				'hubloc_guid'     => $uri,
				'hubloc_hash'     => $b['address'],
				'hubloc_addr'     => $b['address'],
				'hubloc_network'  => 'gnusoc',
				'hubloc_url'	  => $base,
				'hubloc_host'     => $host,
				'hubloc_callback' => $salmon,
				'hubloc_updated'  => datetime_convert(),
				'hubloc_primary'  => 1
			]
		);
	}

	$photos = import_xchan_photo($avatar,$b['address']);
	$r = q("update xchan set xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s'",
		dbescdate(datetime_convert('UTC','UTC',$arr['photo_updated'])),
		dbesc($photos[0]),
		dbesc($photos[1]),
		dbesc($photos[2]),
		dbesc($photos[3]),
		dbesc($b['address'])
	);
	
	$b['success'] = true;

}


function gnusoc_import_author(&$a,&$b) {

	$x = $b['author'];

	if(strpos($x['network'],'gnusoc') === false)
		return;

	if(! $x['address'])
		return;

	$r = q("select * from xchan where xchan_addr = '%s' limit 1",
		dbesc($x['address'])
	);
	if($r) {
		logger('in_cache: ' . $x['address'], LOGGER_DATA);
		$b['result'] = $r[0]['xchan_hash'];
		return;
	}

	if(discover_by_webbie($x['address'])) {
		$r = q("select xchan_hash from xchan where xchan_addr = '%s' limit 1",
			dbesc($x['address'])
		);
		if($r) {
			$b['result'] = $r[0]['xchan_hash'];
			return;
		}
	}

	return;

}

function gnusoc_can_comment_on_post($a,&$b) {

	if($b['allowed'] !== 'unset')
		return;
	if($b['item']['owner']['xchan_network'] === 'gnusoc' && $b['observer_hash'] !== '') {
		$b['allowed'] = true;
	}

}
