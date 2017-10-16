<?php

/**
 * Name: Zot VI
 *
 */

require_once('addon/zotvi/zot6.php');

function zotvi_load() {

	Zotlabs\Extend\Hook::register_array('addon/zotvi/zotvi.php', [
		'module_loaded'              => 'zotvi_load_module',
		'webfinger'                  => 'zotvi_webfinger',
		'channel_mod_init'           => 'zotvi_channel_mod_init',
		'home_mod_init'              => 'zotvi_home_mod_init',
		'zot_revision'               => 'zotvi_zot_revision',
		'queue_deliver'              => 'zotvi_queue_deliver',
		'channel_links'              => 'zotvi_channel_links',
	]);

}


function zotvi_unload() {
	Zotlabs\Extend\Hook::unregister_by_file('addon/zotvi/zotvi.php');
}


function zotvi_load_module(&$b) {
	if($b['module'] === 'zot_probe') {
		require_once('addon/zotvi/Mod_Zot_probe.php');
		$b['controller'] = new \Zotlabs\Module\Zot_probe();
		$b['installed'] = true;
	}
	if($b['module'] === 'zot') {
		require_once('addon/zotvi/Mod_Zot.php');
		$b['controller'] = new \Zotlabs\Module\Zot();
		$b['installed'] = true;
	}
}


function zotvi_channel_links(&$b) {
	$c = channelx_by_nick($b['channel_address']);
	$b['channel_links'][] = [
		'rel'  => 'alternate',
		'type' => 'application/x-zot+json',
		'url'  => z_root() . '/channel/' . $c['channel_address']
	];
}



function zotvi_zot_revision(&$b) {
//  Do not enable yet.
//	$b['revision'] = '6.0';
}

function zotvi_webfinger(&$b) {
	if(! $b['channel'])
		return;

	$b['result']['links'][] = [ 
		'rel'  => 'http://purl.org/zot/protocol/6.0', 
		'type' => 'application/x-zot+json', 
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
}

function zotvi_is_zot_request() {

	if($_REQUEST['module_format'] === 'json')
		return true;

	$x = getBestSupportedMimeType([
		'application/x-zot+json'
	]);

	return(($x) ? true : false);

}


function zotvi_channel_mod_init($x) {
	
	if(zotvi_is_zot_request()) {
		$channel = channelx_by_nick(argv(1));
		if(! $channel)
			http_status_exit(404, 'Not found');

		
		$x = zot6::zotinfo([ 'address' => $channel['channel_address'] ]);

		$headers = [];
		$headers['Content-Type'] = 'application/x-zot+json' ;

		$ret = json_encode($x);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],z_root() . '/channel/' . $channel['channel_address'],true);
		echo $ret;
		killme();
	}
}


function zotvi_home_mod_init($x) {
	
	if(zotvi_is_zot_request()) {

		$channel = [ 'channel_prvkey' => get_config('system','prvkey') ];
		
		$x = zot6::zot_site_info();

		$headers = [];
		$headers['Content-Type'] = 'application/x-zot+json' ;

		$ret = json_encode($x);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],z_root(),true);
		echo $ret;
		killme();
	}
}


function zot_getzotkey($url) {

	if(! check_siteallowed($url)) {
		logger('blacklisted: ' . $url);
		return null;
	}

	if($url === z_root()) {
		$j = zot_site_info();
	}
	else {
		$redirects = 0;
		$x = z_fetch_url($url,true,$redirects,
			[ 'headers' => [ 'Accept: application/x-zot+json, application/json;' ] ]
		);

		if($x['success']) {
			$j = json_decode($x['body'],true);
		}
	}

	if($j) {
		return(($j['key']) ? $j['key'] : false);
	}

	return false;

}



function zotvi_queue_deliver(&$x) {

	// fixme
	if($x['outq']['driver'] !== 'zotvi')
		return;

	$v = get_sconfig($x['base'],'system','zot_revision');
	if($v && version_compare($v,'6.0') >= 0) {

		$s = q("select * from site where site_url = '%s' and site_dead = 0",
			dbesc($x['base'])
		);
		if(! $s)
			return;

		$channel = channelx_by_n($x['outq']['outq_channel']);

		$retries = 0;

		$headers = [];
		$headers['Content-Type'] = 'application/x-zot+json';


		$data = [ 'notify' => $x['outq']['outq_notify'], 'message' => $x['outq']['outq_msg'] ];

		// The envelope and message data should have been encrypted in ZDaemon\Notifier if the contents are sensitive.
		// Presence of encrypted data means we need to encrypt the HTTPSignature header so as not to
		// leak any sender metadata through the header fields. 

		if(array_key_exists('iv',$x['outq']['outq_notify'])) {
			$sitekey = get_sconfig($x['base'],'system','pubkey');
			$algorithm = zot_best_algorithm($s[0]['site_crypto']);
		}
		else {
			$sitekey = '';
			$algorithm = '';
		}

		$ret = json_encode($data, JSON_UNESCAPE_SLASHES);

		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		$xhead = \Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],z_root() . '/channel/' . $channel['channel_address'],false, false, 'sha256',$sitekey,$algorithm);
 	
		$result = z_post_url($x['outq']['outq_posturl'],$ret,$retries,[ 'headers' => $xhead ]);

		zot_process_response($x['base'],$result,$x['outq']);
		$x['handled'] = true;
	}
}

