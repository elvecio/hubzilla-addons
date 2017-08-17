<?php
namespace Zotlabs\Module;


class Outbox extends \Zotlabs\Web\Controller {

	function post() {

	}

	function get() {

		if(observer_prohibited(true)) {
			killme();
		}

		if(argc() < 2)
			killme();

		$channel = channelx_by_nick(argv(1));
		if(! $channel)
			killme();

		$observer_hash = get_observer_hash();

		$params = [];
	
		$params['begin']     = ((x($_REQUEST,'date_begin')) ? $_REQUEST['date_begin']       : NULL_DATE);
		$params['end']       = ((x($_REQUEST,'date_end'))   ? $_REQUEST['date_end']         : '');
		$params['type']      = 'json';
		$params['pages']     = ((x($_REQUEST,'pages'))      ? intval($_REQUEST['pages'])    : 0);
		$params['top']       = ((x($_REQUEST,'top'))        ? intval($_REQUEST['top'])      : 0);
		$params['start']     = ((x($params,'start'))        ? intval($params['start'])      : 0);
		$params['records']   = ((x($params,'records'))      ? intval($params['records'])    : 60);
		$params['direction'] = ((x($params,'direction'))    ? dbesc($params['direction'])   : 'desc');
		$params['cat']       = ((x($_REQUEST,'cat'))        ? escape_tags($_REQUEST['cat']) : '');
		$params['compat']    = ((x($_REQUEST,'compat'))     ? intval($_REQUEST['compat'])   : 1);	


		$items = items_fetch(
    	    [
        	    'wall'       => '1',
            	'datequery'  => $params['end'],
	            'datequery2' => $params['begin'],
    	        'start'      => intval($params['start']),
        	    'records'    => intval($params['records']),
            	'direction'  => dbesc($params['direction']),
	            'pages'      => $params['pages'],
    	        'order'      => dbesc('post'),
        	    'top'        => $params['top'],
            	'cat'        => $params['cat'],
	            'compat'     => $params['compat']
    	    ], $channel, $observer_hash, CLIENT_MODE_NORMAL, \App::$module
	    );

		if(pubcrawl_is_as_request()) {

	        $x = array_merge(['@context' => [
    	        'https://www.w3.org/ns/activitystreams',
        	    [ 'me' => 'http://salmon-protocol.org/ns/magic-env' ],
				[ 'zot' => 'http://purl.org/zot/protocol' ]
            	]], asencode_item_collection($items, \App::$query_string, 'OrderedCollection'));

	        if(pubcrawl_magic_env_allowed()) {
    	        $x = pubcrawl_salmon_sign(json_encode($x),$chan);
        	    header('Content-Type: application/magic-envelope+json');
            	json_return_and_die($x);

        	}
	        else {
    	        header('Content-Type: application/activity+json');
        	    $ret = json_encode($x);
            	\HTTPSig::generate_digest($ret);
	            echo $ret;
    	        killme();
        	}
    	}

	}

}



