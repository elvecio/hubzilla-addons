<?php


/**
 * Name: Diaspora Protocol
 * Description: Diaspora Protocol. Install 'Diaspora Statistics' first if you wish to use public tag relays
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */

// use the new federation protocol
define('DIASPORA_V2',1);

require_once('include/crypto.php');
require_once('include/items.php');
require_once('include/markdown.php');
require_once('include/queue_fn.php');

require_once('addon/diaspora/inbound.php');
require_once('addon/diaspora/outbound.php');
require_once('addon/diaspora/util.php');


function diaspora_load() {

	Zotlabs\Extend\Hook::register_array('addon/diaspora/diaspora.php', [
		'notifier_hub'                => 'diaspora_process_outbound',
		'notifier_process'            => 'diaspora_notifier_process',
		'permissions_create'          => 'diaspora_permissions_create',
		'permissions_update'          => 'diaspora_permissions_update',
		'module_loaded'               => 'diaspora_load_module',
		'follow_allow'                => 'diaspora_follow_allow',
		'feature_settings_post'       => 'diaspora_feature_settings_post',
		'feature_settings'            => 'diaspora_feature_settings',
		'post_local'                  => 'diaspora_post_local',
		'well_known'                  => 'diaspora_well_known',
		'create_identity'             => 'diaspora_create_identity',
		'profile_sidebar'             => 'diaspora_profile_sidebar',
		'discover_channel_webfinger'  => 'diaspora_discover',
		'import_author'               => 'diaspora_import_author',
		'markdown_to_bb_init'         => 'diaspora_markdown_to_bb_init',
		'bb_to_markdown_bb'           => 'diaspora_bb_to_markdown_bb',
		'service_plink'               => 'diaspora_service_plink',
		'import_foreign_channel_data' => 'diaspora_import_foreign_channel_data',
		'personal_xrd'                => 'diaspora_personal_xrd',
		'author_is_pmable'            => 'diaspora_author_is_pmable',
		'can_comment_on_post'         => 'diaspora_can_comment_on_post',
		'queue_deliver'               => 'diaspora_queue_deliver'
	]);

	diaspora_init_relay();
}

function diaspora_unload() {
	Zotlabs\Extend\Hook::unregister_by_file('addon/diaspora/diaspora.php');
}


function diaspora_init_relay() {
	if(! get_config('diaspora','relay_handle')) {
		if(plugin_is_installed('statistics')) {
			$x = ['author' => [ 'address' => 'relay@relay.iliketoast.net', 'network' => 'diaspora' ], 'result' => false ];
			$placeholder = 'placeholder';
			diaspora_import_author($placeholder, $x);
			if($x['result']) {
				set_config('diaspora','relay_handle',$x['result']);
				// Now register
				$url = "https://the-federation.info/register/" . App::get_hostname();
				$ret = z_fetch_url($url);
			}
		}
	}
}

function diaspora_author_is_pmable(&$b) {
	if($b['abook'] && (! intval($b['abook']['abook_not_here'])) && (strpos($b['xchan']['xchan_network'],'diaspora') !== false))
		$b['result'] = true;
}

function diaspora_load_module(&$b) {
	if($b['module'] === 'receive') {
		require_once('addon/diaspora/Mod_Receive.php');
		$b['controller'] = new \Zotlabs\Module\Receive();
		$b['installed'] = true;
	}
	if($b['module'] === 'p') {
		require_once('addon/diaspora/p.php');
		$b['installed'] = true;
	}
	if($b['module'] === 'fetch') {
		require_once('addon/diaspora/Mod_Fetch.php');
		$b['controller'] = new \Zotlabs\Module\Fetch();
		$b['installed'] = true;
	}
}


function diaspora_well_known(&$b) {
	if(argc() > 1 && argv(1) === 'x-social-relay') {
		$disabled = (get_config('system','disable_discover_tab') || get_config('system','disable_diaspora_discover_tab'));
		$scope = 'all';
		$tags = get_config('diaspora','relay_tags');
		if($tags) {
			$disabled = false;
			$scope = 'tags';
		}

		$arr = array(
			'subscribe' => (($disabled) ? false : true),
			'scope' => $scope,
			'tags' => (($tags) ? $tags : [])
		);

		header('Content-type: application/json');
		echo json_encode($arr);
		killme();			

	}
}


function diaspora_personal_xrd(&$b) {

	if(! intval(get_pconfig($b['user']['channel_id'],'system','diaspora_allowed')))
		return;

	$dspr = replace_macros(get_markup_template('xrd_diaspora.tpl','addon/diaspora'),
		[
			'$baseurl'   => z_root(),
			'$dspr_guid' => $b['user']['channel_guid'] . str_replace('.','',\App::get_hostname()),
			'$dspr_key'  => base64_encode(pemtorsa($b['user']['channel_pubkey']))
		]
	);

	$b['xml'] = str_replace('</XRD>',$dspr . "\n" . '</XRD>',$b['xml']);

}




function diaspora_permissions_create(&$b) {
	if($b['recipient']['xchan_network'] === 'diaspora' || $b['recipient']['xchan_network'] === 'friendica-over-diaspora') {

		$b['deliveries'] = diaspora_share($b['sender'],$b['recipient']);
		if($b['deliveries'])
			$b['success'] = 1;
	}
}

function diaspora_permissions_update(&$b) {
	if($b['recipient']['xchan_network'] === 'diaspora' || $b['recipient']['xchan_network'] === 'friendica-over-diaspora') {
		discover_by_webbie($b['recipient']['xchan_hash']);
		$b['success'] = 1;
	}
}

function diaspora_notifier_process(&$arr) {

	// if it is a public post (reply, etc.), add the chosen relay channel to the recipients

	// If target_item isn't set it's likely to be refresh packet.

	if(! ((array_key_exists('target_item',$arr)) && (is_array($arr['target_item'])))) {
		return;
	} 

	// If item_wall doesn't exist, it's not a post - perhaps an email or other DB object

	if(! array_key_exists('item_wall',$arr['target_item']))
		return;
	if(($arr['normal_mode']) && (! $arr['env_recips']) && (! $arr['private']) && (! $arr['upstream'])) {
		$relay = get_config('diaspora','relay_handle');
		if($relay) {
			$arr['recipients'][] = "'" . $relay . "'";
		}
	}
}


function diaspora_process_outbound(&$arr) {

/*

	We are passed the following array from the notifier, providing everything we need to make delivery decisions.

			$arr = array(
				'channel' => $channel,
				'upstream' => $upstream,
				'env_recips' => $env_recips,
				'packet_recips' => $packet_recips,
				'recipients' => $recipients,
				'item' => $item,
				'target_item' => $target_item,
				'hub' => $hub,
				'top_level_post' => $top_level_post,
				'private' => $private,
				'relay_to_owner' => $relay_to_owner,
				'uplink' => $uplink,
				'cmd' => $cmd,
				'mail' => $mail,
				'location' => $location,
				'normal_mode' => $normal_mode,
				'packet_type' => $packet_type,
				'walltowall' => $walltowall,
				'queued' => pass these queued items (outq_hash) back to notifier.php for delivery
			);
*/

	if(! strstr($arr['hub']['hubloc_network'],'diaspora'))
		return;

	logger('upstream: ' . $arr['upstream']);
//	logger('notifier_array: ' . print_r($arr,true), LOGGER_ALL, LOG_INFO);



	// allow this to be set per message

	if(($arr['mail']) && intval($arr['item']['raw'])) {
		logger('Cannot send raw data to Diaspora mail service.');
		return;
	}

	if(array_key_exists('target_item',$arr) && is_array($arr['target_item'])) {
		if(intval($arr['target_item']['item_obscured'])) {
			logger('Cannot send raw data as a Diaspora activity.');
			return;
		}

		if(strpos($arr['target_item']['postopts'],'nodspr') !== false) {
			return;
		}
	}

	$allowed = get_pconfig($arr['channel']['channel_id'],'system','diaspora_allowed');

	if(! intval($allowed)) {
		logger('mod-diaspora: disallowed for channel ' . $arr['channel']['channel_name']);
		return;
	}


	if($arr['location'])
		return;

	// send to public relay server - not ready for prime time

	if(($arr['top_level_post']) && (! $arr['env_recips'])) {
		// Add the relay server to the list of hubs.	
		// = array('hubloc_callback' => 'https://relay.iliketoast.net/receive', 'xchan_pubkey' => 'bogus');
	}

	$target_item = $arr['target_item'];

	if($target_item && array_key_exists('item_obscured',$target_item) && intval($target_item['item_obscured'])) {
		$key = get_config('system','prvkey');
		if($target_item['title'])
			$target_item['title'] = crypto_unencapsulate(json_decode($target_item['title'],true),$key);
		if($target_item['body'])
			$target_item['body'] = crypto_unencapsulate(json_decode($target_item['body'],true),$key);
	}

	$prv_recips = $arr['env_recips'];

	// The Diaspora profile message is unusual in that it is sent privately. 

	if($arr['cmd'] === 'refresh_all' && $arr['recipients']) {
		$prv_recips = array();
		foreach($arr['recipients'] as $r) {
			$prv_recips[] = array('hash' => trim($r,"'"));
		}
	}
			
	if($prv_recips) {
		$hashes = array();

		// re-explode the recipients, but only for this hub/pod

		foreach($prv_recips as $recip)
			$hashes[] = "'" . $recip['hash'] . "'";

		$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s' 
			and xchan_hash in (" . implode(',', $hashes) . ") and xchan_network in ('diaspora', 'friendica-over-diaspora') ",
			dbesc($arr['hub']['hubloc_url'])
		);


		if(! $r) {
			logger('diaspora_process_outbound: no recipients');
			return; 
		}

		foreach($r as $contact) {

			// is $contact connected with this channel - and if the channel is cloned, also on this hub? 
			$single = deliverable_singleton($arr['channel']['channel_id'],$contact);
	
			if($arr['packet_type'] == 'refresh' && $single) {
				$qi = diaspora_profile_change($arr['channel'],$contact);
				if($qi)
					$arr['queued'][] = $qi;
				return;
			}
			if($arr['mail'] && $single) {
				$qi = diaspora_send_mail($arr['item'],$arr['channel'],$contact);
				if($qi)
					$arr['queued'][] = $qi;
				continue;
			}

			if(! $arr['normal_mode'])
				continue;

			// special handling for send_upstream to public post, not checked for $single
			// all other public posts processed as public batches further below

			if((! $arr['private']) && ($arr['upstream'])) {
				$qi = diaspora_send_upstream($target_item,$arr['channel'],$contact, true);
				if($qi)
					$arr['queued'][] = $qi;
				continue;
			}

			if(! $contact['xchan_pubkey'])
				continue;

			// singletons will be sent upstream regardless of $single state. They may be rejected.

			if(intval($target_item['item_deleted']) && ($arr['top_level_post'] || $arr['upstream'])) { 
				$qi = diaspora_send_retraction($target_item,$arr['channel'],$contact);
				if($qi)
					$arr['queued'][] = $qi;
				continue;
			}

			if($arr['upstream']) {
				// send comments and likes to owner to relay
				$qi = diaspora_send_upstream($target_item,$arr['channel'],$contact,false,(($arr['uplink'] && !$arr['relay_to_owner']) ? true : false));
				if($qi)
					$arr['queued'][] = $qi;
				continue;
			}

			// downstream (private) posts

			if(! $single) {
				logger('Singleton private delivery ignored on this site.');
				continue;
			}
				
			if($arr['top_level_post']) {
				$qi = diaspora_send_status($target_item,$arr['channel'],$contact);
				if($qi) {
					foreach($qi as $q)
						$arr['queued'][] = $q;
				}
				continue;
			}
			else {
				// we are the relay - send comments, likes and relayable_retractions
				// (of comments and likes) to our conversants
				$qi = diaspora_send_downstream($target_item,$arr['channel'],$contact);
				if($qi)
					$arr['queued'][] = $qi;
				continue;
			}
		}
	}
	else {
		// public message

		$contact = $arr['hub'];

		if($arr['packet_type'] === 'keychange') {
			$target_item = get_pconfig($arr['channel']['channel_id'],'system','keychange');
			$qi = diaspora_send_migration($target_item,$arr['channel'],$contact,true);
			if($qi)
				$arr['queued'][] = $qi;
			return;
		}
		if(intval($target_item['item_deleted']) 
			&& ($target_item['mid'] === $target_item['parent_mid'])) {
			// top-level retraction
			logger('delivery: diaspora retract: ' . $loc);
			$qi = diaspora_send_retraction($target_item,$arr['channel'],$contact,true);
			if($qi)
				$arr['queued'][] = $qi;
			return;
		}
		elseif($target_item['mid'] !== $target_item['parent_mid']) {
			// we are the relay - send comments, likes and relayable_retractions to our conversants
			logger('delivery: diaspora relay: ' . $loc);
			$qi = diaspora_send_downstream($target_item,$arr['channel'],$contact,true);
			if($qi)
				$arr['queued'][] = $qi;
			return;
		}
		elseif($arr['top_level_post']) {
			if(perm_is_allowed($arr['channel']['channel_id'],'','view_stream')) {
				logger('delivery: diaspora status: ' . $loc);
				$qi = diaspora_send_status($target_item,$arr['channel'],$contact,true);
				if($qi) {
					foreach($qi as $q)
						$arr['queued'][] = $q;
				}
				return;
			}
		}
	}
}





function diaspora_queue($owner,$contact,$slap,$public_batch,$message_id = '') {


	$allowed = get_pconfig($owner['channel_id'],'system','diaspora_allowed',1);

	if(! intval($allowed)) {
		return false;
	}

	if($public_batch)
		$dest_url = $contact['hubloc_callback'] . '/public';
	else
		$dest_url = $contact['hubloc_callback'] . '/users/' . $contact['hubloc_guid'];


	logger('diaspora_queue: URL: ' . $dest_url, LOGGER_DEBUG);	

	if(intval(get_config('system','diaspora_test')) || intval(get_pconfig($owner['channel_id'],'system','diaspora_test'))) {
		logger('diaspora test mode - delivery disabled');
		return false;
	}

	$hash = random_string();

	logger('diaspora_queue: ' . $hash . ' ' . $dest_url, LOGGER_DEBUG);

	queue_insert(array(
		'hash'       => $hash,
		'account_id' => $owner['channel_account_id'],
		'channel_id' => $owner['channel_id'],
		'driver'     => 'diaspora',
		'posturl'    => $dest_url,
		'notify'     => '',
		'msg'        => $slap
	));

	if($message_id && (! get_config('system','disable_dreport'))) {
		q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_result, dreport_time, dreport_xchan, dreport_queue ) values ( '%s','%s','%s','%s','%s','%s','%s' ) ",
			dbesc($message_id),
			dbesc($dest_url),
			dbesc($dest_url),
			dbesc('queued'),
			dbesc(datetime_convert()),
			dbesc($owner['channel_hash']),
			dbesc($hash)
		);
	}

	return $hash;

}


function diaspora_follow_allow(&$b) {

	if($b['xchan']['xchan_network'] !== 'diaspora' && $b['xchan']['xchan_network'] !== 'friendica-over-diaspora')
		return;

	$allowed = get_pconfig($b['channel_id'],'system','diaspora_allowed');
	if($allowed === false)
		$allowed = 1;
	$b['allowed'] = $allowed;
	$b['singleton'] = 1;  // this network does not support channel clones
}


function diaspora_discover(&$b) {

	require_once('include/network.php');

	$webbie = $b['address'];

	$protocol = $b['protocol'];
	if($protocol && strtolower($protocol) !== 'diaspora')
		return;


	$result = array();
	$network = null;
	$diaspora = false;

	$diaspora_base = '';
	$diaspora_guid = '';
	$diaspora_key = '';
	$guid = '';

	$dfrn = false;

	$x = $b['webfinger'];

	if($x && array_key_exists('links',$x) && $x['links']) {
		foreach($x['links'] as $link) {
			if(array_key_exists('rel',$link)) {

				if($link['rel'] === NAMESPACE_DFRN)
					$dfrn = $link['href'];				

				if($link['rel'] === 'http://joindiaspora.com/seed_location') {
					$diaspora_base = $link['href'];
					$diaspora = true;
				}
				if($link['rel'] === 'http://joindiaspora.com/guid') {
					$diaspora_guid = $link['href'];
					$diaspora = true;
				}
				if($link['rel'] === 'diaspora-public-key') {
					$diaspora_key = base64_decode($link['href']);
					if(strstr($diaspora_key,'RSA '))
						$pubkey = rsatopem($diaspora_key);
					else
						$pubkey = $diaspora_key;
					$diaspora = true;
				}
				if($link['rel'] === 'http://microformats.org/profile/hcard')
					$hcard = $link['href'];
				if($link['rel'] === 'http://webfinger.net/rel/profile-page')
					$profile = $link['href'];
			}
		}
	}

	if($diaspora && $diaspora_base) {
		$x = false;
	}

	if(! $x) {
		$x = old_webfinger($webbie);
	}

	if($x) {
		logger('old_webfinger: ' . print_r($x,true));
		foreach($x as $link) {
			if($link['@attributes']['rel'] === NAMESPACE_DFRN)
				$dfrn = unamp($link['@attributes']['href']);				
			if($link['@attributes']['rel'] === 'http://microformats.org/profile/hcard')
				$hcard = unamp($link['@attributes']['href']);
			if($link['@attributes']['rel'] === 'http://webfinger.net/rel/profile-page')
				$profile = unamp($link['@attributes']['href']);
			if($link['@attributes']['rel'] === 'http://joindiaspora.com/seed_location') {
				$diaspora_base = unamp($link['@attributes']['href']);
				$diaspora = true;
			}
			if($link['@attributes']['rel'] === 'http://joindiaspora.com/guid') {
				$diaspora_guid = unamp($link['@attributes']['href']);
				$diaspora = true;
			}
			if($link['@attributes']['rel'] === 'diaspora-public-key') {
				$diaspora_key = base64_decode(unamp($link['@attributes']['href']));
				if(strstr($diaspora_key,'RSA '))
					$pubkey = rsatopem($diaspora_key);
				else
					$pubkey = $diaspora_key;
				$diaspora = true;
			}
		}
	}

	if($diaspora && $diaspora_base) {

		if($diaspora_guid)
			$guid = $diaspora_guid;

		$diaspora_base = trim($diaspora_base,'/');

		$notify = $diaspora_base . '/receive';

		if(strpos($webbie,'@')) {
			$addr = str_replace('acct:', '', $webbie);
			$hostname = substr($webbie,strpos($webbie,'@')+1);
		}
		$network = 'diaspora';
		// until we get a dfrn layer, we'll use diaspora protocols for Friendica,
		// but give it a different network so we can go back and fix these when we get proper support. 
		// It really should be just 'friendica' but we also want to distinguish
		// between Friendica sites that we can use D* protocols with and those we can't.
		// Some Friendica sites will have Diaspora disabled. 
		if($dfrn)
			$network = 'friendica-over-diaspora';
		if($hcard) {
			$vcard = scrape_vcard($hcard);
			$vcard['nick'] = substr($webbie,0,strpos($webbie,'@'));
			if(! $vcard['fn'])
				$vcard['fn'] = $webbie;
			if(($vcard['uid']) && (! $diaspora_guid))
				$diaspora_guid = $guid = $vcard['uid'];
		} 

		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($addr)
		);

		/**
		 *
		 * Diaspora communications are notoriously unreliable and receiving profile update messages (indeed any messages) 
		 * are pretty much random luck. We'll check the timestamp of the xchan_name_date at a higher level and refresh
		 * this record once a month; because if you miss a profile update message and they update their profile photo or name 
		 * you're otherwise stuck with stale info until they change their profile again - which could be years from now. 
		 *
		 */  			

		if($r) {
			$r = q("update xchan set xchan_name = '%s', xchan_network = '%s', xchan_name_date = '%s' where xchan_hash = '%s'",
				dbesc($vcard['fn']),
				dbesc($network),
				dbesc(datetime_convert()),
				dbesc($addr)
			);
		}
		else {
			$r = xchan_store_lowlevel(
				[
					'xchan_hash'         => $addr,
					'xchan_guid'         => $guid,
					'xchan_pubkey'       => $pubkey,
					'xchan_addr'         => $addr,
					'xchan_url'          => $profile,
					'xchan_name'         => $vcard['fn'],
					'xchan_name_date'    => datetime_convert(),
					'xchan_network'      => $network
				]
			);
		}

		$r = q("select * from hubloc where hubloc_hash = '%s' limit 1",
			dbesc($webbie)
		);

		if(! $r) {
			$r = hubloc_store_lowlevel(
				[
					'hubloc_guid'     => $guid,
					'hubloc_hash'     => $addr,
					'hubloc_addr'     => $addr,
					'hubloc_network'  => $network,
					'hubloc_url'      => trim($diaspora_base,'/'),
					'hubloc_host'     => $hostname,
					'hubloc_callback' => $notify,
					'hubloc_updated'  => datetime_convert(),
					'hubloc_primary'  => 1
				]
			);
		}

		$photos = import_xchan_photo($vcard['photo'],$addr);
		$r = q("update xchan set xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s'",
			dbescdate(datetime_convert('UTC','UTC',$arr['photo_updated'])),
			dbesc($photos[0]),
			dbesc($photos[1]),
			dbesc($photos[2]),
			dbesc($photos[3]),
			dbesc($addr)
		);
		$b['success'] = true;
	}
}


function diaspora_feature_settings_post(&$b) {

	if($_POST['diaspora-submit']) {
		set_pconfig(local_channel(),'system','diaspora_allowed',intval($_POST['dspr_allowed']));
		set_pconfig(local_channel(),'system','diaspora_public_comments',intval($_POST['dspr_pubcomment']));
		set_pconfig(local_channel(),'system','prevent_tag_hijacking',intval($_POST['dspr_hijack']));
		set_pconfig(local_channel(),'diaspora','sign_unsigned',intval($_POST['dspr_sign']));

		$followed = $_POST['dspr_followed'];
		$ntags = array();
		if($followed) {
			$tags = explode(',', $followed);
			foreach($tags as $t) {
				$t = trim($t);
				if($t)
					$ntags[] = $t;
			}
		}
		set_pconfig(local_channel(),'diaspora','followed_tags',$ntags);

		if(plugin_is_installed('statistics'))
			diaspora_build_relay_tags();
		
		info( t('Diaspora Protocol Settings updated.') . EOL);
	}
}


function diaspora_feature_settings(&$s) {

	diaspora_init_relay();

	$dspr_allowed = get_pconfig(local_channel(),'system','diaspora_allowed');
	$pubcomments  = get_pconfig(local_channel(),'system','diaspora_public_comments',1);
	$hijacking    = get_pconfig(local_channel(),'system','prevent_tag_hijacking');
	$signing      = get_pconfig(local_channel(),'diaspora','sign_unsigned');
	$followed     = get_pconfig(local_channel(),'diaspora','followed_tags');
	if(is_array($followed))
		$hashtags = implode(',',$followed);
	else
		$hashtags = '';

	$sc = '<div>' . t('The Diaspora protocol does not support location independence. Connections you make within that network may be unreachable from alternate channel locations.') . '</div><br>';

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('dspr_allowed', t('Enable the Diaspora protocol for this channel'), $dspr_allowed, '', $yes_no),
	));

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('dspr_pubcomment', t('Allow any Diaspora member to comment on your public posts'), $pubcomments, '', $yes_no),
	));

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('dspr_hijack', t('Prevent your hashtags from being redirected to other sites'), $hijacking, '', $yes_no),
	));

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('dspr_sign', t('Sign and forward posts and comments with no existing Diaspora signature'), $signing, '', $yes_no),
	));

	if(plugin_is_installed('statistics')) {
		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('dspr_followed', t('Followed hashtags (comma separated, do not include the #)'), $hashtags, '')
		));
	}

	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('diaspora', '<img src="addon/diaspora/diaspora.png" style="width:auto; height:1em; margin:-3px 5px 0px 0px;">' . t('Diaspora Protocol Settings'), '', t('Submit')),
		'$content'	=> $sc
	));

	return;

}



function diaspora_post_local(&$item) {

	require_once('include/markdown.php');

	if($item['mid'] === $item['parent_mid'])
		return;
	if($item['created'] != $item['edited'])
		return;

	$meta = null;

	$author = channelx_by_hash($item['author_xchan']);
	if($author) {

		$handle = channel_reddress($author);
		$meta = null;

		if($item['verb'] === ACTIVITY_LIKE || $item['verb'] === ACTIVITY_DISLIKE) {
			if($item['thr_parent'] === $item['parent_mid'] && $item['obj_type'] == ACTIVITY_OBJ_NOTE) {
				$meta = [
					'positive'        => (($item['verb'] === ACTIVITY_LIKE) ? 'true' : 'false'),
					'guid'            => $item['mid'],
					'parent_guid'     => $item['parent_mid'],
				];
				if(defined('DIASPORA_V2')) {
					$meta['author']      = $handle;
					$meta['parent_type'] = 'Post';
				}
				else {
					$meta['diaspora_handle'] = $handle;
					$meta['target_type']     = 'Post';
				}
			}
		}
		else {
			$body = bb_to_markdown($item['body'], [ 'diaspora' ]);

			$meta = [
				'guid'            => $item['mid'],
				'parent_guid'     => $item['parent_mid'],
				'text'            => $body
			];

			if(defined('DIASPORA_V2')) {
				$meta['author']     = $handle;
				$meta['created_at'] = datetime_convert('UTC','UTC', $item['created'], ATOM_TIME );
			}
			else {
				$meta['diaspora_handle'] = $handle;
			}

		}

		if(! $meta)
			return;

		$meta['author_signature'] = diaspora_sign_fields($meta, $author['channel_prvkey']);
		if($item['author_xchan'] === $item['owner_xchan']) {
			$meta['parent_author_signature'] = diaspora_sign_fields($meta,$author['channel_prvkey']);
		}
	}

	if($meta)
		set_iconfig($item,'diaspora','fields', $meta, true);


}


function diaspora_create_identity($b) {

	if(get_config('system','diaspora_allowed')) {
		set_pconfig($b,'system','diaspora_allowed','1');
	}

}

function diaspora_import_foreign_channel_data(&$data) {

	if(array_key_exists('user',$data) && array_key_exists('version',$data)) {
		require_once('addon/diaspora/import_diaspora.php');
		$data['handled'] = true;
		import_diaspora_account($data);
		return;
	}
}
		
function diaspora_profile_sidebar(&$x) {

	$profile = $x['profile'];

	$firstname = ((strpos($profile['channel_name'],' '))
		? trim(substr($profile['channel_name'],0,strpos($profile['channel_name'],' '))) : $profile['channel_name']);
	$lastname = (($firstname === $profile['channel_name']) 
			? '' : trim(substr($profile['channel_name'],strlen($firstname))));

	$vcarddata = replace_macros(get_markup_template('diaspora_vcard.tpl','addon/diaspora'), 
		[
			'$podloc'     => z_root(),
			'$guid'       => $profile['channel_guid'] . str_replace('.','',App::get_hostname()),
			'$pubkey'     => pemtorsa($profile['channel_pubkey']),
			'$searchable' => ((observer_prohibited()) ? 'false' : 'true'),
			'$nickname'   => $profile['channel_address'],
			'$fullname'   => $profile['channel_name'],
			'$firstname'  => $firstname,
			'$lastname'   => $lastname,
			'$photo300'   => z_root() . '/photo/profile/300/' . $profile['uid'] . '.jpg',
			'$photo100'   => z_root() . '/photo/profile/100/' . $profile['uid'] . '.jpg',
			'$photo50'    => z_root() . '/photo/profile/50/'  . $profile['uid'] . '.jpg',
		]
	);

	$x['entry'] = str_replace('<div class="hcard-addon"></div>',$vcarddata . '<div class="hcard-addon"></div>', $x['entry']);

}


function diaspora_import_author(&$b) {

	$x = $b['author'];

	if(strpos($x['network'],'diaspora') === false)
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


function diaspora_md_mention_callback($matches) {

    $webbie = $matches[2] . '@' . $matches[3];
    $link = '';
    if($webbie) {
        $r = q("select * from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_addr = '%s' limit 1",
            dbesc($webbie)
        );
        if(! $r) {
            $x = discover_by_webbie($webbie);
            if($x) {
                $r = q("select * from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_addr = '%s' limit 1",
                    dbesc($webbie)
                );
            }
        }
        if($r)
            $link = $r[0]['xchan_url'];
    }
    if(! $link)
        $link = 'https://' . $matches[3] . '/u/' . $matches[2];

    if($r && $r[0]['hubloc_network'] === 'zot')
        return '@[zrl=' . $link . ']' . trim($matches[1]) . ((substr($matches[0],-1,1) === '+') ? '+' : '') . '[/zrl]' ;
    else
        return '@[url=' . $link . ']' . trim($matches[1]) . ((substr($matches[0],-1,1) === '+') ? '+' : '') . '[/url]' ;

}

function diaspora_md_mention_callback2($matches) {

    $webbie = $matches[1] . '@' . $matches[2];
    $link = '';
    if($webbie) {
        $r = q("select * from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_addr = '%s' limit 1",
            dbesc($webbie)
        );
        if(! $r) {
            $x = discover_by_webbie($webbie);
            if($x) {
                $r = q("select * from hubloc left join xchan on hubloc_hash = xchan_hash where hubloc_addr = '%s' limit 1",
                    dbesc($webbie)
                );
            }
        }
        if($r)
            $link = $r[0]['xchan_url'];
    }

    $name = (($r) ? $r[0]['xchan_name'] : $matches[1]);

    if(! $link)
        $link = 'https://' . $matches[2] . '/u/' . $matches[1];

    if($r && $r[0]['hubloc_network'] === 'zot')
        return '@[zrl=' . $link . ']' . trim($name) . ((substr($matches[0],-1,1) === '+') ? '+' : '') . '[/zrl]' ;
    else
        return '@[url=' . $link . ']' . trim($name) . ((substr($matches[0],-1,1) === '+') ? '+' : '') . '[/url]' ;

}

function diaspora_markdown_to_bb_init(&$s) {

	// if empty link text replace with the url
	$s = preg_replace("/\[\]\((.*?)\)/ism",'[$1]($1)',$s);

  	$s = preg_replace_callback('/\@\{(.+?)\; (.+?)\@(.+?)\}\+/','diaspora_md_mention_callback',$s);
	$s = preg_replace_callback('/\@\{(.+?)\; (.+?)\@(.+?)\}/','diaspora_md_mention_callback',$s);

	$s = preg_replace_callback('/\@\{(.+?)\@(.+?)\}\+/','diaspora_md_mention_callback2',$s);
	$s = preg_replace_callback('/\@\{(.+?)\@(.+?)\}/','diaspora_md_mention_callback2',$s);


}


function diaspora_bb_to_markdown_bb(&$x) {

	if(! in_array('diaspora',$x['options']))
		return;	

	$Text = $x['bbcode'];

	$Text = preg_replace_callback('/\@\!?\[([zu])rl\=(\w+.*?)\](\w+.*?)\[\/([zu])rl\]/i', 
		'diaspora_bb_to_markdown_mention_callback', $Text);


	// strip map and embed tags, as the rendering is performed in bbcode() and the resulting output
	// is not compatible with Diaspora (at least in the case of openstreetmap and probably
	// due to the inclusion of an html iframe)

	$Text = preg_replace("/\[map\=(.*?)\]/ism", '$1', $Text);
	$Text = preg_replace("/\[map\](.*?)\[\/map\]/ism", '$1', $Text);

	$Text = preg_replace("/\[embed\](.*?)\[\/embed\]/ism", '$1', $Text);

	$x['bbcode'] = $Text;
}



function diaspora_bb_to_markdown_mention_callback($match) {

	// may-2017 this needs to be updated to the "new protocol" where it is just '{$handle}'
	// but we'll wait until a majority of sites are running the new protocol

    $r = q("select xchan_addr from xchan where xchan_url = '%s'",
        dbesc($match[2])
    );

    if($r)
        return '@{' . $match[3] . ' ; ' . $r[0]['xchan_addr'] . '}';

    return '@' . $match[3];
}

function diaspora_service_plink(&$b) {
	$contact = $b['xchan'];
	$url     = $b['url'];
	$guid    = $b['guid'];

	if($contact['xchan_network'] === 'diaspora')
		$b['plink'] = $url . '/posts/' . $guid;
	if($contact['xchan_network'] === 'friendica-over-diaspora')
		$b['plink'] = $url . '/display/' . $handle . '/' . $guid;


}

function diaspora_can_comment_on_post(&$b) {
	if(local_channel() && strpos($b['item']['comment_policy'],'diaspora') !== false) {
		$b['allowed'] = get_pconfig(local_channel(),'system','diaspora_allowed');
	}
}


function diaspora_queue_deliver(&$b) {

	$outq = $b['outq'];
	$base = $b['base'];
	$immediate = $b['immediate'];


	if($outq['outq_driver'] === 'diaspora') {
		$b['handled'] = true;
		$first_char = substr(trim($outq['outq_msg']),0,1);

		if($first_char === '{')
			$content_type = 'application/json';
		elseif($first_char === '<')
			$content_type = 'application/magic-envelope+xml';
		else
			$content_type = 'application/x-www-form-urlencoded';


		$retries = 0;
		$result = z_post_url($outq['outq_posturl'],$outq['outq_msg'],$retries,[ 'headers' => [ 'Content-type: ' . $content_type ]] );

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
