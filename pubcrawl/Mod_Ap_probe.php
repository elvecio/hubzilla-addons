<?php
namespace Zotlabs\Module;

require_once('include/zot.php');
require_once('library/jsonld/jsonld.php');

class Ap_probe extends \Zotlabs\Web\Controller {

	function get() {
	
		$o .= '<h3>ActivityPub Probe Diagnostic</h3>';
	
		$o .= '<form action="ap_probe" method="get">';
		$o .= 'Lookup URI: <input type="text" style="width: 250px;" name="addr" value="' . $_GET['addr'] .'" /><br>';
		$o .= '<input type="submit" name="submit" value="Submit" /></form>'; 
	
		$o .= '<br /><br />';
	
		if(x($_GET,'addr')) {
			$addr = $_GET['addr'];

			$headers = 'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/ld+json';


			$redirects = 0;
		    $x = z_fetch_url($addr,true,$redirects, [ 'headers' => [ $headers ]]);
	    	if($x['success'])
				$o .= '<pre>' . $x['header'] . '</pre>' . EOL;


				$o .= '<pre>' . $x['body'] . '</pre>' . EOL;
				
				$o .= 'verify returns: ' . str_replace("\n",EOL,print_r(\Zotlabs\Web\HTTPSig::verify($x),true)) . EOL;

				if($x['body'] && json_decode($x['body'])) {
					$normalized1 = jsonld_normalize(json_decode($x['body']),[ 'algorithm' => 'URDNA2015', 'format' => 'application/nquads' ]);
					$o .= str_replace("\n",EOL,htmlentities(var_export($normalized1,true))); 

	//				$o .= '<pre>' . json_encode($normalized1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
				}

				$o .= '<pre>' . str_replace(['\\n','\\'],["\n",''],jindent($x['body'])) . '</pre>';

				$AP = new \Zotlabs\Lib\ActivityStreams($x['body']);	
				$o .= '<pre>' . $AP->debug() . '</pre>';


		}
		return $o;
	}
	
}
