<?php

function nodeinfo_content(&$a) {

	// Nodeinfo: what a stinking piece of crap.

	// We have to lie and say we're redmatrix because the schema was defined a bit too rigidly

	$hidden = get_config('diaspora','hide_in_statistics');

	if($hidden) {
		if(argc() > 1 && argv(1) === '1.0') {
			$arr = [
				'version' => '1.0',
				'software' => [ 'name' => 'redmatrix', 'version' => '0.0' ],
				'protocols' => [ 'inbound' => array('redmatrix'), 'outbound' => array('redmatrix') ],
				'services' => [],
				'openRegistrations' => false,
				'usage' => [ 'users' => [ 'total' => 1, 'activeHalfyear' => 1, 'activeMonth' => 1 ],
					'localPosts' => 1,
					'localComments' => 1
				]
			];
		}
		if(argc() > 1 && argv(1) === '2.0') {
			$arr = [
				'version' => '2.0',
				'software' => [ 'name' => strtolower(Zotlabs\Lib\System::get_platform_name()),'version' => Zotlabs\Lib\System::get_project_version()],
				'protocols' => [ 'inbound' => array('zot'), 'outbound' => array('zot') ],
				'services' => [],
				'openRegistrations' => false,
				'usage' => [ 'users' => [ 'total' => 1, 'activeHalfyear' => 1, 'activeMonth' => 1 ],
					'localPosts' => 1,
					'localComments' => 1
				]
			];
		}

	}
	elseif(argc() > 1 && argv(1) === '1.0') {
		$arr = array(

			'version' => '1.0',
			'software' => array('name' => 'redmatrix','version' => Zotlabs\Lib\System::get_project_version()),
			'protocols' => array('inbound' => array('redmatrix'), 'outbound' => array('redmatrix')),
			'services' => array(),
			'openRegistrations' => ((get_config('system','register_policy') === REGISTER_OPEN) ? true : false),
			'usage' => array(
				'users' => array(
					'total' => intval(get_config('statistics_json','total_users')),
					'activeHalfyear' => intval(get_config('statistics_json','active_users_halfyear')),
					'activeMonth' => intval(get_config('statistics_json','active_users_monthly')),
				),
				'localPosts' => intval(get_config('statistics_json','local_posts')),
				'localComments' => intval(get_config('statistics_json','local_comments')),
			)
		);

		if(in_array('diaspora',App::$plugins)) {
			$arr['protocols']['inbound'][] = 'diaspora';
			$arr['protocols']['outbound'][] = 'diaspora';
		}

		if(in_array('gnusoc',App::$plugins)) {
			$arr['protocols']['inbound'][] = 'gnusocial';
			$arr['protocols']['outbound'][] = 'gnusocial';
		}

		if(in_array('friendica',App::$plugins)) {
			$arr['protocols']['inbound'][] = 'friendica';
			$arr['protocols']['outbound'][] = 'friendica';
		}

		$services = array();
		$iservices = array();

		if(in_array('diaspost',App::$plugins))
			$services[] = 'diaspora';
		if(in_array('dwpost',App::$plugins))
			$services[] = 'dreamwidth';
		if(in_array('statusnet',App::$plugins))
			$services[] = 'gnusocial';
		if(in_array('rtof',App::$plugins))
			$services[] = 'friendica';
		if(in_array('gpluspost',App::$plugins))
			$services[] = 'google';
		if(in_array('ijpost',App::$plugins))
			$services[] = 'insanejournal';
		if(in_array('libertree',App::$plugins))
			$services[] = 'libertree';
		if(in_array('pumpio',App::$plugins))
			$services[] = 'pumpio';
		if(in_array('redred',App::$plugins))
			$services[] = 'redmatrix';
		if(in_array('twitter',App::$plugins))
			$services[] = 'twitter';
		if(in_array('wppost',App::$plugins)) {
			$services[] = 'wordpress';
			$iservices[] = 'wordpress';
		}
		if(in_array('xmpp',App::$plugins)) {
			$services[] = 'xmpp';
			$iservices[] = 'xmpp';
		}

		if($services)
			$arr['services']['outbound'] = $services;
		if($iservices)
			$arr['services']['inbound'] = $iservices;



	}
	elseif(argc() > 1 && argv(1) === '2.0') {
		$arr = array(

			'version' => '2.0',
			'software' => array('name' => strtolower(Zotlabs\Lib\System::get_platform_name()),'version' => Zotlabs\Lib\System::get_project_version()),
			'protocols' => array('inbound' => array('zot'), 'outbound' => array('zot')),
			'services' => array(),
			'openRegistrations' => ((get_config('system','register_policy') === REGISTER_OPEN) ? true : false),
			'usage' => array(
				'users' => array(
					'total' => intval(get_config('statistics_json','total_users')),
					'activeHalfyear' => intval(get_config('statistics_json','active_users_halfyear')),
					'activeMonth' => intval(get_config('statistics_json','active_users_monthly')),
				),
				'localPosts' => intval(get_config('statistics_json','local_posts')),
				'localComments' => intval(get_config('statistics_json','local_comments')),
			)
		);

		if(in_array('diaspora',App::$plugins)) {
			$arr['protocols']['inbound'][] = 'diaspora';
			$arr['protocols']['outbound'][] = 'diaspora';
		}

		if(in_array('gnusoc',App::$plugins)) {
			$arr['protocols']['inbound'][] = 'ostatus';
			$arr['protocols']['outbound'][] = 'ostatus';
		}

		if(in_array('friendica',App::$plugins)) {
			$arr['protocols']['inbound'][] = 'friendica';
			$arr['protocols']['outbound'][] = 'friendica';
		}

		$services = [ 'atom1.0', 'rss2.0' ];
		$iservices = [ 'atom1.0', 'rss2.0' ];

		if(in_array('diaspost',App::$plugins))
			$services[] = 'diaspora';
		if(in_array('dwpost',App::$plugins))
			$services[] = 'dreamwidth';
		if(in_array('statusnet',App::$plugins))
			$services[] = 'gnusocial';
		if(in_array('rtof',App::$plugins))
			$services[] = 'friendica';
		if(in_array('gpluspost',App::$plugins))
			$services[] = 'google';
		if(in_array('ijpost',App::$plugins))
			$services[] = 'insanejournal';
		if(in_array('libertree',App::$plugins))
			$services[] = 'libertree';
		if(in_array('pumpio',App::$plugins))
			$services[] = 'pumpio';
		if(in_array('redred',App::$plugins))
			$services[] = 'redmatrix';
		if(in_array('twitter',App::$plugins))
			$services[] = 'twitter';
		if(in_array('wppost',App::$plugins)) {
			$services[] = 'wordpress';
			$iservices[] = 'wordpress';
		}
		if(in_array('xmpp',App::$plugins)) {
			$services[] = 'xmpp';
			$iservices[] = 'xmpp';
		}

		if($services)
			$arr['services']['outbound'] = $services;
		if($iservices)
			$arr['services']['inbound'] = $iservices;



	}


	header('Content-type: application/json');
	echo json_encode($arr);
	killme();


}

