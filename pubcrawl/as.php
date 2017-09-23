<?php


function asencode_object($x) {

	if((substr(trim($x),0,1)) === '{' ) {
		$x = json_decode($x,true);
	}
	if($x['type'] === ACTIVITY_OBJ_PERSON) {
		return asfetch_person($x); 
	}
	if($x['type'] === ACTIVITY_OBJ_PROFILE) {
		return asfetch_profile($x); 
	}

	if($x['type'] === ACTIVITY_OBJ_NOTE) {
		return asfetch_item($x); 
	}

	if($x['type'] === ACTIVITY_OBJ_THING) {
		return asfetch_thing($x); 
	}


}	

function asfetch_person($x) {
	return asfetch_profile($x);
}

function asfetch_profile($x) {
	$r = q("select * from xchan where xchan_url like '%s' limit 1",
		dbesc($x['id'] . '/%')
	);
	if(! $r) {
		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($x['id'])
		);

	} 
	if(! $r)
		return [];

	return asencode_person($r[0]);

}

function asfetch_thing($x) {

	$r = q("select * from obj where obj_type = %d and obj_obj = '%s' limit 1",
		intval(TERM_OBJ_THING),
		dbesc($x['id'])
	);

	if(! $r)
		return [];

	$x = [
		'type' => 'Object',
		'id'   => z_root() . '/thing/' . $r[0]['obj_obj'],
		'name' => $r[0]['obj_term']
	];

	if($r[0]['obj_image'])
		$x['image'] = $r[0]['obj_image'];

	return $x;

}

function asfetch_item($x) {

	$r = q("select * from item where mid = '%s' limit 1",
		dbesc($x['id'])
	);
	if($r) {
		return asencode_item($r[0]);
	}
}

function asencode_item_collection($items,$id,$type,$extra = null) {

	$ret = [
		'id' => z_root() . '/' . $id,
		'type' => $type,
		'totalItems' => count($items),
	];
	if($extra)
		$ret = array_merge($ret,$extra);

	if($items) {
		$x = [];
		foreach($items as $i) {
			$x[] = asencode_activity($i);
		}
		if($type === 'OrderedCollection')
			$ret['orderedItems'] = $x;
		else
			$ret['items'] = $x;
	}

	return $ret;
}

function asencode_follow_collection($items,$id,$type,$extra = null) {

	$ret = [
		'id' => z_root() . '/' . $id,
		'type' => $type,
		'totalItems' => count($items),
	];
	if($extra)
		$ret = array_merge($ret,$extra);

	if($items) {
		$x = [];
		foreach($items as $i) {
			if($i['xchan_network'] === 'activitypub' ) {
				if($i['xchan_url']) {
					$x[] = $i['xchan_url'];
				}
			}
			else {
				if($i['xchan_addr']) {
					$x[] = 'acct:' . $i['xchan_addr'];
				}
			}
		}
		if($type === 'OrderedCollection')
			$ret['orderedItems'] = $x;
		else
			$ret['items'] = $x;
	}

	return $ret;
}




function asencode_item($i) {

	$ret = [];

	if(intval($i['item_deleted'])) {
		$ret['type'] = 'Tombstone';
		$ret['formerType'] = 'Note';
		$ret['id'] = ((strpos($i['mid'],'http') === 0) ? $i['mid'] : z_root() . '/item/' . urlencode($i['mid']));
		return $ret;
	}

	$images = false;
	$has_images = preg_match_all('/\[[zi]mg(.*?)\](.*?)\[/ism',$i['body'],$images,PREG_SET_ORDER); 

	if((! $has_images) || get_pconfig($i['uid'],'activitypub','downgrade_media',true))
		$ret['type'] = 'Note';
	else
		$ret['type'] = 'Article';

	$ret['id']   = ((strpos($i['mid'],'http') === 0) ? $i['mid'] : z_root() . '/item/' . urlencode($i['mid']));

	if($i['title'])
		$ret['title'] = bbcode($i['title']);

	$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);
	if($i['created'] !== $i['edited'])
		$ret['updated'] = datetime_convert('UTC','UTC',$i['edited'],ATOM_TIME);
	if($i['app']) {
		$ret['instrument'] = [ 'type' => 'Service', 'name' => $i['app'] ];
	}
	if($i['location'] || $i['coord']) {
		$ret['location'] = [ 'type' => 'Place' ];
		if($i['location']) {
			$ret['location']['name'] = $i['location'];
		}
		if($i['coord']) {
			$l = explode(' ',$i['coord']);
			$ret['location']['latitude'] = $l[0];
			$ret['location']['longitude'] = $l[1];
		}
	}
	$ret['url'] = [
		'type' => 'Link',
		'rel'  => 'alternate',
		'type' => 'text/html',
		'href' => $i['plink']
	];

	$cnv = null;

	if($i['id'] != $i['parent']) {
		$ret['inReplyTo'] = ((strpos($i['parent_mid'],'http') === 0) ? $i['parent_mid'] : z_root() . '/item/' . urlencode($i['parent_mid']));
		$cnv = get_iconfig($i['parent'],'ostatus','conversation');
	}
	if(! $cnv) {
		$cnv = get_iconfig($i,'ostatus','conversation');
	}
	if($cnv) {
		$ret['conversation'] = $cnv;
	}

	$ret['content']   = bbcode($i['body']);

	$ret['actor']     = asencode_person($i['author']);

	$t = asencode_taxonomy($i);
	if($t) {
		$ret['tag']       = $t;
	}

	$a = asencode_attachment($i);
	if($a) {
		$ret['attachment'] = $a;
	}

    if($has_images && $ret['type'] === 'Note') {
		$img = [];
        foreach($images as $match) {
			$img[] =  [ 'type' => 'Image', 'url' => $match[2] ]; 
        }
		if(! $ret['attachment'])
			$ret['attachment'] = [];

		$ret['attachment'] = array_merge($img,$ret['attachment']);
    }

	return $ret;
}

function asdecode_taxonomy($item) {

	$ret = [];

	if($item['tag']) {
		foreach($item['tag'] as $t) {
			if(! array_key_exists('type',$t))
				$t['type'] = 'Hashtag';

			switch($t['type']) {
				case 'Hashtag':
					$ret[] = [ 'ttype' => TERM_HASHTAG, 'url' => $t['id'], 'term' => ((substr($t['name'],0,1) === '#') ? substr($t['name'],1) : $t['name']) ];
					break;

				case 'Mention':
TERM_MENTION:
					$ret[] = [ 'ttype' => TERM_MENTION, 'url' => $t['href'], 'term' => ((substr($t['name'],0,1) === '@') ? substr($t['name'],1) : $t['name']) ];
					break;

				default:
					break;
			}
		}
	}

	return $ret;
}


function asencode_taxonomy($item) {

	$ret = [];

	if($item['term']) {
		foreach($item['term'] as $t) {
			switch($t['ttype']) {
				case TERM_HASHTAG:
					$ret[] = [ 'id' => $t['url'], 'name' => '#' . $t['term'] ];
					break;

				case TERM_MENTION:
					$ret[] = [ 'type' => 'Mention', 'href' => $t['url'], 'name' => '@' . $t['term'] ];
					break;

				default:
					break;
			}
		}
	}

	return $ret;
}

function asencode_attachment($item) {

	$ret = [];

	if($item['attach']) {
		$atts = json_decode($item['attach'],true);
		if($atts) {
			foreach($atts as $att) {
				if(strpos($att['type'],'image')) {
					$ret[] = [ 'type' => 'Image', 'url' => $att['href'] ];
				}
				else {
					$ret[] = [ 'type' => 'Link', 'mediaType' => $att['type'], 'href' => $att['href'] ];
				}
			}
		}
	}

	return $ret;
}


function asdecode_attachment($item) {

	$ret = [];

	if($item['attachment']) {
		foreach($item['attachment'] as $att) {
			$entry = [];
			if($att['href'])
				$entry['href'] = $att['href'];
			elseif($att['url'])
				$entry['href'] = $att['url'];
			if($att['mediaType'])
				$entry['type'] = $att['mediaType'];
			elseif($att['type'] === 'Image')
				$entry['type'] = 'image/jpeg';
			if($entry)
				$ret[] = $entry;
		}
	}

	return $ret;
}



function asencode_activity($i) {

	$ret   = [];
	$reply = false;

	if(intval($i['item_deleted'])) {
		$ret['type'] = 'Tombstone';
		$ret['formerType'] = activity_obj_mapper($i['obj_type']);
		$ret['id'] = ((strpos($i['mid'],'http') === 0) ? $i['mid'] : z_root() . '/item/' . urlencode($i['mid']));
		return $ret;
	}

	$ret['type'] = activity_mapper($i['verb']);
	$ret['id']   = ((strpos($i['mid'],'http') === 0) ? $i['mid'] : z_root() . '/activity/' . urlencode($i['mid']));

	if($i['title'])
		$ret['title'] = html2plain(bbcode($i['title']));

	$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);
	if($i['created'] !== $i['edited'])
		$ret['updated'] = datetime_convert('UTC','UTC',$i['edited'],ATOM_TIME);
	if($i['app']) {
		$ret['instrument'] = [ 'type' => 'Service', 'name' => $i['app'] ];
	}
	if($i['location'] || $i['coord']) {
		$ret['location'] = [ 'type' => 'Place' ];
		if($i['location']) {
			$ret['location']['name'] = $i['location'];
		}
		if($i['coord']) {
			$l = explode(' ',$i['coord']);
			$ret['location']['latitude'] = $l[0];
			$ret['location']['longitude'] = $l[1];
		}
	}

	if($i['id'] != $i['parent']) {
		$ret['inReplyTo'] = ((strpos($i['parent_mid'],'http') === 0) ? $i['parent_mid'] : z_root() . '/item/' . urlencode($i['parent_mid']));

		$d = q("select xchan_url from item left join xchan on xchan_hash = author_xchan where id = %d limit 1",
			intval($i['parent'])
		);
		if($d) {
			$reply_url = (($i['item_private']) ? $d[0]['xchan_url'] : ACTIVITY_PUBLIC_INBOX);
		}

		$reply = true;
	}


	$ret['actor'] = asencode_person($i['author']);

	if($i['obj']) {
		$ret['object'] = asencode_object($i['obj']);
	}
	else {
		$ret['object'] = asencode_item($i);
	}


	if($i['target']) {
		$ret['target'] = asencode_object($i['target']);
	}

	if($reply) {
		$ret['to'] = [ $reply_url ];
		if(in_array($ret['object']['type'], [ 'Note', 'Article' ]))
			$ret['object']['to'] = $ret['to'];
	}
	else {
		if($i['item_private']) {
			$ret['bto'] = as_map_acl($i);
			if(in_array($ret['object']['type'], [ 'Note', 'Article' ]))
				$ret['object']['bto'] = $ret['bto'];
		}
		else {
			$ret['to'] = [ ACTIVITY_PUBLIC_INBOX ];
			if(in_array($ret['object']['type'], [ 'Note', 'Article' ]))
				$ret['object']['to'] = $ret['to'];

			if($i['item_origin']) {
				$ret['cc'] = [ z_root() . '/followers/' . substr($i['owner']['xchan_addr'],0,strpos($i['owner']['xchan_addr'],'@')) ];
				if(in_array($ret['object']['type'], [ 'Note', 'Article' ]))
					$ret['object']['cc'] = $ret['cc'];
			}				
		}
	} 


	return $ret;
}


function as_map_acl($i) {

	$private = false;
	$list = [];
	$x = collect_recipients($i,$private);
	if($x) {
		stringify_array_elms($x);
		if(! $x)
			return;

		$strict = get_config('activitypub','compliance');
		$sql_extra = (($strict) ? " and xchan_network = 'activitypub' " : '');

		$details = q("select xchan_url from xchan where xchan_hash in (" . implode(',',$recipients) . ") $sql_extra");
		if($details) {
			foreach($details as $d) {
				$list[] = $d['xchan_url'];
			}
		}
	}

	return $list;

}


function asencode_person($p) {

	$ret = [];
	$ret['type']  = 'Person';
	$ret['id']    = $p['xchan_url'];
	if($p['xchan_addr'] && strpos($p['xchan_addr'],'@'))
		$ret['preferredUsername'] = substr($p['xchan_addr'],0,strpos($p['xchan_addr'],'@'));
	$ret['name']  = $p['xchan_name'];
	$ret['icon']  = [ 
		'type'      => 'Image',
		'mediaType' => (($p['xchan_photo_mimetype']) ? $p['xchan_photo_mimetype'] : 'image/png' ),
		'url'       => $p['xchan_photo_l'],
		'height'    => 300,
		'width'     => 300,
	];
	$ret['url'] = [
		'type'      => 'Link',
		'mediaType' => 'text/html',
		'href'      => $p['xchan_url']
	];

	$c = channelx_by_hash($p['xchan_hash']);

	if($c) {

		$ret['inbox']       = z_root() . '/inbox/'     . $c['channel_address'];
		$ret['outbox']      = z_root() . '/outbox/'    . $c['channel_address'];
		$ret['followers']   = z_root() . '/followers/' . $c['channel_address'];
		$ret['following']   = z_root() . '/following/' . $c['channel_address'];

		$ret['endpoints']   = [ 'sharedInbox' => z_root() . '/inbox' ];

		$ret['publicKey'] = [
			'id'           => $p['xchan_url'] . '/public_key_pem',
			'owner'        => $p['xchan_url'],
			'publicKeyPem' => $p['xchan_pubkey']
		];

		$locs = zot_encode_locations($c);
		if($locs) {
			$ret['nomadicLocations'] = [];
			foreach($locs as $loc) {
				$ret['nomadicLocations'][] = [
					'id'      => $loc['url'] . '/locs/' . substr($loc['address'],0,strpos($loc['address'],'@')),
					'type'            => 'nomadicLocation',
					'locationAddress' => 'acct:' . $loc['address'],
					'locationPrimary' => (boolean) $loc['primary'],
					'locationDeleted' => (boolean) $loc['deleted']
				];
			}
		}
	}
	else {
		$collections = get_xconfig($p['xchan_hash'],'activitystreams','collections',[]);
		if($collections) {
			$ret = array_merge($ret,$collections);
		}
		else {
			$ret['inbox'] = null;
			$ret['outbox'] = null;
		}
	}

	return $ret;
}


function activity_mapper($verb) {

	$acts = [
		'http://activitystrea.ms/schema/1.0/post'      => 'Create',
		'http://activitystrea.ms/schema/1.0/update'    => 'Update',
		'http://activitystrea.ms/schema/1.0/like'      => 'Like',
		'http://activitystrea.ms/schema/1.0/favorite'  => 'Like',
		'http://purl.org/zot/activity/dislike'         => 'Dislike',
		'http://activitystrea.ms/schema/1.0/tag'       => 'Add',
		'http://activitystrea.ms/schema/1.0/follow'    => 'Follow',
		'http://activitystrea.ms/schema/1.0/unfollow'  => 'Unfollow',
	];


	if(array_key_exists($verb,$acts) && $acts[$verb]) {
		return $acts[$verb];
	}

	// Reactions will just map to normal activities

	if(strpos($verb,ACTIVITY_REACT) !== false)
		return 'Create';
	if(strpos($verb,ACTIVITY_MOOD) !== false)
		return 'Create';

	if(strpos($verb,ACTIVITY_POKE) !== false)
		return 'Activity';

	return false;
}


function activity_obj_mapper($obj) {

	$objs = [
		'http://activitystrea.ms/schema/1.0/note'           => 'Note',
		'http://activitystrea.ms/schema/1.0/comment'        => 'Note',
		'http://activitystrea.ms/schema/1.0/person'         => 'Person',
		'http://purl.org/zot/activity/profile'              => 'Profile',
		'http://activitystrea.ms/schema/1.0/photo'          => 'Image',
		'http://activitystrea.ms/schema/1.0/profile-photo'  => 'Icon',
		'http://activitystrea.ms/schema/1.0/event'          => 'Event',
		'http://activitystrea.ms/schema/1.0/wiki'           => 'Document',
		'http://purl.org/zot/activity/location'             => 'Place',
		'http://purl.org/zot/activity/chessgame'            => 'Game',
		'http://purl.org/zot/activity/tagterm'              => 'zot:Tag',
		'http://purl.org/zot/activity/thing'                => 'Object',
		'http://purl.org/zot/activity/file'                 => 'zot:File',
		'http://purl.org/zot/activity/mood'                 => 'zot:Mood',
		
	];

	if(array_key_exists($obj,$objs)) {
		return $objs[$obj];
	}
	return false;
}


function as_fetch($url) {

	if(! check_siteallowed($url)) {
		logger('blacklisted: ' . $url);
		return null;
	}

	$redirects = 0;
	$x = z_fetch_url($url,true,$redirects,
		['headers' => [ 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/activity+json']]);

	if($x['success']) {
		return $x['body'];
	}
	return null;
}

function as_follow($channel,$act) {

	$contact = null;
	$their_follow_id = null;

	/*
	 * 
	 * if $act->type === 'Follow', actor is now following $channel 
	 * if $act->type === 'Accept', actor has approved a follow request from $channel 
	 *	 
	 */

	$person_obj = $act->actor;

	if($act->type === 'Follow') {
		$their_follow_id  = $act->id;
	}
	elseif($act->type === 'Accept') {
		$my_follow_id = z_root() . '/follow/' . $contact['id'];
	}
	
	if(is_array($person_obj)) {

		// store their xchan and hubloc

		as_actor_store($person_obj['id'],$person_obj);

		// Find any existing abook record 

		$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d limit 1",
			dbesc($person_obj['id']),
			intval($channel['channel_id'])
		);
		if($r) {
			$contact = $r[0];
		}
	}

	$x = \Zotlabs\Access\PermissionRoles::role_perms('social');
	$their_perms = \Zotlabs\Access\Permissions::FilledPerms($x['perms_connect']);

	if($contact && $contact['abook_id']) {

		// A relationship of some form already exists on this site. 

		switch($act->type) {

			case 'Follow':

				// A second Follow request, but we haven't approved the first one

				if($contact['abook_pending']) {
					return;
				}

				// We've already approved them or followed them first
				// Send an Accept back to them

				set_abconfig($channel['channel_id'],$person_obj['id'],'pubcrawl','their_follow_id', $their_follow_id);
				\Zotlabs\Daemon\Master::Summon([ 'Notifier', 'permission_accept', $contact['abook_id'] ]);
				return;

			case 'Accept':

				// They accepted our Follow request - set default permissions

				foreach($their_perms as $k => $v) {
					set_abconfig($channel['channel_id'],$contact['abook_xchan'],'their_perms',$k,$v);
				}

				$abook_instance = $contact['abook_instance'];

				if(strpos($abook_instance,z_root()) === false) {
					if($abook_instance) 
						$abook_instance .= ',';
					$abook_instance .= z_root();

					$r = q("update abook set abook_instance = '%s', abook_not_here = 0 
						where abook_id = %d and abook_channel = %d",
						dbesc($abook_instance),
						intval($contact['abook_id']),
						intval($channel['channel_id'])
					);
				}
		
				return;
			default:
				return;

		}
	}

	// No previous relationship exists.

	if($act->type === 'Accept') {
		// This should not happen unless we deleted the connection before it was accepted.
		return;
	}

	// From here on out we assume a Follow activity to somebody we have no existing relationship with

	set_abconfig($channel['channel_id'],$person_obj['id'],'pubcrawl','their_follow_id', $their_follow_id);

	// The xchan should have been created by as_actor_store() above

	$r = q("select * from xchan where xchan_hash = '%s' and xchan_network = 'activitypub' limit 1",
		dbesc($person_obj['id'])
	);

	if(! $r) {
		logger('xchan not found for ' . $person_obj['id']);
		return;
	}
	$ret = $r[0];

	$p = \Zotlabs\Access\Permissions::connect_perms($channel['channel_id']);
	$my_perms  = $p['perms'];
	$automatic = $p['automatic'];

	$closeness = get_pconfig($channel['channel_id'],'system','new_abook_closeness');
	if($closeness === false)
		$closeness = 80;

	$r = abook_store_lowlevel(
		[
			'abook_account'   => intval($channel['channel_account_id']),
			'abook_channel'   => intval($channel['channel_id']),
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
			set_abconfig($channel['channel_id'],$ret['xchan_hash'],'my_perms',$k,$v);

	if($their_perms)
		foreach($their_perms as $k => $v)
			set_abconfig($channel['channel_id'],$ret['xchan_hash'],'their_perms',$k,$v);


	if($r) {
		logger("New ActivityPub follower for {$channel['channel_name']}");

		$new_connection = q("select * from abook left join xchan on abook_xchan = xchan_hash left join hubloc on hubloc_hash = xchan_hash where abook_channel = %d and abook_xchan = '%s' order by abook_created desc limit 1",
			intval($channel['channel_id']),
			dbesc($ret['xchan_hash'])
		);
		if($new_connection) {
			\Zotlabs\Lib\Enotify::submit(
				[
					'type'	       => NOTIFY_INTRO,
					'from_xchan'   => $ret['xchan_hash'],
					'to_xchan'     => $channel['channel_hash'],
					'link'         => z_root() . '/connedit/' . $new_connection[0]['abook_id'],
				]
			);

			if($my_perms && $automatic) {
				// send an Accept for this Follow activity
				\Zotlabs\Daemon\Master::Summon([ 'Notifier', 'permission_accept', $new_connection[0]['abook_id'] ]);
				// Send back a Follow notification to them
				\Zotlabs\Daemon\Master::Summon([ 'Notifier', 'permission_create', $new_connection[0]['abook_id'] ]);
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
		
			$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);

			if($abconfig)
				$clone['abconfig'] = $abconfig;

			build_sync_packet($channel['channel_id'], [ 'abook' => array($clone) ] );
		}
	}


	/* If there is a default group for this channel, add this member to it */

	if($channel['channel_default_group']) {
		require_once('include/group.php');
		$g = group_rec_byhash($channel['channel_id'],$channel['channel_default_group']);
		if($g)
			group_add_member($channel['channel_id'],'',$ret['xchan_hash'],$g['id']);
	}


	return;

}


function as_unfollow($channel,$act) {

	$contact = null;

	/* @FIXME This really needs to be a signed request. */

	/* actor is unfollowing $channel */

	$person_obj = $act->actor;

	if(is_array($person_obj)) {

		$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d limit 1",
			dbesc($person_obj['id']),
			intval($channel['channel_id'])
		);
		if($r) {
			// This is just to get a list of permission names, we don't care about the values
			$x = \Zotlabs\Access\PermissionRoles::role_perms('social');
			$my_perms = \Zotlabs\Access\Permissions::FilledPerms($x['perms_connect']);

			// remove all permissions they provided

			foreach($my_perms as $k => $v) {
				del_abconfig($channel['channel_id'],$r[0]['xchan_hash'],'their_perms',$k);
			}
		}
	}

	return;
}




function as_actor_store($url,$person_obj) {

	if(! is_array($person_obj))
		return;

	$name = $person_obj['name'];
	if(! $name)
		$name = $person_obj['preferredUsername'];
	if(! $name)
		$name = t('Unknown');

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

	if(is_array($person_obj['url']) && array_key_exists('href', $person_obj['url']))
		$profile = $person_obj['url']['href'];
	else
		$profile = $url;


	$inbox = $person_obj['inbox'];

	$collections = [];

	if($inbox) {
		$collections['inbox'] = $inbox;
		if($person_obj['outbox'])
			$collections['outbox'] = $person_obj['outbox'];
		if($person_obj['followers'])
			$collections['followers'] = $person_obj['followers'];
		if($person_obj['following'])
			$collections['following'] = $person_obj['following'];
		if($person_obj['endpoints'] && $person_obj['endpoints']['sharedInbox'])
			$collections['sharedInbox'] = $person_obj['endpoints']['sharedInbox'];
	}

	if(array_key_exists('publicKey',$person_obj) && array_key_exists('publicKeyPem',$person_obj['publicKey'])) {
		if($person_obj['id'] === $person_obj['publicKey']['owner']) {
			$pubkey = $person_obj['publicKey']['publicKeyPem'];
		}
	}

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

		// Record exists. Cache existing records for one week at most
		// then refetch to catch updated profile photos, names, etc. 

		$d = datetime_convert('UTC','UTC','now - 1 week');
		if($r[0]['xchan_name_date'] > $d)
			return;

		// update existing record
		$r = q("update xchan set xchan_name = '%s', xchan_pubkey = '%s', xchan_network = '%s', xchan_name_date = '%s' where xchan_hash = '%s'",
			dbesc($name),
			dbesc($pubkey),
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


	$m = parse_url($url);
	if($m) {
		$hostname = $m['host'];
	}

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
		$icon = z_root() . '/' . get_default_profile_photo(300);

	$photos = import_xchan_photo($icon,$url);
	$r = q("update xchan set xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s'",
		dbescdate(datetime_convert('UTC','UTC',$arr['photo_updated'])),
		dbesc($photos[0]),
		dbesc($photos[1]),
		dbesc($photos[2]),
		dbesc($photos[3]),
		dbesc($url)
	);

}


function as_create_action($channel,$observer_hash,$act) {

	if(in_array($act->obj['type'], [ 'Note', 'Article' ])) {
		as_create_note($channel,$observer_hash,$act);
	}


}

function as_like_action($channel,$observer_hash,$act) {

	if(in_array($act->obj['type'], [ 'Note', 'Article' ])) {
		as_like_note($channel,$observer_hash,$act);
	}


}



function as_create_note($channel,$observer_hash,$act) {

	$s = [];


	$parent = ((array_key_exists('inReplyTo',$act->obj)) ? urldecode($act->obj['inReplyTo']) : '');
	if($parent) {

		$r = q("select * from item where uid = %d and ( mid = '%s' || mid = '%s' ) and parent_mid = mid limit 1",
			intval($channel['channel_id']),
			dbesc($parent),
			dbesc(basename($parent))
		);

		if(! $r) {
			logger('parent not found.');
			return;
		}
		if($r[0]['owner_xchan'] === $channel['channel_hash']) {
			if(! perm_is_allowed($channel['channel_id'],$observer_hash,'post_comments')) {
				logger('no comment permission.');
				return;
			}
		}

		$s['parent_mid'] = $r[0]['mid'];
		$s['owner_xchan'] = $r[0]['owner_xchan'];
		$s['author_xchan'] = $observer_hash;
	}
	else {
		if(! perm_is_allowed($channel['channel_id'],$observer_hash,'send_stream')) {
			logger('no permission');
			return;
		}
		$s['owner_xchan'] = $s['author_xchan'] = $observer_hash;		
	}
	
	$abook = q("select * from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
		dbesc($observer_hash),
		intval($channel['channel_id'])
	);
	
	$content = as_get_content($act->obj);

	if(! $content) {
		logger('no content');
		return;
	}

	$s['aid'] = $channel['channel_account_id'];
	$s['uid'] = $channel['channel_id'];
	$s['mid'] = urldecode($act->obj['id']);


	if($act->data['published']) {
		$s['created'] = datetime_convert('UTC','UTC',$act->data['published']);
	}
	elseif($act->obj['published']) {
		$s['created'] = datetime_convert('UTC','UTC',$act->obj['published']);
	}
	if($act->data['updated']) {
		$s['edited'] = datetime_convert('UTC','UTC',$act->data['updated']);
	}
	elseif($act->obj['updated']) {
		$s['edited'] = datetime_convert('UTC','UTC',$act->obj['updated']);
	}

	if(! $s['created'])
		$s['created'] = datetime_convert();

	if(! $s['edited'])
		$s['edited'] = $s['created'];


	if(! $s['parent_mid'])
		$s['parent_mid'] = $s['mid'];
	
	$s['title']    = as_bb_content($content,'name');
	$s['body']     = as_bb_content($content,'content');
	$s['verb']     = ACTIVITY_POST;
	$s['obj_type'] = ACTIVITY_OBJ_NOTE;
	$s['app']      = t('ActivityPub');

	if($abook) {
		if(! post_is_importable($s,$abook[0])) {
			logger('post is filtered');
			return;
		}
	}

	if($act->obj['conversation']) {
		set_iconfig($s,'ostatus','conversation',$act->obj['conversation'],1);
	}

	$a = asdecode_taxonomy($act->obj);
	if($a) {
		$s['term'] = $a;
	}

	$a = asdecode_attachment($act->obj);
	if($a) {
		$s['attach'] = $a;
	}

	if($act->obj['type'] === 'Note' && $s['attach']) {
		foreach($s['attach'] as $img) {
			if(strpos($img['type'],'image') !== false) {
				$s['body'] .= "\n\n" . '[img]' . $img['href'] . '[/img]';
			}
		}
	}

	set_iconfig($s,'activitypub','recips',$act->raw_recips);

	$r = q("select created, edited from item where mid = '%s' and uid = %d limit 1",
		dbesc($s['mid']),
		intval($s['uid'])
	);
	if($r) {
		if($s['edited'] > $r[0]['edited']) {
			$x = item_store_update($s);
		}
		else {
			return;
		}
	}
	else {
		$x = item_store($s);
	}

}


function as_like_note($channel,$observer_hash,$act) {

	$s = [];

	$parent = $act->obj['id'];

	if($act->type === 'Like')
		$s['verb'] = ACTIVITY_LIKE;
	if($act->type === 'Dislike')
		$s['verb'] = ACTIVITY_DISLIKE;

	if(! $parent)
		return;

	$r = q("select * from item where uid = %d and ( mid = '%s' || mid = '%s' ) limit 1",
		intval($channel['channel_id']),
		dbesc($parent),
		dbesc(urldecode(basename($parent)))
	);

	if(! $r) {
		logger('parent not found.');
		return;
	}

	xchan_query($r);
	$parent_item = $r[0];

	if($parent_item['owner_xchan'] === $channel['channel_hash']) {
		if(! perm_is_allowed($channel['channel_id'],$observer_hash,'post_comments')) {
			logger('no comment permission.');
			return;
		}
	}

	if($parent_item['mid'] === $parent_item['parent_mid']) {
		$s['parent_mid'] = $parent_item['mid'];
	}
	else {
		$s['thr_parent'] = $parent_item['mid'];
		$s['parent_mid'] = $parent_item['parent_mid'];
	}

	$s['owner_xchan'] = $parent_item['owner_xchan'];
	$s['author_xchan'] = $observer_hash;

	$s['aid'] = $channel['channel_account_id'];
	$s['uid'] = $channel['channel_id'];
	$s['mid'] = $act->id;

	if(! $s['parent_mid'])
		$s['parent_mid'] = $s['mid'];
	

	$post_type = (($parent_item['resource_type'] === 'photo') ? t('photo') : t('status'));

	$links = array(array('rel' => 'alternate','type' => 'text/html', 'href' => $parent_item['plink']));
	$objtype = (($parent_item['resource_type'] === 'photo') ? ACTIVITY_OBJ_PHOTO : ACTIVITY_OBJ_NOTE );

	$body = $parent_item['body'];

	$z = q("select * from xchan where xchan_hash = '%s' limit 1",
		dbesc($parent_item['author_xchan'])
	);
	if($z)
		$item_author = $z[0];		

	$object = json_encode(array(
		'type'    => $post_type,
		'id'      => $parent_item['mid'],
		'parent'  => (($parent_item['thr_parent']) ? $parent_item['thr_parent'] : $parent_item['parent_mid']),
		'link'    => $links,
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
		), JSON_UNESCAPED_SLASHES
	);

	if($act->type === 'Like')
		$bodyverb = t('%1$s likes %2$s\'s %3$s');
	if($act->type === 'Dislike')
		$bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');

	$ulink = '[url=' . $item_author['xchan_url'] . ']' . $item_author['xchan_name'] . '[/url]';
	$alink = '[url=' . $parent_item['author']['xchan_url'] . ']' . $parent_item['author']['xchan_name'] . '[/url]';
	$plink = '[url='. z_root() . '/display/' . urlencode($act->id) . ']' . $post_type . '[/url]';
	$s['body'] =  sprintf( $bodyverb, $ulink, $alink, $plink );

	$s['app']  = t('ActivityPub');

	// set the route to that of the parent so downstream hubs won't reject it.

	$s['route'] = $parent_item['route'];
	$s['item_private'] = $parent_item['item_private'];
	$s['obj_type'] = $objtype;
	$s['obj'] = $object;

	if($act->obj['conversation']) {
		set_iconfig($s,'ostatus','conversation',$act->obj['conversation'],1);
	}

	set_iconfig($s,'activitypub','recips',$act->raw_recips);

	$result = item_store($s);

	if($result['success']) {
		// if the message isn't already being relayed, notify others
		if(intval($parent_item['item_origin']))
				Zotlabs\Daemon\Master::Summon(array('Notifier','comment-import',$result['item_id']));
			sync_an_item($channel['channel_id'],$result['item_id']);
	}

	return;
}






function as_bb_content($content,$field) {

	require_once('include/html2bbcode.php');

	$ret = false;

	if(is_array($content[$field])) {
		foreach($content[$field] as $k => $v) {
			$ret .= '[language=' . $k . ']' . html2bbcode($v) . '[/language]';
		}
	}
	else {
		$ret = html2bbcode($content[$field]);
	}

	return $ret;
}



function as_get_content($act) {

	$content = [];

	foreach([ 'name', 'summary', 'content' ] as $a) {
		if(($x = as_get_textfield($act,$a)) !== false) {
			$content[$a] = $x;
		}
	}

	return $content;
}


function as_get_textfield($act,$field) {
	
	$content = false;

	if(array_key_exists($field,$act) && $act[$field])
		$content = purify_html($act[$field]);
	elseif(array_key_exists($field . 'Map',$act) && $act[$field . 'Map']) {
		foreach($act[$field . 'Map'] as $k => $v) {
			$content[escape_tags($k)] = purify_html($v);
		}
	}
	return $content;
}
