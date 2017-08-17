<?php

class Diaspora_Receiver {

	protected $importer;
	protected $xmlbase;
	protected $msg;


	function __construct($importer,$xmlbase,$msg) {
		$this->importer = $importer;
		$this->xmlbase = $xmlbase;
		$this->msg = $msg;
	}

	function request() {

		/* sender is now sharing with recipient */

		$sender_handle    = $this->get_author();
		$recipient_handle = $this->get_recipient();

		// @TODO - map these perms to $newperms below

		if(array_key_exists('following',$this->xmlbase) && array_key_exists('sharing',$this->xmlbase)) {
			$following = (($this->get_property('following')) === 'true' ? true : false);
			$sharing   = (($this->get_property('sharing'))   === 'true' ? true : false);
		}
		else {
			$following = true;
			$sharing   = true;
		}

		if((! $sender_handle) || (! $recipient_handle))
			return;


		// Do we already have an abook record? 

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$sender_handle);

		// Please note some permissions such as PERMS_R_PAGES are impossible for Disapora.
		// They cannot currently authenticate to our system.

		$x = \Zotlabs\Access\PermissionRoles::role_perms('social');
		$their_perms = \Zotlabs\Access\Permissions::FilledPerms($x['perms_connect']);

		if($contact && $contact['abook_id']) {

			// perhaps we were already sharing with this person. Now they're sharing with us.
			// That makes us friends. Maybe.

			foreach($their_perms as $k => $v)
				set_abconfig($this->importer['channel_id'],$contact['abook_xchan'],'their_perms',$k,$v);

			$abook_instance = $contact['abook_instance'];

			if(strpos($abook_instance,z_root()) === false) {
				if($abook_instance) 
					$abook_instance .= ',';
				$abook_instance .= z_root();

				$r = q("update abook set abook_instance = '%s', abook_not_here = 0 where abook_id = %d and abook_channel = %d",
					dbesc($abook_instance),
					intval($contact['abook_id']),
					intval($this->importer['channel_id'])
				);
			}
			return;
		}

		$ret = find_diaspora_person_by_handle($sender_handle);

		if((! $ret) || (! strstr($ret['xchan_network'],'diaspora'))) {
			logger('diaspora_request: Cannot resolve diaspora handle ' . $sender_handle . ' for ' . $recipient_handle);
			return;
		}

		$p = \Zotlabs\Access\Permissions::connect_perms($this->importer['channel_id']);
		$my_perms  = $p['perms'];
		$automatic = $p['automatic'];

		$closeness = get_pconfig($this->importer['channel_id'],'system','new_abook_closeness');
		if($closeness === false)
			$closeness = 80;

		$r = abook_store_lowlevel(
			[
				'abook_account'   => intval($this->importer['channel_account_id']),
				'abook_channel'   => intval($this->importer['channel_id']),
				'abook_xchan'     => $ret['xchan_hash'],
				'abook_closeness' => intval($closeness),
				'abook_created'   => datetime_convert(),
				'abook_updated'   => datetime_convert(),
				'abook_connected' => datetime_convert(),
				'abook_dob'       => NULL_DATE,
				'abook_pending'   => intval(($automatic) ? 0 : 1),
				'abook_instance'  => z_root()
			]
		);
		
		if($my_perms)
			foreach($my_perms as $k => $v)
				set_abconfig($this->importer['channel_id'],$ret['xchan_hash'],'my_perms',$k,$v);

		if($their_perms)
			foreach($their_perms as $k => $v)
				set_abconfig($this->importer['channel_id'],$ret['xchan_hash'],'their_perms',$k,$v);


		if($r) {
			logger("New Diaspora introduction received for {$this->importer['channel_name']}");

			$new_connection = q("select * from abook left join xchan on abook_xchan = xchan_hash left join hubloc on hubloc_hash = xchan_hash where abook_channel = %d and abook_xchan = '%s' order by abook_created desc limit 1",
				intval($this->importer['channel_id']),
				dbesc($ret['xchan_hash'])
			);
			if($new_connection) {
				\Zotlabs\Lib\Enotify::submit(
					[
						'type'	       => NOTIFY_INTRO,
						'from_xchan'   => $ret['xchan_hash'],
						'to_xchan'     => $this->importer['channel_hash'],
						'link'         => z_root() . '/connedit/' . $new_connection[0]['abook_id'],
					]
				);

				if($my_perms && $automatic) {
					// Send back a sharing notification to them
					$x = diaspora_share($this->importer,$new_connection[0]);
					if($x)
						Zotlabs\Daemon\Master::Summon(array('Deliver',$x));
		
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
		
				$abconfig = load_abconfig($this->importer['channel_id'],$clone['abook_xchan']);

				if($abconfig)
					$clone['abconfig'] = $abconfig;

				build_sync_packet($this->importer['channel_id'], [ 'abook' => array($clone) ] );

			}
		}

		// find the abook record we just created

		$contact_record = diaspora_get_contact_by_handle($this->importer['channel_id'],$sender_handle);

		if(! $contact_record) {
			logger('diaspora_request: unable to locate newly created contact record.');
			return;
		}

		/* If there is a default group for this channel, add this member to it */

		if($this->importer['channel_default_group']) {
			require_once('include/group.php');
			$g = group_rec_byhash($this->importer['channel_id'],$this->importer['channel_default_group']);
			if($g)
				group_add_member($this->importer['channel_id'],'',$contact_record['xchan_hash'],$g['id']);
		}

		return;
	}



	function post() {

		$guid            = notags($this->get_property('guid'));
		$diaspora_handle = notags($this->get_author());
		$app             = notags($this->get_property('provider_display_name'));
		$raw_location    = $this->get_property('location');


		if($diaspora_handle != $this->msg['author']) {
			logger('diaspora_post: Potential forgery. Message handle is not the same as envelope sender.');
			return 202;
		}

		$xchan = find_diaspora_person_by_handle($diaspora_handle);

		if((! $xchan) || (! strstr($xchan['xchan_network'],'diaspora'))) {
			logger('Cannot resolve diaspora handle ' . $diaspora_handle);
			return;
		}

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$diaspora_handle);

		if(! $app) {
			if(strstr($xchan['xchan_network'],'friendica'))
				$app = 'Friendica';
			else
				$app = 'Diaspora';
		}

		$created = notags($this->get_property('created_at'));
		$private = (($this->get_property('public') === 'false') ? 1 : 0);


		$r = q("SELECT id FROM item WHERE uid = %d AND mid = '%s' LIMIT 1",
			intval($this->importer['channel_id']),
			dbesc($guid)
		);

		$updated = false;

		if($r) {
			// check dates if post editing is implemented

			$edited = datetime_convert('UTC','UTC',$created);
			if($edited > $r[0]['edited']) { 
				$updated = true;
			}
			else {
				logger('diaspora_post: message exists: ' . $guid);
				return;
			}
		}


		$body = markdown_to_bb($this->get_body());


		// photo could be a single photo or an array of photos.
		// Turn singles into an array of one. 

		$photos = $this->get_property('photo');
		if(is_array($photos) && array_key_exists('guid',$photos))
			$photos = array($photos);

		if($photos) {
			$tmp = '';
			foreach($photos as $ph) {
				if((! $ph['remote_photo_path']) || (strpos($ph['remote_photo_path'],'http') !== 0))
					continue; 
				$tmp .= '[img]' . $ph['remote_photo_path'] . $ph['remote_photo_name'] . '[/img]' . "\n\n";
			}
			
			$body = $tmp . $body;
			$body = scale_external_images($body);
		}

		$maxlen = get_max_import_size();

		if($maxlen && mb_strlen($body) > $maxlen) {
			$body = mb_substr($body,0,$maxlen,'UTF-8');
			logger('message length exceeds max_import_size: truncated');
		}

		$datarray = array();

		// Look for tags and linkify them
		$results = linkify_tags('', $body, $this->importer['channel_id'], true);

		$datarray['term'] = array();

		if($results) {
			foreach($results as $result) {
				$success = $result['success'];
				if($success['replaced']) {
					$datarray['term'][] = [
						'uid'   => $this->importer['channel_id'],
						'ttype' => $success['termtype'],
						'otype' => TERM_OBJ_POST,
						'term'  => $success['term'],
						'url'   => $success['url']
					];
				}
			}
		}

		$found_tags = false;
		$followed_tags = get_pconfig($this->importer['channel_id'],'diaspora','followed_tags');
		if($followed_tags && $datarray['term']) {
			foreach($datarray['term'] as $t) {
				if(in_array(mb_strtolower($t['term']),array_map('mb_strtolower',$followed_tags))) {
					$found_tags = true;
					break;
				}
			}
		}


		$cnt = preg_match_all('/@\[url=(.*?)\](.*?)\[\/url\]/ism',$body,$matches,PREG_SET_ORDER);
		if($cnt) {
			foreach($matches as $mtch) {
				$datarray['term'][] = [
					'uid'   => $this->importer['channel_id'],
					'ttype' => TERM_MENTION,
					'otype' => TERM_OBJ_POST,
					'term'  => $mtch[2],
					'url'   => $mtch[1]
				];
			}
		}

		$cnt = preg_match_all('/@\[zrl=(.*?)\](.*?)\[\/zrl\]/ism',$body,$matches,PREG_SET_ORDER);
		if($cnt) {
			foreach($matches as $mtch) {
				// don't include plustags in the term
				$term = ((substr($mtch[2],-1,1) === '+') ? substr($mtch[2],0,-1) : $mtch[2]);
				$datarray['term'][] = [
					'uid'   => $this->importer['channel_id'],
					'ttype' => TERM_MENTION,
					'otype' => TERM_OBJ_POST,
					'term'  => $term,
					'url'   => $mtch[1]
				];
			}
		}

		$plink = service_plink($xchan,$guid);

		if(is_array($raw_location)) {
			if(array_key_exists('address',$raw_location))
				$datarray['location'] = unxmlify($raw_location['address']);
			if(array_key_exists('lat',$raw_location) && array_key_exists('lng',$raw_location))
				$datarray['coord'] = floatval(unxmlify($raw_location['lat'])) 
					. ' ' . floatval(unxmlify($raw_location['lng']));
		}


		$datarray['aid'] = $this->importer['channel_account_id'];
		$datarray['uid'] = $this->importer['channel_id'];

		$datarray['verb'] = ACTIVITY_POST;
		$datarray['mid'] = $datarray['parent_mid'] = $guid;

		if($updated) {
			$datarray['changed'] = $datarray['edited'] = $edited;
		}
		else {
			$datarray['changed'] = $datarray['created'] = $datarray['edited'] = datetime_convert('UTC','UTC',$created);
		}
		$datarray['item_private'] = $private;

		$datarray['plink'] = $plink;

		$datarray['author_xchan'] = $xchan['xchan_hash'];
		$datarray['owner_xchan']  = $xchan['xchan_hash'];

		$datarray['body'] = $body;

		$datarray['app']  = $app;

		$datarray['item_unseen'] = 1;
		$datarray['item_thread_top'] = 1;

		$tgroup = tgroup_check($this->importer['channel_id'],$datarray);

		if((! $this->importer['system']) && (! perm_is_allowed($this->importer['channel_id'],$xchan['xchan_hash'],'send_stream')) && (! $tgroup) && (! $found_tags)) {
			logger('diaspora_post: Ignoring this author.');
			return 202;
		}

		// Diaspora allows anybody to comment on public posts in theory
		// In fact the comment will be rejected unless it is correctly signed

		if($this->importer['system'] || $this->msg['public']) {
			$datarray['comment_policy'] = 'network: diaspora';
		}

		if(($contact) && (! post_is_importable($datarray,$contact))) {
			logger('diaspora_post: filtering this author.');
			return 202;
		}

		if($updated) {
			$result = item_store_update($datarray);
		}
		else {
			$result = item_store($datarray);
		}

		if($result['success']) {
			sync_an_item($this->importer['channel_id'],$result['item_id']);
		}

		return;

	}




	function reshare() {

		logger('diaspora_reshare: init: ' . print_r($this->xmlbase,true), LOGGER_DATA);

		$guid = notags($this->get_property('guid'));
		$diaspora_handle = notags($this->get_author());

		if($diaspora_handle != $this->msg['author']) {
			logger('Potential forgery. Message handle is not the same as envelope sender.');
			return 202;
		}

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$diaspora_handle);
		if(! $contact)
			return;

		$r = q("SELECT id FROM item WHERE uid = %d AND mid = '%s' LIMIT 1",
			intval($this->importer['channel_id']),
			dbesc($guid)
		);
		if($r) {
			logger('diaspora_reshare: message exists: ' . $guid);
			return;
		}

		$orig_author = notags($this->get_root_author());
		$orig_guid = notags($this->get_property('root_guid'));

		$source_url = 'https://' . substr($orig_author,strpos($orig_author,'@')+1) . '/fetch/post/' . $orig_guid ;
		$orig_url = 'https://'.substr($orig_author,strpos($orig_author,'@')+1).'/posts/'.$orig_guid;

		$source_xml = get_diaspora_reshare_xml($source_url);

		if($source_xml['status_message']) {
			$body = markdown_to_bb($this->get_body($source_xml['status_message']));

			$orig_author = $this->get_author($source_xml['status_message']);
			$orig_guid   = notags($this->get_property('guid',$source_xml['status_message']));

			// Check for one or more embedded photo objects
		
			if($source_xml['status_message']['photo']) {
				$photos = $source_xml['status_message']['photo'];
				if(array_key_exists('remote_photo_path',$photos)) {
					$photos = [ $photos ];
				}
				if($photos) {
					foreach($photos as $ph) {
						if($ph['remote_photo_path'] && $ph['remote_photo_name']) {
							$remote_photo_path = notags($this->get_property('remote_photo_path',$ph));
							$remote_photo_name = notags($this->get_property('remote_photo_name',$ph));
							$body = $body . "\n" . '[img]' . $remote_photo_path . $remote_photo_name . '[/img]' . "\n";
							logger('reshare: embedded picture link found: '.$body, LOGGER_DEBUG);
						}
					}
				}
			}

			$body = scale_external_images($body);
		}

		$maxlen = get_max_import_size();

		if($maxlen && mb_strlen($body) > $maxlen) {
			$body = mb_substr($body,0,$maxlen,'UTF-8');
			logger('message length exceeds max_import_size: truncated');
		}

		$person = find_diaspora_person_by_handle($orig_author);

		if($person) {
			$orig_author_name  = $person['xchan_name'];
			$orig_author_link  = $person['xchan_url'];
			$orig_author_photo = $person['xchan_photo_m'];
		}


		$created = $this->get_property('created_at');
		$private = (($this->get_property('public') === 'false') ? 1 : 0);

		$datarray = array();

		// Look for tags and linkify them
		$results = linkify_tags('', $body, $this->importer['channel_id'], true);

		$datarray['term'] = array();

		if($results) {
			foreach($results as $result) {
				$success = $result['success'];
				if($success['replaced']) {
					$datarray['term'][] = array(
						'uid'   => $this->importer['channel_id'],
						'ttype' => $success['termtype'],
						'otype' => TERM_OBJ_POST,
						'term'  => $success['term'],
						'url'   => $success['url']
					);
				}	
			}
		}

		$cnt = preg_match_all('/@\[url=(.*?)\](.*?)\[\/url\]/ism',$body,$matches,PREG_SET_ORDER);
		if($cnt) {
			foreach($matches as $mtch) {
				$datarray['term'][] = array(
					'uid'   => $this->importer['channel_id'],
					'ttype'  => TERM_MENTION,
					'otype' => TERM_OBJ_POST,
					'term'  => $mtch[2],
					'url'   => $mtch[1]
				);
			}
		}

		$cnt = preg_match_all('/@\[zrl=(.*?)\](.*?)\[\/zrl\]/ism',$body,$matches,PREG_SET_ORDER);
		if($cnt) {
			foreach($matches as $mtch) {
				// don't include plustags in the term
				$term = ((substr($mtch[2],-1,1) === '+') ? substr($mtch[2],0,-1) : $mtch[2]);
				$datarray['term'][] = array(
					'uid'   => $this->importer['channel_id'],
					'ttype'  => TERM_MENTION,
					'otype' => TERM_OBJ_POST,
					'term'  => $term,
					'url'   => $mtch[1]
				);
			}
		}

		$newbody = "[share author='" . urlencode($orig_author_name) 
			. "' profile='" . $orig_author_link 
			. "' avatar='"  . $orig_author_photo 
			. "' link='"    . $orig_url
			. "' posted='"  . datetime_convert('UTC','UTC',$this->get_property('created_at',$source_xml['status_message']))
			. "' message_id='" . $this->get_property('guid',$source_xml['status_message'])
	 		. "']" . $body . "[/share]";


		$plink = service_plink($contact,$guid);
		$datarray['aid'] = $this->importer['channel_account_id'];
		$datarray['uid'] = $this->importer['channel_id'];
		$datarray['mid'] = $datarray['parent_mid'] = $guid;
		$datarray['changed'] = $datarray['created'] = $datarray['edited'] = datetime_convert('UTC','UTC',$created);
		$datarray['item_private'] = $private;
		$datarray['plink'] = $plink;
		$datarray['owner_xchan'] = $contact['xchan_hash'];
		$datarray['author_xchan'] = $contact['xchan_hash'];

		$datarray['body'] = $newbody;
		$datarray['app']  = 'Diaspora';

		$tgroup = tgroup_check($this->importer['channel_id'],$datarray);

		if((! $this->importer['system']) && (! perm_is_allowed($this->importer['channel_id'],$contact['xchan_hash'],'send_stream')) && (! $tgroup)) {
			logger('diaspora_reshare: Ignoring this author.');
			return 202;
		}

		if(! post_is_importable($datarray,$contact)) {
			logger('diaspora_reshare: filtering this author.');
			return 202;
		}

		$result = item_store($datarray);

		if($result['success']) {
			sync_an_item($this->importer['channel_id'],$result['item_id']);
		}

		return;
	}


	function comment() {

		$guid = notags($this->get_property('guid'));
		$parent_guid = notags($this->get_property('parent_guid'));
		$diaspora_handle = notags($this->get_author());

		$created_at = ((array_key_exists('created_at',$this->xmlbase)) 
			? datetime_convert('UTC','UTC',$this->get_property('created_at')) : datetime_convert());

		$thr_parent = ((array_key_exists('thread_parent_guid',$this->xmlbase)) 
			? notags($this->get_property('thread_parent_guid')) : '');

		$text = $this->get_body();
		$author_signature = notags($this->get_property('author_signature'));
		$parent_author_signature = notags($this->get_property('parent_author_signature'));

		$xchan = find_diaspora_person_by_handle($diaspora_handle);

		if(! $xchan) {
			logger('Cannot resolve diaspora handle ' . $diaspora_handle);
			return;
		}

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$this->msg['author']);

		$pubcomment = get_pconfig($this->importer['channel_id'],'system','diaspora_public_comments',1);

		// by default comments on public posts are allowed from anybody on Diaspora. That is their policy.
		// Once this setting is set to something we'll track your preference and it will over-ride the default. 

		if(($pubcomment) && (! $contact))
			$contact = find_diaspora_person_by_handle($this->msg['author']);


		$r = q("SELECT * FROM item WHERE uid = %d AND mid = '%s' LIMIT 1",
			intval($this->importer['channel_id']),
			dbesc($parent_guid)
		);
		if(! $r) {
			logger('diaspora_comment: parent item not found: parent: ' . $parent_guid . ' item: ' . $guid);
			return;
		}

		$parent_item = $r[0];

		if(intval($parent_item['item_nocomment']) || $parent_item['comment_policy'] === 'none' 
			|| ($parent_item['comments_closed'] > NULL_DATE && $parent_item['comments_closed'] < datetime_convert())) {
			logger('diaspora_comment: comments disabled for post ' . $parent_item['mid']);
			return;
		}

		if(intval($parent_item['item_private']))
			$pubcomment = 0;	

		$r = q("SELECT * FROM item WHERE uid = %d AND mid = '%s' LIMIT 1",
			intval($this->importer['channel_id']),
			dbesc($guid)
		);
		if($r) {
			logger('diaspora_comment: our comment just got relayed back to us (or there was a guid collision) : ' . $guid);
			return;
		}

		/* How Diaspora performs comment signature checking:

	   - If an item has been sent by the comment author to the top-level post owner to relay on
	     to the rest of the contacts on the top-level post, the top-level post owner should check
	     the author_signature, then create a parent_author_signature before relaying the comment on
	   - If an item has been relayed on by the top-level post owner, the contacts who receive it
	     check only the parent_author_signature. Basically, they trust that the top-level post
	     owner has already verified the authenticity of anything he/she sends out
	   - In either case, the signature that get checked is the signature created by the person
	     who sent the pseudo-salmon
		*/

		$signed_data = $guid . ';' . $parent_guid . ';' . $text . ';' . $diaspora_handle;
		$key = $this->msg['key'];

		/* WARN: As a side effect of this, all of $this->xmlbase will now be unxmlified */

		$unxml = array_map('unxmlify',$this->xmlbase);

		if($parent_author_signature) {
			// If a parent_author_signature exists, then we've received the comment
			// relayed from the top-level post owner *or* it is legacy protocol. 

			$x = diaspora_verify_fields($unxml,$parent_author_signature,$key);
			if(! $x) {
				logger('diaspora_comment: top-level owner verification failed.');
				return;
			}
		}
		else {

			// the comment is being sent to the owner to relay 
			// *or* there is no parent signature because it is the new format

			if($this->importer['system'] && $this->msg['format'] === 'legacy') {
				// don't relay to the sys channel
				logger('diaspora_comment: relay to sys channel blocked.');
				return;
			}

			// Note: Diaspora verifies both signatures. We only verify the 
			// author_signature when relaying.
			// 
			// If there's no parent_author_signature, then we've received the comment
			// from the comment creator. In that case, the person is commenting on
			// our post, so he/she must be a contact of ours and his/her public key
			// should be in $this->msg['key']

			if(! $author_signature) {
				if($parent_item['owner_xchan'] !== $this->msg['author']) {
					logger('author signature required and not present');
					return;
				}
			}
			if($author_signature || $this->msg['type'] === 'legacy') {
				$x = diaspora_verify_fields($unxml,$author_signature,$key);
				if(! $x) {
					logger('diaspora_comment: comment author verification failed.');
					return;
				}
			}

			// No parent_author_signature, so let's assume we're relaying the post. Create one. 
			// in the V2 protocol we don't create a parent_author_signature as the salmon 
			// magic envelope we will send is signed and verified.

			// if(! defined('DIASPORA_V2'))	
				$unxml['parent_author_signature'] = diaspora_sign_fields($unxml,$this->importer['channel_prvkey']);

		}

		// Phew! Everything checks out. Now create an item.

		// Find the original comment author information.
		// We need this to make sure we display the comment author
		// information (name and avatar) correctly.

		if(strcasecmp($diaspora_handle,$this->msg['author']) == 0)
			$person = $contact;
		else
			$person = $xchan;

		if(! is_array($person)) {
			logger('diaspora_comment: unable to find author details');
			return;
		}

		$body = markdown_to_bb($text);

		$maxlen = get_max_import_size();

		if($maxlen && mb_strlen($body) > $maxlen) {
			$body = mb_substr($body,0,$maxlen,'UTF-8');
			logger('message length exceeds max_import_size: truncated');
		}

		$datarray = array();

		// Look for tags and linkify them
		$results = linkify_tags('', $body, $this->importer['channel_id'], true);

		$datarray['term'] = array();

		if($results) {
			foreach($results as $result) {
				$success = $result['success'];
				if($success['replaced']) {
					$datarray['term'][] = [
						'uid'   => $this->importer['channel_id'],
						'ttype' => $success['termtype'],
						'otype' => TERM_OBJ_POST,
						'term'  => $success['term'],
						'url'   => $success['url']
					];
				}
			}
		}

		$cnt = preg_match_all('/@\[url=(.*?)\](.*?)\[\/url\]/ism',$body,$matches,PREG_SET_ORDER);
		if($cnt) {
			foreach($matches as $mtch) {
				$datarray['term'][] = [
					'uid'   => $this->importer['channel_id'],
					'ttype' => TERM_MENTION,
					'otype' => TERM_OBJ_POST,
					'term'  => $mtch[2],
					'url'   => $mtch[1]
				];
			}
		}

		$cnt = preg_match_all('/@\[zrl=(.*?)\](.*?)\[\/zrl\]/ism',$body,$matches,PREG_SET_ORDER);
		if($cnt) {
			foreach($matches as $mtch) {
				// don't include plustags in the term
				$term = ((substr($mtch[2],-1,1) === '+') ? substr($mtch[2],0,-1) : $mtch[2]);
				$datarray['term'][] = [
					'uid'   => $this->importer['channel_id'],
					'ttype' => TERM_MENTION,
					'otype' => TERM_OBJ_POST,
					'term'  => $term,
					'url'   => $mtch[1]
				];
			}
		}

		$datarray['aid'] = $this->importer['channel_account_id'];
		$datarray['uid'] = $this->importer['channel_id'];
		$datarray['verb'] = ACTIVITY_POST;
		$datarray['mid'] = $guid;
		$datarray['parent_mid'] = $parent_item['mid'];
		$datarray['thr_parent'] = $thr_parent;

		// set the route to that of the parent so downstream hubs won't reject it.
		$datarray['route'] = $parent_item['route'];
		$datarray['changed'] = $datarray['created'] = $datarray['edited'] = $created_at;
		$datarray['item_private'] = $parent_item['item_private'];

		$datarray['owner_xchan'] = $parent_item['owner_xchan'];
		$datarray['author_xchan'] = $person['xchan_hash'];

		$datarray['body'] = $body;

		if(strstr($person['xchan_network'],'friendica'))
			$app = 'Friendica';
		elseif($person['xchan_network'] == 'diaspora')
			$app = 'Diaspora';
		else
			$app = '';

		$datarray['app'] = $app;
	

		// So basically if something arrives at the sys channel it's by definition public and we allow it.
		// If $pubcomment and the parent was public, we allow it.
		// In all other cases, honour the permissions for this Diaspora connection

		$tgroup = tgroup_check($this->importer['channel_id'],$datarray);


		// If it's a comment to one of our own posts, check if the commenter has permission to comment.
		// We should probably check send_stream permission if the stream owner isn't us,
		// but we did import the parent post so at least at that time we did allow it and
		// the check would nearly always be superfluous and redundant.

		if($parent_item['owner_xchan'] === $this->importer['channel_hash']) 
			$allowed = perm_is_allowed($this->importer['channel_id'],$xchan['xchan_hash'],'post_comments');
		else
			$allowed = true;

		if((! $this->importer['system']) && (! $pubcomment) && (! $allowed) && (! $tgroup)) {
			logger('diaspora_comment: Ignoring this author.');
			return 202;
		}

		set_iconfig($datarray,'diaspora','fields',$unxml,true);

		$result = item_store($datarray);

		if($result && $result['success'])
			$message_id = $result['item_id'];

		if($parent_item['owner_xchan'] === $this->importer['channel_hash']) {
			// We are the owner of this conversation, so send all received comments back downstream
			Zotlabs\Daemon\Master::Summon(array('Notifier','comment-import',$message_id));
		}

		if($result['success']) {
			$r = q("select * from item where id = %d limit 1",
				intval($result['item_id'])
			);
			if($r) {
				send_status_notifications($result['item_id'],$r[0]);
				sync_an_item($this->importer['channel_id'],$result['item_id']);
			}
		}

		return;
	}




	function conversation() {

		$guid = notags($this->get_property('guid'));
		$subject = notags($this->get_property('subject'));
		$diaspora_handle = notags($this->get_author());
		$participant_handles = notags($this->get_participants());
		$created_at = datetime_convert('UTC','UTC',notags($this->get_property('created_at')));

		$parent_uri = $guid;
 
		$messages = $this->xmlbase['message'];

		if(! count($messages)) {
			logger('diaspora_conversation: empty conversation');
			return;
		}

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$this->msg['author']);
		if(! $contact) {
			logger('diaspora_conversation: cannot find contact: ' . $this->msg['author']);
			return;
		}


		if(! perm_is_allowed($this->importer['channel_id'],$contact['xchan_hash'],'post_mail')) {
			logger('diaspora_conversation: Ignoring this author.');
			return 202;
		}

		$conversation = null;

		$c = q("select * from conv where uid = %d and guid = '%s' limit 1",
			intval($this->importer['channel_id']),
			dbesc($guid)
		);
		if(count($c))
			$conversation = $c[0];
		else {
			if($subject)
				$nsubject = str_rot47(base64url_encode($subject));

			$r = q("insert into conv (uid, guid, creator, created, updated, subject, recips) 
				values( %d, '%s', '%s', '%s', '%s', '%s', '%s') ",
				intval($this->importer['channel_id']),
				dbesc($guid),
				dbesc($diaspora_handle),
				dbesc(datetime_convert('UTC','UTC',$created_at)),
				dbesc(datetime_convert()),
				dbesc($nsubject),
				dbesc($participant_handles)
			);
			if($r)
				$c = q("select * from conv where uid = %d and guid = '%s' limit 1",
				intval($this->importer['channel_id']),
				dbesc($guid)
			);
			if($c)
				$conversation = $c[0];
		}
		if(! $conversation) {
			logger('diaspora_conversation: unable to create conversation.');
			return;
		}

		$conversation['subject'] = base64url_decode(str_rot47($conversation['subject']));

		/* @fixme use signed field order for signature verification */

		foreach($messages as $mesg) {

			$reply = 0;

			$msg_guid = notags(unxmlify($mesg['guid']));
			$msg_parent_guid = notags(unxmlify($mesg['parent_guid']));
			$msg_parent_author_signature = notags(unxmlify($mesg['parent_author_signature']));
			$msg_author_signature = notags(unxmlify($mesg['author_signature']));
			$msg_text = unxmlify($mesg['text']);
			$msg_created_at = datetime_convert('UTC','UTC',notags(unxmlify($mesg['created_at'])));
			$msg_diaspora_handle = notags($this->get_author($mesg));
			$msg_conversation_guid = notags(unxmlify($mesg['conversation_guid']));
			if($msg_conversation_guid != $guid) {
				logger('diaspora_conversation: message conversation guid does not belong to the current conversation. ' . $this->xmlbase);
				continue;
			}

			$body = markdown_to_bb($msg_text);

			$maxlen = get_max_import_size();

			if($maxlen && mb_strlen($body) > $maxlen) {
				$body = mb_substr($body,0,$maxlen,'UTF-8');
				logger('message length exceeds max_import_size: truncated');
			}


			if($msg_author_signature || $this->msg['type'] === 'legacy') {
				$author_signed_data = $msg_guid . ';' . $msg_parent_guid . ';' . $msg_text . ';' . unxmlify($mesg['created_at']) . ';' . $msg_diaspora_handle . ';' . $msg_conversation_guid;

				$author_signature = base64_decode($msg_author_signature);

				if(strcasecmp($msg_diaspora_handle,$this->msg['author']) == 0) {
					$person = $contact;
					$key = $this->msg['key'];
				}
				else {
					$person = find_diaspora_person_by_handle($msg_diaspora_handle);	

					if(is_array($person) && x($person,'xchan_pubkey'))
						$key = $person['xchan_pubkey'];
					else {
						logger('diaspora_conversation: unable to find author details');
						continue;
					}
				}

				if(! rsa_verify($author_signed_data,$author_signature,$key,'sha256')) {
					logger('diaspora_conversation: verification failed.');
					continue;
				}
			}
			else {
				if(strcasecmp($msg_diaspora_handle,$this->msg['author']) == 0) {
					$person = $contact;
				}
			}

			if($msg_parent_author_signature && $this->msg['type'] === 'legacy') {
				$owner_signed_data = $msg_guid . ';' . $msg_parent_guid . ';' . $msg_text . ';' . unxmlify($mesg['created_at']) . ';' . $msg_diaspora_handle . ';' . $msg_conversation_guid;

				$parent_author_signature = base64_decode($msg_parent_author_signature);

				$key = $this->msg['key'];

				if(! rsa_verify($owner_signed_data,$parent_author_signature,$key,'sha256')) {
					logger('diaspora_conversation: owner verification failed.');
					continue;
				}
			}

			$stored_parent_mid = (($msg_parent_guid == $msg_conversation_guid) ? $msg_guid : $msg_parent_guid);

			$r = q("select id from mail where mid = '%s' limit 1",
				dbesc($message_id)
			);
			if(count($r)) {
				logger('diaspora_conversation: duplicate message already delivered.', LOGGER_DEBUG);
				continue;
			}

			if($subject)
				$subject = str_rot47(base64url_encode($subject));
			if($body)
				$body  = str_rot47(base64url_encode($body));

			$sig = ''; // placeholder

			// @fixme - use mail_store or mail_store_lowlevel

			$x = mail_store( [
				'account_id' => intval($this->importer['channel_account_id']),
				'channel_id' => intval($this->importer['channel_id']),
				'convid' => intval($conversation['id']),
				'conv_guid' => $conversation['guid'],
				'from_xchan' => $person['xchan_hash'],
				'to_xchan' => $this->importer['channel_hash'],
				'title' => $subject,
				'body' => $body,
				'sig' => $sig,
				'mail_obscured' => 1,
				'mid' => $msg_guid,
				'parent_mid' => $stored_parent_mid,
				'created' => $msg_created_at
			]);

			q("update conv set updated = '%s' where id = %d",
				dbesc(datetime_convert()),
				intval($conversation['id'])
			);

			$z = q("select * from mail where mid = '%s' and channel_id = %d limit 1",
				dbesc($msg_guid),
				intval($this->importer['channel_id'])
			);

			\Zotlabs\Lib\Enotify::submit(array(
				'from_xchan' => $person['xchan_hash'],
				'to_xchan' => $this->importer['channel_hash'],
				'type' => NOTIFY_MAIL,
				'item' => $z[0],
				'verb' => ACTIVITY_POST,
				'otype' => 'mail'
			));
		}

		return;
	}

	
	function message() {

		$msg_guid = notags(unxmlify($this->xmlbase['guid']));
		$msg_parent_guid = notags(unxmlify($this->xmlbase['parent_guid']));
		$msg_parent_author_signature = notags(unxmlify($this->xmlbase['parent_author_signature']));
		$msg_author_signature = notags(unxmlify($this->xmlbase['author_signature']));
		$msg_text = unxmlify($this->xmlbase['text']);
		$msg_created_at = datetime_convert('UTC','UTC',notags(unxmlify($this->xmlbase['created_at'])));
		$msg_diaspora_handle = notags($this->get_author());
		$msg_conversation_guid = notags(unxmlify($this->xmlbase['conversation_guid']));

		$parent_uri = $msg_parent_guid;
 
		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$msg_diaspora_handle);
		if(! $contact) {
			logger('diaspora_message: cannot find contact: ' . $msg_diaspora_handle);
			return;
		}

		if(! perm_is_allowed($this->importer['channel_id'],$contact['xchan_hash'],'post_mail')) {
			logger('Ignoring this author.');
			return 202;
		}

		$conversation = null;

		$c = q("select * from conv where uid = %d and guid = '%s' limit 1",
			intval($this->importer['channel_id']),
			dbesc($msg_conversation_guid)
		);
		if($c)
			$conversation = $c[0];
		else {
			logger('diaspora_message: conversation not available.');
			return;
		}

		$reply = 0;

		$subject = $conversation['subject']; //this is already encoded
		$body = markdown_to_bb($msg_text);


		$maxlen = get_max_import_size();

		if($maxlen && mb_strlen($body) > $maxlen) {
			$body = mb_substr($body,0,$maxlen,'UTF-8');
			logger('message length exceeds max_import_size: truncated');
		}


		$x = q("select mid from mail where conv_guid = '%s' and channel_id = %d order by id asc limit 1",
			dbesc($conversation['guid']),
			intval($this->importer['channel_id'])
		);
		if($x)
			$parent_ptr = $x[0]['mid'];

		if($msg_author_signature || $this->msg['type'] === 'legacy') {
			$author_signed_data = $msg_guid . ';' . $msg_parent_guid . ';' . $msg_text . ';' . unxmlify($this->xmlbase['created_at']) . ';' . $msg_diaspora_handle . ';' . $msg_conversation_guid;

			$author_signature = base64_decode($msg_author_signature);

			$person = find_diaspora_person_by_handle($msg_diaspora_handle);	
			if(is_array($person) && x($person,'xchan_pubkey'))
				$key = $person['xchan_pubkey'];
			else {
				logger('diaspora_message: unable to find author details');
				return;
			}

			if(! rsa_verify($author_signed_data,$author_signature,$key,'sha256')) {
				logger('diaspora_message: verification failed.');
				return;
			}
		}
		else {
			$person = find_diaspora_person_by_handle($msg_diaspora_handle);	
		}

		$r = q("select id from mail where mid = '%s' and channel_id = %d limit 1",
			dbesc($msg_guid),
			intval($this->importer['channel_id'])
		);
		if($r) {
			logger('diaspora_message: duplicate message already delivered.', LOGGER_DEBUG);
			return;
		}

		$key = get_config('system','pubkey');
		// $subject is a copy of the already obscured subject from the conversation structure
		if($body)
			$body  = str_rot47(base64url_encode($body));

		$sig = '';

		$x = mail_store( 
			[
				'account_id'    => intval($this->importer['channel_account_id']),
				'channel_id'    => intval($this->importer['channel_id']),
				'convid'        => intval($conversation['id']),
				'conv_guid'     => $conversation['guid'],
				'from_xchan'    => $person['xchan_hash'],
				'to_xchan'      => $this->importer['xchan_hash'],
				'title'         => $subject,
				'body'          => $body,
				'sig'           => $sig,
				'mail_obscured' => 1,
				'mid'           => $msg_guid,
				'parent_mid'    => $parent_ptr,
				'created'       => $msg_created_at,
				'mail_isreply'  => 1
			]
		);

		q("update conv set updated = '%s' where id = %d",
			dbesc(datetime_convert()),
			intval($conversation['id'])
		);

		$z = q("select * from mail where mid = '%s' and channel_id = %d limit 1",
			dbesc($msg_guid),
			intval($this->importer['channel_id'])
		);

		\Zotlabs\Lib\Enotify::submit(array(
			'from_xchan' => $person['xchan_hash'],
			'to_xchan' => $this->importer['channel_hash'],
			'type' => NOTIFY_MAIL,
			'item' => $z[0],
			'verb' => ACTIVITY_POST,
			'otype' => 'mail'
		));

		return;
	}


	function photo() {

		// Probably not used any more

		logger('diaspora_photo: init',LOGGER_DEBUG);

		$remote_photo_path = notags(unxmlify($this->xmlbase['remote_photo_path']));

		$remote_photo_name = notags(unxmlify($this->xmlbase['remote_photo_name']));

		$status_message_guid = notags(unxmlify($this->xmlbase['status_message_guid']));

		$guid = notags(unxmlify($this->xmlbase['guid']));

		$diaspora_handle = notags($this->get_author());

		$public = notags(unxmlify($this->xmlbase['public']));

		$created_at = notags(unxmlify($this->xmlbase['created_at']));

		logger('diaspora_photo: status_message_guid: ' . $status_message_guid, LOGGER_DEBUG);

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$this->msg['author']);
		if(! $contact) {
			logger('diaspora_photo: contact record not found: ' . $this->msg['author'] . ' handle: ' . $diaspora_handle);
			return;
		}

		if((! $this->importer['system']) && (! perm_is_allowed($this->importer['channel_id'],$contact['xchan_hash'],'send_stream'))) {
			logger('diaspora_photo: Ignoring this author.');
			return 202;
		}

		$r = q("SELECT * FROM item WHERE uid = %d AND mid = '%s' LIMIT 1",
			intval($this->importer['channel_id']),
			dbesc($status_message_guid)
		);
		if(! $r) {
			logger('diaspora_photo: attempt = ' . $attempt . '; status message not found: ' . $status_message_guid . ' for photo: ' . $guid);
			return;
		}

	//	$parent_item = $r[0];

	//	$link_text = '[img]' . $remote_photo_path . $remote_photo_name . '[/img]' . "\n";

	//	$link_text = scale_external_images($link_text, true,
	//									   array($remote_photo_name, 'scaled_full_' . $remote_photo_name));

	//	if(strpos($parent_item['body'],$link_text) === false) {
	//		$r = q("update item set body = '%s', visible = 1 where id = %d and uid = %d",
	//			dbesc($link_text . $parent_item['body']),
	//			intval($parent_item['id']),
	//			intval($parent_item['uid'])
	//		);
	//	}

		return;
	}




	function like() {

		$guid = notags($this->get_property('guid'));
		$parent_guid = notags($this->get_property('parent_guid'));
		$diaspora_handle = notags($this->get_author());
		$target_type = notags($this->get_ptype());
		$positive = notags($this->get_property('positive'));
		$author_signature = notags($this->get_property('author_signature'));

		$parent_author_signature = $this->get_property('parent_author_signature');

		// likes on comments not supported here and likes on photos not supported by Diaspora


		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$this->msg['author']);
		if(! $contact) {
			logger('diaspora_like: cannot find contact: ' . $this->msg['author'] . ' for channel ' . $this->importer['channel_name']);
			return;
		}


		if((! $this->importer['system']) && (! perm_is_allowed($this->importer['channel_id'],$contact['xchan_hash'],'post_comments'))) {
			logger('diaspora_like: Ignoring this author.');
			return 202;
		}

		$r = q("SELECT * FROM item WHERE uid = %d AND mid = '%s' LIMIT 1",
			intval($this->importer['channel_id']),
			dbesc($parent_guid)
		);
		if(! $r) {
			logger('diaspora_like: parent item not found: ' . $guid);
			return;
		}

		xchan_query($r);
		$parent_item = $r[0];

		if(intval($parent_item['item_nocomment']) || $parent_item['comment_policy'] === 'none' 
			|| ($parent_item['comments_closed'] > NULL_DATE && $parent_item['comments_closed'] < datetime_convert())) {
			logger('diaspora_like: comments disabled for post ' . $parent_item['mid']);
			return;
		}

		$r = q("SELECT * FROM item WHERE uid = %d AND mid = '%s' LIMIT 1",
			intval($this->importer['channel_id']),
			dbesc($guid)
		);
		if($r) {
			if($positive === 'true') {
				logger('diaspora_like: duplicate like: ' . $guid);
				return;
			}

			// Note: I don't think "Like" objects with positive = "false" are ever actually used
			// It looks like "RelayableRetractions" are used for "unlike" instead

			if($positive === 'false') {
				logger('diaspora_like: received a like with positive set to "false"...ignoring');
				// perhaps call drop_item()
				// FIXME--actually don't unless it turns out that Diaspora does indeed send out "false" likes
				//  send notification via proc_run()
				return;
			}
		}

		$i = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($parent_item['author_xchan'])
		);
		if($i)
			$item_author = $i[0];

		// Note: I don't think "Like" objects with positive = "false" are ever actually used
		// It looks like "RelayableRetractions" are used for "unlike" instead

		if($positive === 'true')
			$activity = ACTIVITY_LIKE;
		else
			$activity = ACTIVITY_DISLIKE;

		// old style signature
		$signed_data = $positive . ';' . $guid . ';' . $target_type . ';' . $parent_guid . ';' . $diaspora_handle;

		$key = $this->msg['key'];

		if($parent_author_signature) {
			// If a parent_author_signature exists, then we've received the like
			// relayed from the top-level post owner.

			$x = diaspora_verify_fields($this->xmlbase,$parent_author_signature,$key);
			if(! $x) {
				logger('diaspora_like: top-level owner verification failed.');
				return;
			}
		}
		else {

			// If there's no parent_author_signature, then we've received the like
			// from the like creator. In that case, the person is "like"ing
			// our post, so he/she must be a contact of ours and his/her public key
			// should be in $this->msg['key']

			$x = diaspora_verify_fields($this->xmlbase,$author_signature,$key);
			if(! $x) {
				logger('diaspora_like: author verification failed.');
				return;
			}

			if(defined('DIASPORA_V2'))
				$this->xmlbase['parent_author_signature'] = diaspora_sign_fields($this->xmlbase,$this->importer['channel_prvkey']);
		}
	
		logger('diaspora_like: signature check complete.',LOGGER_DEBUG);

		// Phew! Everything checks out. Now create an item.

		// Find the original comment author information.
		// We need this to make sure we display the comment author
		// information (name and avatar) correctly.
		if(strcasecmp($diaspora_handle,$this->msg['author']) == 0)
			$person = $contact;
		else {
			$person = find_diaspora_person_by_handle($diaspora_handle);

			if(! is_array($person)) {
				logger('diaspora_like: unable to find author details');
				return;
			}
		}

		$uri = $diaspora_handle . ':' . $guid;


		$post_type = (($parent_item['resource_type'] === 'photo') ? t('photo') : t('status'));

		$links = array(array('rel' => 'alternate','type' => 'text/html', 'href' => $parent_item['plink']));
		$objtype = (($parent_item['resource_type'] === 'photo') ? ACTIVITY_OBJ_PHOTO : ACTIVITY_OBJ_NOTE );

		$body = $parent_item['body'];


		$object = json_encode(array(
			'type'    => $post_type,
			'id'	  => $parent_item['mid'],
			'parent'  => (($parent_item['thr_parent']) ? $parent_item['thr_parent'] : $parent_item['parent_mid']),
			'link'	  => $links,
			'title'   => $parent_item['title'],
			'content' => $parent_item['body'],
			'created' => $parent_item['created'],
			'edited'  => $parent_item['edited'],
			'author'  => array(
				'name'     => $item_author['xchan_name'],
				'address'  => $item_author['xchan_addr'],
				'guid'     => $item_author['xchan_guid'],
				'guid_sig' => $item_author['xchan_guid_sig'],
				'link'     => array(
					array('rel' => 'alternate', 'type' => 'text/html', 'href' => $item_author['xchan_url']),
					array('rel' => 'photo', 'type' => $item_author['xchan_photo_mimetype'], 'href' => $item_author['xchan_photo_m'])),
				),
			));


		$bodyverb = t('%1$s likes %2$s\'s %3$s');

		$arr = array();

		$arr['uid'] = $this->importer['channel_id'];
		$arr['aid'] = $this->importer['channel_account_id'];
		$arr['mid'] = $guid;
		$arr['parent_mid'] = $parent_item['mid'];
		$arr['owner_xchan'] = $parent_item['owner_xchan'];
		$arr['author_xchan'] = $person['xchan_hash'];

		$ulink = '[url=' . $item_author['xchan_url'] . ']' . $item_author['xchan_name'] . '[/url]';
		$alink = '[url=' . $parent_item['author']['xchan_url'] . ']' . $parent_item['author']['xchan_name'] . '[/url]';
		$plink = '[url='. z_root() .'/display/'.$guid.']'.$post_type.'[/url]';
		$arr['body'] =  sprintf( $bodyverb, $ulink, $alink, $plink );

		$arr['app']  = 'Diaspora';

		// set the route to that of the parent so downstream hubs won't reject it.
		$arr['route'] = $parent_item['route'];

		$arr['item_private'] = $parent_item['item_private'];
		$arr['verb'] = $activity;
		$arr['obj_type'] = $objtype;
		$arr['obj'] = $object;

		set_iconfig($arr,'diaspora','fields',array_map('unxmlify',$this->xmlbase),true);

		$result = item_store($arr);

		if($result['success']) {
			// if the message isn't already being relayed, notify others
			// the existence of parent_author_signature means the parent_author or owner
			// is already relaying. The parent_item['origin'] indicates the message was created on our system

			if(intval($parent_item['item_origin']) && (! $parent_author_signature))
				Zotlabs\Daemon\Master::Summon(array('Notifier','comment-import',$result['item_id']));
			sync_an_item($this->importer['channel_id'],$result['item_id']);
		}

		return;
	}

	function retraction() {


		$guid = notags($this->get_target_guid());
		$diaspora_handle = notags($this->get_author());
		$type = notags($this->get_type());

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$diaspora_handle);
		if(! $contact)
			return;

		if($type === 'Person' || $type === 'Contact') {
			contact_remove($this->importer['channel_id'],$contact['abook_id']);
		}
		elseif(($type === 'Post') || ($type === 'StatusMessage') || ($type === 'Comment') || ($type === 'Like')) {
			$r = q("select * from item where mid = '%s' and uid = %d limit 1",
				dbesc($guid),
				intval($this->importer['channel_id'])
			);
			if($r) {
				if(link_compare($r[0]['author_xchan'],$contact['xchan_hash'])
					|| link_compare($r[0]['owner_xchan'],$contact['xchan_hash'])) {
					drop_item($r[0]['id'],false);
				}
				// @FIXME - ensure that relay is performed if this was an upstream
				// Could probably check if we're the owner and it is a like or comment
				// This may or may not be handled by drop_item
			}
		}

		return 202;
	}

	function signed_retraction() {
	
		// obsolete - see https://github.com/SuperTux88/diaspora_federation/issues/27


		$guid = notags($this->get_target_guid());
		$diaspora_handle = notags($this->get_author());
		$type = notags($this->get_type());
		$sig = notags(unxmlify($this->xmlbase['target_author_signature']));

		$parent_author_signature = (($this->xmlbase['parent_author_signature']) ? notags(unxmlify($this->xmlbase['parent_author_signature'])) : '');

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$diaspora_handle);
		if(! $contact) {
			logger('diaspora_signed_retraction: no contact ' . $diaspora_handle . ' for ' . $this->importer['channel_id']);
			return;
		}


		$signed_data = $guid . ';' . $type ;
		$key = $this->msg['key'];

		/* How Diaspora performs relayable_retraction signature checking:

	   - If an item has been sent by the item author to the top-level post owner to relay on
	     to the rest of the contacts on the top-level post, the top-level post owner checks
	     the author_signature, then creates a parent_author_signature before relaying the item on
	   - If an item has been relayed on by the top-level post owner, the contacts who receive it
	     check only the parent_author_signature. Basically, they trust that the top-level post
	     owner has already verified the authenticity of anything he/she sends out
	   - In either case, the signature that get checked is the signature created by the person
	     who sent the salmon
		*/

		if($parent_author_signature) {

			$parent_author_signature = base64_decode($parent_author_signature);

			if(! rsa_verify($signed_data,$parent_author_signature,$key,'sha256')) {
				logger('diaspora_signed_retraction: top-level post owner verification failed');
				return;
			}
		}
		else {

			$sig_decode = base64_decode($sig);

			if(! rsa_verify($signed_data,$sig_decode,$key,'sha256')) {
				logger('diaspora_signed_retraction: retraction owner verification failed.' . print_r($this->msg,true));
				return;
			}
		}

		if($type === 'StatusMessage' || $type === 'Comment' || $type === 'Like') {
			$r = q("select * from item where mid = '%s' and uid = %d limit 1",
				dbesc($guid),
				intval($this->importer['channel_id'])
			);
			if($r) {
				if($r[0]['author_xchan'] == $contact['xchan_hash']) {

					drop_item($r[0]['id'],false, DROPITEM_PHASE1);

					// Now check if the retraction needs to be relayed by us
					//
					// The first item in the item table with the parent id is the parent. However, MySQL doesn't always
					// return the items ordered by item.id, in which case the wrong item is chosen as the parent.
					// The only item with parent and id as the parent id is the parent item.
					$p = q("select item_flags from item where parent = %d and id = %d limit 1",
						$r[0]['parent'],
						$r[0]['parent']
					);
					if($p) {
						if(intval($p[0]['item_origin']) && (! $parent_author_signature)) {

							// the existence of parent_author_signature would have meant the parent_author or owner
							// is already relaying.

							logger('diaspora_signed_retraction: relaying relayable_retraction');
							Zotlabs\Daemon\Master::Summon(array('Notifier','drop',$r[0]['id']));
						}
					}
				}
			}
		}
		else
			logger('diaspora_signed_retraction: unknown type: ' . $type);

		return 202;

	}

	function profile() {

		$diaspora_handle = notags($this->get_author());

		logger('xml: ' . print_r($this->xmlbase,true), LOGGER_DEBUG);

		if($diaspora_handle != $this->msg['author']) {
			logger('diaspora_post: Potential forgery. Message handle is not the same as envelope sender.');
			return 202;
		}

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$diaspora_handle);
		if(! $contact)
			return;

		if($contact['abook_blocked']) {
			logger('diaspora_profile: Ignoring this author.');
			return 202;
		}

		$name = unxmlify($this->xmlbase['first_name'] . (($this->xmlbase['last_name']) ? ' ' . $this->xmlbase['last_name'] : ''));
		$image_url = unxmlify($this->xmlbase['image_url']);
		$birthday = unxmlify($this->xmlbase['birthday']);


		$handle_parts = explode("@", $diaspora_handle);
		if($name === '') {
			$name = $handle_parts[0];
		}
		 
		if( preg_match("|^https?://|", $image_url) === 0) {
			$image_url = "http://" . $handle_parts[1] . $image_url;
		}

		require_once('include/photo/photo_driver.php');

		$images = import_xchan_photo($image_url,$contact['xchan_hash']);
	
		// Generic birthday. We don't know the timezone. The year is irrelevant. 

		$birthday = str_replace('1000','1901',$birthday);

		$birthday = datetime_convert('UTC','UTC',$birthday,'Y-m-d');

		// this is to prevent multiple birthday notifications in a single year
		// if we already have a stored birthday and the 'm-d' part hasn't changed, preserve the entry, which will preserve the notify year

		if(substr($birthday,5) === substr($contact['bd'],5))
			$birthday = $contact['bd'];

		$r = q("update xchan set xchan_name = '%s', xchan_name_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s' ",
			dbesc($name),
			dbesc(datetime_convert()),
			dbesc($images[0]),
			dbesc($images[1]),
			dbesc($images[2]),
			dbesc($images[3]),
			dbesc(datetime_convert()),
			intval($contact['xchan_hash'])
		); 

		return;

	}

	function participation() {

		$diaspora_handle = notags($this->get_author());
		$type = notags($this->get_ptype());

		// not currently handled

		logger('participation: ' . print_r($this->xmlbase,true), LOGGER_DATA);


	}

	function poll_participation() {

		$diaspora_handle = notags($this->get_author());

		// not currently handled

		logger('poll_participation: ' . print_r($this->xmlbase,true), LOGGER_DATA);

	}

	function account_deletion() {

		$diaspora_handle = notags($this->get_author());

		// not currently handled

		logger('account_deletion: ' . print_r($this->xmlbase,true), LOGGER_DATA);


	}


	function account_migration() {

		$diaspora_handle = notags($this->get_author());

		$profile = $this->xmlbase['profile'];
		if(! $profile) {
			return;	
		}

		logger('xml: ' . print_r($this->xmlbase,true), LOGGER_DEBUG);

		if($this->msg['format'] === 'legacy') {
			return;
		}

		if($diaspora_handle != $this->msg['author']) {
			logger('diaspora_post: Potential forgery. Message handle is not the same as envelope sender.');
			return 202;
		}

		$new_handle = notags($this->get_author($profile));

		$signed_text = 'AccountMigration:' . $diaspora_handle . ':' . $new_handle;

		$signature = $this->get_property('signature');

		if(! $signature) {
			logger('signature not found.');
			return 202;
		}
		$signature = str_replace(array(" ","\t","\r","\n"),array("","","",""),$signature);

		$contact = diaspora_get_contact_by_handle($this->importer['channel_id'],$diaspora_handle);
		if(! $contact) {
			logger('connection not found.');
			return 202;
		}

		$new_contact = find_diaspora_person_by_handle($new_handle);

		if(! $new_contact) {
			logger('new handle not found.');
			return 202;
		}

		$sig_decode = base64_decode($signature);

		if(! rsa_verify($signed_text,$sig_decode,$new_contact['xchan_pubkey'],'sha256')) {
			logger('message verification failed.');
			return 202;
		}


		$name = unxmlify($this->get_property('first_name',$profile) . (($this->get_property('last_name',$profile)) ? ' ' . $this->get_property('last_name',$profile) : ''));
		$image_url = unxmlify($this->get_property('image_url',$profile));
		$birthday = unxmlify($this->get_property('birthday',$profile));

		$handle_parts = explode("@", $new_handle);
		if($name === '') {
			$name = $handle_parts[0];
		}
		 
		if( preg_match("|^https?://|", $image_url) === 0) {
			$image_url = "http://" . $handle_parts[1] . $image_url;
		}

		require_once('include/photo/photo_driver.php');

		$images = import_xchan_photo($image_url,$new_contact['xchan_hash']);
	
		// Generic birthday. We don't know the timezone. The year is irrelevant. 

		$birthday = str_replace('1000','1901',$birthday);

		$birthday = datetime_convert('UTC','UTC',$birthday,'Y-m-d');

		// this is to prevent multiple birthday notifications in a single year
		// if we already have a stored birthday and the 'm-d' part hasn't changed, preserve the entry, which will preserve the notify year
		// currently not implemented

		if(substr($birthday,5) === substr($new_contact['bd'],5))
			$birthday = $new_contact['bd'];

		$r = q("update xchan set xchan_name = '%s', xchan_name_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s' ",
			dbesc($name),
			dbesc(datetime_convert()),
			dbesc($images[0]),
			dbesc($images[1]),
			dbesc($images[2]),
			dbesc($images[3]),
			dbesc(datetime_convert()),
			intval($new_contact['xchan_hash'])
		); 

		$r = q("update abook set abook_xchan = '%s' where abook_xchan = '%s' and abook_channel = %d",
			dbesc($new_contact['xchan_hash']),
			dbesc($contact['xchan_hash']),
			intval($this->importer['channel_id'])
		);

		$r = q("update group_member set xchan = '%s' where xchan = '%s' and uid = %d",
			dbesc($new_contact['xchan_hash']),
			dbesc($contact['xchan_hash']),
			intval($this->importer['channel_id'])
		);		

		// @todo also update private conversational items with the old xchan_hash in an allow_cid or deny_cid acl
		// Not much point updating other DB objects which wouldn't have been visible without remote authentication
		// to begin with.
 
		return;

	}



	function get_author($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('diaspora_handle',$xml))
			return unxmlify($xml['diaspora_handle']);
		elseif(array_key_exists('sender_handle',$xml))
			return unxmlify($xml['sender_handle']);
		elseif(array_key_exists('author',$xml))
			return unxmlify($xml['author']);
		else
			return '';
	}

	function get_root_author($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('root_diaspora_id',$xml))
			return unxmlify($xml['root_diaspora_id']);
		elseif(array_key_exists('root_author',$xml))
			return unxmlify($xml['root_author']);
		else
			return '';
	}


	function get_participants($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('participant_handles',$xml))
			return unxmlify($xml['participant_handles']);
		elseif(array_key_exists('participants',$xml))
			return unxmlify($xml['participants']);
		else
			return '';
	}

	function get_ptype($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('target_type',$xml))
			return unxmlify($xml['target_type']);
		elseif(array_key_exists('parent_type',$xml))
			return unxmlify($xml['parent_type']);
		else
			return '';
	}

	function get_type($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('target_type',$xml))
			return unxmlify($xml['target_type']);
		elseif(array_key_exists('type',$xml))
			return unxmlify($xml['type']);
		else
			return '';
	}


	function get_target_guid($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('post_guid',$xml))
			return unxmlify($xml['post_guid']);
		elseif(array_key_exists('target_guid',$xml))
			return unxmlify($xml['target_guid']);
		elseif(array_key_exists('guid',$xml))
			return unxmlify($xml['guid']);
		else
			return '';
	}


	function get_recipient($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('recipient_handle',$xml))
			return unxmlify($xml['recipient_handle']);
		elseif(array_key_exists('recipient',$xml))
			return unxmlify($xml['recipient']);
		else
			return '';
	}

	function get_body($xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists('raw_message',$xml))
			return unxmlify($xml['raw_message']);
		elseif(array_key_exists('text',$xml))
			return unxmlify($xml['text']);
		else
			return '';
	}

	function get_property($property,$xml = []) {
		if(! $xml)
			$xml = $this->xmlbase;

		if(array_key_exists($property,$xml)) {
			if(is_array($xml[$property])) {
				return $xml[$property];
			}
			else {
				return unxmlify($xml[$property]);
			}
		}
		return '';
	}


}