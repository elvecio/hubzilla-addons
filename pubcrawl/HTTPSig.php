<?php


class HTTPSig {

	// See RFC5843

	static function generate_digest($body,$set = true) {
		$digest = base64_encode(hash('sha256',$body,true));

		if($set) {
			header('Digest: SHA-256=' . $digest);
		}
		return $digest;
	}

	// See draft-cavage-http-signatures-07

	static function verify($data,$key = '') {

		$body = $data;
		$headers = null;

		// decide if $data arrived via controller submission or curl
		if(is_array($data) && $data['header']) {
			if(! $data['success'])
				return false;
			$h = new \Zotlabs\Web\HTTPHeaders($data['header']);
			$headers = $h->fetcharr();
			$body = $data['body'];
		}

		else {
			$headers = [];
			$headers['(request-target)'] = 
				$_SERVER['REQUEST_METHOD'] . ' ' .
				$_SERVER['REQUEST_URI'];
			foreach($_SERVER as $k => $v) {
				if(strpos($k,'HTTP_') === 0) {
					$field = str_replace('_','-',strtolower(substr($k,5)));
					$headers[$field] = $v;
				}
			}
		}

		$sig_block = null;

		if(array_key_exists('signature',$headers)) {
			$sig_block = self::parse_sigheader($headers['signature']);
		}
		elseif(array_key_exists('authorization',$headers)) {
			$sig_block = self::parse_sigheader($headers['authorization']);
		}

		if(! $sig_block)
			return null;

		$signed_headers = $sig_block['headers'];
		if(! $signed_headers) 
			$signed_headers = [ 'date' ];

		$signed_data = '';
		foreach($signed_headers as $h) {
			if(array_key_exists($h,$headers)) {
				$signed_data .= $h . ': ' . $headers[$h] . "\n";
			}
		}
		$signed_data = rtrim($signed_data,"\n");

		$algorithm = null;
		if($sig_block['algorithm'] === 'rsa-sha256') {
			$algorithm = 'sha256';
		}

		if(! $key) {
			$key = self::get_activitypub_key($sig_block['keyId']);
		}

		if(! $key)
			return null;

		$x = rsa_verify($signed_data,$sig_block['signature'],$key,$algorithm);

		if($x === false)
			return $x;

		if(in_array('digest',$signed_headers)) {
			$digest = explode('=', $headers['digest']);
			if($digest[0] === 'SHA-256')
				$hashalg = 'sha256';

			// The explode operation will have stripped the '=' padding, so compare against unpadded base64 
			if(rtrim(base64_encode(hash($hashalg,$body,true)),'=') === $digest[1])
				return true;
			else
				return false;
		}

		return $x;

	}

	function get_activitypub_key($id) {

		$x = q("select xchan_pubkey from xchan where xchan_hash = '%s' and xchan_network = 'activitypub' ",
			dbesc($id)
		);

		if($x && $x[0]['xchan_pubkey']) {
			return ($x[0]['xchan_pubkey']);
		}
		$r = as_fetch($id);

		if($r) {
			$j = json_decode($r,true);

			if($j['id'] !== $id)
				return false; 
			if(array_key_exists('publicKey',$j) && array_key_exists('publicKeyPem',$j['publicKey'])) {
				return($j['publicKey']['publicKeyPem']);
			}
		}
		return false;
	}




	static function create_sig($request,$head,$prvkey,$keyid = 'Key',$send_headers = false,$alg = 'sha256') {

		$return_headers = [];

		if($alg === 'sha256') {
			$algorithm = 'rsa-sha256';
		}

		$x = self::sign($request,$head,$prvkey,$alg);			

		$sighead = 'Signature: keyId="' . $keyid . '",algorithm="' . $algorithm
			. '",headers="' . $x['headers'] . '",signature="' . $x['signature'] . '"';

		if($head) {
			foreach($head as $k => $v) {
				if($send_headers) {
					header($k . ': ' . $v);
				}
				else {
					$return_headers[] = $k . ': ' . $v;
				}
			}
		}
		if($send_headers) {
			header($sighead);
		}
		else {
			$return_headers[] = $sighead;
		}
		return $return_headers;
	}



	static function sign($request,$head,$prvkey,$alg = 'sha256') {

		$ret = [];

		$headers = '';
		$fields  = '';
		if($request) {
			$headers = '(request-target)' . ': ' . trim($request) . "\n";
			$fields = '(request-target)';
		}			

		if(head) {
			foreach($head as $k => $v) {
				$headers .= strtolower($k) . ': ' . trim($v) . "\n";
				if($fields)
					$fields .= ' ';
				$fields .= strtolower($k);
			}
			// strip the trailing linefeed
			$headers = rtrim($headers,"\n");
		}

		$sig = base64_encode(rsa_sign($headers,$prvkey,$alg)); 		

		$ret['headers']   = $fields;
		$ret['signature'] = $sig;
	
		return $ret;
	}

	static function parse_sigheader($header) {
		$ret = [];
		$matches = [];
		if(preg_match('/keyId="(.*?)"/ism',$header,$matches))
			$ret['keyId'] = $matches[1];
		if(preg_match('/algorithm="(.*?)"/ism',$header,$matches))
			$ret['algorithm'] = $matches[1];
		if(preg_match('/headers="(.*?)"/ism',$header,$matches))
			$ret['headers'] = explode(' ', $matches[1]);
		if(preg_match('/signature="(.*?)"/ism',$header,$matches))
			$ret['signature'] = base64_decode(preg_replace('/\s+/','',$matches[1]));

		if(($ret['signature']) && ($ret['algorithm']) && (! $ret['headers']))
			$ret['headers'] = [ 'date' ];

 		return $ret;
	}


}


