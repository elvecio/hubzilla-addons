<?php

/**
 * @brief
 *
 * @param array $items
 * @return array
 */
function gen_asld($items) {
	$ret = array();
	if(! $items)
		return $ret;

	foreach($items as $item) {
		$ret[] = i2asld($item);
	}

	return $ret;
}

/**
 * @brief
 *
 * @param array $i
 * @return array
 */
function i2asld($i) {

	if(! $i)
		return array();

	$ret = array();

	$ret['@context'] = array( 'https://www.w3.org/ns/activitystreams', 'zot' => 'http://purl.org/zot/protocol');

	if($i['verb']) {
		if(strpos(dirname($i['verb'],'activitystrea.ms/schema/1.0'))) {
			$ret['type'] = ucfirst(basename($i['verb']));
		}
		elseif(strpos(dirname($i['verb'],'purl.org/zot'))) {
			$ret['type'] = 'zot:' . ucfirst(basename($i['verb']));
		}
	}
	$ret['id'] = $i['plink'];

	$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);

	if($i['obj_type'] === ACTIVITY_OBJ_NOTE)
		$ret['object'] = asencode_item($i);

	$ret['actor'] = asencode_person($i['author']);

	return $ret;
}

function asencode_object($x) {

	if((substr(0,1,trim($x))) === '{' ) {
		$x = json_decode($x,true);
	}
	if($x['type'] === ACTIVITY_OBJ_PERSON) {
		return asfetch_person($x); 
	}
	if($x['type'] === ACTIVITY_OBJ_PERSON) {
		return asfetch_profile($x); 
	}
}	

function asfetch_person($x) {
	return $x;
}

function asfetch_profile($x) {
	return $x;
}

function asencode_item($i) {

	$ret = array();

	if(intval($i['item_deleted'])) {
		$ret['type'] = 'Tombstone';
		$ret['formerType'] = 'Note';
		$ret['id'] = z_root() . '/item/' . urlencode($i['mid']);
		return $ret;
	}

	$ret['type'] = 'Note';
	$ret['id']   = z_root() . '/item/' . urlencode($i['mid']);

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

	if($i['id'] != $i['parent']) {
		$ret['inReplyTo'] = z_root() . '/item/' . urlencode($i['parent_mid']);
	}

	$ret['content']   = bbcode($i['body']);

	$ret['zot:owner'] = asencode_person($i['owner']);
	$ret['actor']     = asencode_person($i['author']);

	$ret['tag'] = [];
	$ret['tag'][] = [ 
		'type' => 'zot:messageId', 
		'id'   => z_root() . '/display/' . urlencode($i['mid']),
		'name' => $i['mid']
	];

	return $ret;
}




function asencode_activity($i) {

	$ret = array();

	if(intval($i['item_deleted'])) {
		$ret['type'] = 'Tombstone';
		$ret['formerType'] = activity_obj_mapper($i['obj_type']);
		$ret['id'] = z_root() . '/item/' . urlencode($i['mid']);
		return $ret;
	}

	$ret['type'] = activity_mapper($i['verb']);
	$ret['id']   = z_root() . '/activity/' . urlencode($i['mid']);

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

	if($i['id'] != $i['parent']) {
		$ret['inReplyTo'] = z_root() . '/item/' . urlencode($i['parent_mid']);
	}

	$ret['content']   = bbcode($i['body']);

	$ret['zot:owner'] = asencode_person($i['owner']);
	$ret['actor']     = asencode_person($i['author']);
	if($i['obj']) {
		$ret['object'] = asencode_object($i['obj']);
	}
	else {
		$ret['object'] = asencode_item($i);
	}

	if($i['target']) {
		$ret['target'] = asencode_object($i['target']);
	}

	$ret['tag'] = [];
	$ret['tag'][] = [ 
		'type' => 'zot:messageId', 
		'id'   => z_root() . '/display/' . urlencode($i['mid']),
		'name' => $i['mid']
	];

	if(! $i['item_private']) {
		$ret['to'] = [ ACTIVITY_PUBLIC_INBOX ];
	}
	else {
		$ret['bto'] = as_map_acl($i);
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
	$ret['name']  = $p['xchan_name'];
	$ret['icon']  = [ 
		[
			'type'      => 'Image',
			'mediaType' => $p['xchan_photo_mimetype'],
			'url'       => $p['xchan_photo_l'],
			'height'    => 300,
			'width'     => 300,
		],
		[
			'type'      => 'Image',
			'mediaType' => $p['xchan_photo_mimetype'],
			'url'       => $p['xchan_photo_m'],
			'height'    => 80,
			'width'     => 80,
		],
		[
			'type'      => 'Image',
			'mediaType' => $p['xchan_photo_mimetype'],
			'url'       => $p['xchan_photo_s'],
			'height'    => 48,
			'width'     => 48,
		]
	];
	$ret['url'] = [
		'type'      => 'Link',
		'mediaType' => 'text/html',
		'href'      => $p['xchan_url']
	];

	$ret['me:magic_keys'] = [
		[ 
			'value'  => salmon_key($p['xchan_pubkey']), 
			'key_id' => base64url_encode(hash('sha256',salmon_key($p['xchan_pubkey'])),true)
		]
	];

	$c = channelx_by_hash($p['xchan_hash']);
	if($c) {
		$ret['inbox']       = z_root() . '/inbox/' . $c['channel_address'];
		$ret['outbox']      = z_root() . '/outbox/' . $c['channel_address'];
		$ret['publicInbox'] = z_root() . '/inbox/[public]';
	}
	else {
		$collections = get_xconfig($p['xchan_hash'],'activitystreams','collections',[]);
		if($collections) {
			$ret = array_merge($ret,$collections);
		}
		else {
			$ret['inbox'] = z_root() . '/nullbox';
			$ret['outbox'] = z_root() . '/nullbox';
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


	if(array_key_exists($verb,$acts)) {
		return $acts[$verb];
	}
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
		'http://purl.org/zot/activity/thing'                => 'zot:Thing',
		'http://purl.org/zot/activity/file'                 => 'zot:File',
		'http://purl.org/zot/activity/poke'                 => 'zot:Action',
		'http://purl.org/zot/activity/react'                => 'zot:Reaction',
		'http://purl.org/zot/activity/mood'                 => 'zot:Mood',
		
	];

	if(array_key_exists($obj,$objs)) {
		return $objs[$obj];
	}
	return false;
}


function as_fetch($url) {
	$redirects = 0;
	$x = z_fetch_url($url,true,$redirects,
		['headers' => [ 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"']]);
	if($x['success']) {
		return $x['body'];
	}
	return null;
}