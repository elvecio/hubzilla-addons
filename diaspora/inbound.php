<?php

require_once('addon/diaspora/Receiver.php');


function diaspora_dispatch_public($msg) {

	$sys_disabled = false;

	if(get_config('system','disable_discover_tab') || get_config('system','disable_diaspora_discover_tab')) {
		$sys_disabled = true;
	}
	$sys = (($sys_disabled) ? null : get_sys_channel());

	// find everybody following or allowing this author

	$r = q("SELECT * from channel where channel_id in ( SELECT abook_channel from abook left join xchan on abook_xchan = xchan_hash WHERE xchan_network like '%%diaspora%%' and xchan_addr = '%s' ) and channel_removed = 0 ",
		dbesc($msg['author'])
	);

	// look for those following tags - we'll check tag validity for each specific channel later 

	$y = q("select * from channel where channel_id in ( SELECT uid from pconfig where cat = 'diaspora' and k = 'followed_tags' and v != '') and channel_removed = 0 ");

	if(is_array($y) && is_array($r))
		$r = array_merge($r,$y);

	// @FIXME we should also enumerate channels that allow postings by anybody

	$msg['public'] = 1;

	if($r) {
		foreach($r as $rr) {
			logger('diaspora_public: delivering to: ' . $rr['channel_name'] . ' (' . $rr['channel_address'] . ') ');
			diaspora_dispatch($rr,$msg);
		}
	}
	else {
		if(! $sys)
			logger('diaspora_public: no subscribers');
	}

	if($sys) {
		$sys['system'] = true;
		logger('diaspora_public: delivering to sys.');
		
		diaspora_dispatch($sys,$msg);
	}
}



function diaspora_dispatch($importer,$msg) {

	$ret = 0;

	if(! array_key_exists('system',$importer))
		$importer['system'] = false;

	if(! array_key_exists('public',$msg))
		$msg['public'] = 0;

	$host = substr($msg['author'],strpos($msg['author'],'@')+1);
	$ssl = ((array_key_exists('HTTPS',$_SERVER) && strtolower($_SERVER['HTTPS']) === 'on') ? true : false);
	$url = (($ssl) ? 'https://' : 'http://') . $host;

	q("update site set site_dead = 0, site_update = '%s' where site_type = %d and site_url = '%s'",
		dbesc(datetime_convert()),
		intval(SITE_TYPE_NOTZOT),
		dbesc($url)
	);

	$allowed = (($importer['system']) ? 1 : get_pconfig($importer['channel_id'],'system','diaspora_allowed'));

	if(! intval($allowed)) {
		logger('mod-diaspora: disallowed for channel ' . $importer['channel_name']);
		return;
	}

	$parsed_xml = xml2array($msg['message'],false,0,'tag');

	if($parsed_xml) {
		if(array_key_exists('xml',$parsed_xml) && array_key_exists('post',$parsed_xml['xml']))
			$xmlbase = $parsed_xml['xml']['post'];
		else
			$xmlbase = $parsed_xml;
	}

	//	logger('diaspora_dispatch: ' . print_r($xmlbase,true), LOGGER_DATA);

	if($xmlbase['request']) {
		$base = $xmlbase['request'];
		$fn = 'request';
	}
	elseif($xmlbase['contact']) {
		$base = $xmlbase['contact'];
		$fn = 'request';
	}
	elseif($xmlbase['status_message']) {
		$base = $xmlbase['status_message'];
		$fn = 'post';
	}
	elseif($xmlbase['profile']) {
		$base = $xmlbase['profile'];
		$fn = 'profile';
	}
	elseif($xmlbase['comment']) {
		$base = $xmlbase['comment'];
		$fn = 'comment';
	}
	elseif($xmlbase['like']) {
		$base = $xmlbase['like'];
		$fn = 'like';
	}
	elseif($xmlbase['reshare']) {
		$base = $xmlbase['reshare'];
		$fn = 'reshare';
	}
	elseif($xmlbase['retraction']) {
		$base = $xmlbase['retraction'];
		$fn = 'retraction';
	}
	elseif($xmlbase['signed_retraction']) {
		$base = $xmlbase['signed_retraction'];
		$fn = 'retraction';
	}
	elseif($xmlbase['relayable_retraction']) {
		$base = $xmlbase['relayable_retraction'];
		$fn = 'retraction';
	}
	elseif($xmlbase['photo']) {
		$base = $xmlbase['photo'];
		$fn = 'photo';
	}
	elseif($xmlbase['conversation']) {
		$base = $xmlbase['conversation'];
		$fn = 'conversation';
	}
	elseif($xmlbase['message']) {
		$base = $xmlbase['message'];
		$fn = 'message';
	}
	elseif($xmlbase['participation']) {
		$base = $xmlbase['participation'];
		$fn = 'participation';
	}
	elseif($xmlbase['account_deletion']) {
		$base = $xmlbase['account_deletion'];
		$fn = 'account_deletion';
	}
	elseif($xmlbase['account_migration']) {
		$base = $xmlbase['account_migration'];
		$fn = 'account_migration';
	}
	elseif($xmlbase['poll_participation']) {
		$base = $xmlbase['poll_participation'];
		$fn = 'poll_participation';
	}
	else {
		logger('diaspora_dispatch: unknown message type: ' . print_r($xmlbase,true));
	}

	$rec = new Diaspora_Receiver($importer,$base,$msg);

	$ret = $rec->$fn();

	return $ret;
}


function diaspora_is_blacklisted($s) {

	if(! check_siteallowed($s)) {
		logger('blacklisted site: ' . $s);
		return true;
	}

	return false;
}


/**
 *
 * diaspora_decode($importer,$xml,$format)
 *   array $importer -> from user table
 *   string $xml -> urldecoded Diaspora salmon
 *   string $format 'legacy', 'salmon', or 'json' 
 *
 * Returns array
 * 'message' -> decoded Diaspora XML message
 * 'author' -> author diaspora handle
 * 'key' -> author public key (converted to pkcs#8)
 * 'format' -> 'legacy', 'json', or 'salmon'
 *
 * Author and key are used elsewhere to save a lookup for verifying replies and likes
 */


function diaspora_decode($importer,$xml,$format) {

	$public = false;

	if($format === 'json') {
		if(! $importer['channel_id']) {
			logger('Private encrypted message arrived on public channel.');
			http_status_exit(400);
		}
		$json = json_decode($xml,true);
		if($json['aes_key']) {
			$key_bundle = '';
			$result = openssl_private_decrypt(base64_decode($json['aes_key']),$key_bundle,$importer['channel_prvkey']);
			if(! $result) {
				logger('decrypting key_bundle for ' . $importer['channel_address'] . ' failed: ' . $json['aes_key'],LOGGER_NORMAL, LOG_ERR);
				http_status_exit(400);
			}
			$jkey = json_decode($key_bundle,true);
			$xml = AES256CBC_decrypt(base64_decode($json['encrypted_magic_envelope']),base64_decode($jkey['key']),base64_decode($jkey['iv']));
			if(! $xml) {
				logger('decrypting magic_envelope for ' . $importer['channel_address'] . ' failed: ' . $json['aes_key'],LOGGER_NORMAL, LOG_ERR);
				http_status_exit(400);
			}
		}
	}

	$basedom = parse_xml_string($xml,false);

	if($basedom === false) {
		logger('unparseable xml');
		http_status_exit(400);
	}

	if($format !== 'legacy') {
		$children = $basedom->children('http://salmon-protocol.org/ns/magic-env');
		$public = true;
		$author_link = str_replace('acct:','',base64url_decode($children->sig[0]->attributes()->key_id[0]));

		/**
			SimpleXMLElement Object
			(
			    [encoding] => base64url
			    [alg] => RSA-SHA256
			    [data] => ((base64url-encoded payload message))
			    [sig] => ((the RSA-SHA256 signature of the above data))
			)
		**/
	} 
	else {

		$children = $basedom->children('https://joindiaspora.com/protocol');

		if($children->header) {
			$public = true;
			$author_link = str_replace('acct:','',$children->header->author_id);
		}
		else {

			if(! $importer['channel_id']) {
				logger('Private encrypted message arrived on public channel.');
				http_status_exit(400);
			}

			$encrypted_header = json_decode(base64_decode($children->encrypted_header));
			$encrypted_aes_key_bundle = base64_decode($encrypted_header->aes_key);
			$ciphertext = base64_decode($encrypted_header->ciphertext);

			$outer_key_bundle = '';
			openssl_private_decrypt($encrypted_aes_key_bundle,$outer_key_bundle,$importer['channel_prvkey']);

			$j_outer_key_bundle = json_decode($outer_key_bundle);

			$outer_iv = base64_decode($j_outer_key_bundle->iv);
			$outer_key = base64_decode($j_outer_key_bundle->key);

			$decrypted = AES256CBC_decrypt($ciphertext,$outer_key,$outer_iv);

			/**
			 * $decrypted now contains something like
			 *
			 *  <decrypted_header>
			 *	 <iv>8e+G2+ET8l5BPuW0sVTnQw==</iv>
			 *	 <aes_key>UvSMb4puPeB14STkcDWq+4QE302Edu15oaprAQSkLKU=</aes_key>
			 ***** OBSOLETE
			 *	 <author>
			 *	   <name>Ryan Hughes</name>
			 *	   <uri>acct:galaxor@diaspora.pirateship.org</uri>
			 *	 </author>
			 ***** CURRENT/LEGACY
			 *	 <author_id>galaxor@diaspora.pirateship.org</author_id>
			 ***** END DIFFS
			 *  </decrypted_header>
			 */

			logger('decrypted: ' . $decrypted, LOGGER_DATA);
			$idom = parse_xml_string($decrypted,false);

			$inner_iv = base64_decode($idom->iv);
			$inner_aes_key = base64_decode($idom->aes_key);

			$author_link = str_replace('acct:','',$idom->author_id);
		}
	}
	
	$dom = $basedom->children(NAMESPACE_SALMON_ME);

	// figure out where in the DOM tree our data is hiding

	if($dom->provenance->data)
		$base = $dom->provenance;
	elseif($dom->env->data)
		$base = $dom->env;
	elseif($dom->data)
		$base = $dom;

	if(! $base) {
		logger('mod-diaspora: unable to locate salmon data in xml ', LOGGER_NORMAL, LOG_ERR);
		http_status_exit(400);
	}


	// Stash the signature away for now. We have to find their key or it won't be good for anything.
	$signature = base64url_decode($base->sig);

	// unpack the  data

	// strip whitespace so our data element will return to one big base64 blob
	$data = str_replace(array(" ","\t","\r","\n"),array("","","",""),$base->data);

	// stash away some other stuff for later

	$type     = $base->data[0]->attributes()->type[0];
	$encoding = $base->encoding;
	$alg      = $base->alg;

	$signed_data = $data  . '.' . base64url_encode($type,false) . '.' . base64url_encode($encoding,false) . '.' . base64url_encode($alg,false);


	// decode the data
	$data = base64url_decode($data);

	if(($format === 'legacy') && (! $public)) {
		// Decode the encrypted blob
		$final_msg = AES256CBC_decrypt(base64_decode($data),$inner_aes_key,$inner_iv);
	}
	else {
		$final_msg = $data;
	}

	if(! $author_link) {
		logger('mod-diaspora: Could not retrieve author URI.');
		http_status_exit(400);
	}

	// Once we have the author URI, go to the web and try to find their public key
	// (first this will look it up locally if it is in the fcontact cache)
	// This will also convert diaspora public key from pkcs#1 to pkcs#8

	logger('mod-diaspora: Fetching key for ' . $author_link );
 	$key = get_diaspora_key($author_link);

	if(! $key) {
		logger('mod-diaspora: Could not retrieve author key.', LOGGER_NORMAL, LOG_WARNING);
		http_status_exit(400);
	}

	$verify = rsa_verify($signed_data,$signature,$key);

	if(! $verify) {
		logger('mod-diaspora: Message did not verify. Discarding.', LOGGER_NORMAL, LOG_ERR);
		http_status_exit(400);
	}

	logger('mod-diaspora: Message verified.');

	return array('message' => $final_msg, 'author' => $author_link, 'key' => $key, 'format' => $format);

}

