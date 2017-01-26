<?php


/**
 * Name: Git Wiki
 * Description: Git based Wiki
 * Version: 1.0
 * Author: Andrew Manning
 * Maintainer: none
 */


function gitwiki_load() {
	Zotlabs\Extend\Hook::register('module_loaded', 'addon/gitwiki/gitwiki.php', '\\Gitwiki::module_loaded');
	Zotlabs\Extend\Hook::register('load_pdl', 'addon/gitwiki/gitwiki.php', '\\Gitwiki::load_pdl');


}

function gitwiki_unload() {

	Zotlabs\Extend\Hook::unregister_by_file('addon/gitwiki/gitwiki.php');

}

class Gitwiki {
	static public function load_pdl(&$x) {
		if($x['module'] === 'gitwiki')
			$x['layout'] = '[region=aside][widget=vcard][/widget][widget=gitwiki_pages][/widget][/region]';
	}

	static public function module_loaded(&$b) {
		if($b['module'] === 'gitwiki') {
			require_once('addon/gitwiki/Mod_Gitwiki.php');
			$b['controller'] = new \Zotlabs\Module\Gitwiki();
			$b['installed'] = true;
		}
	}
}



function widget_gitwiki_pages($arr) {

	$channelname = ((array_key_exists('channel',$arr)) ? $arr['channel'] : '');
	$c = channelx_by_nick($channelname);

	$wikiname = '';
	if (array_key_exists('refresh', $arr)) {
		$not_refresh = (($arr['refresh']=== true) ? false : true);
	} else {
		$not_refresh = true;
	}
	$pages = array();
	if (! array_key_exists('resource_id', $arr)) {
		$hide = true;
	} else {
		$p = wiki_page_list($arr['resource_id']);

		if($p['pages']) {
			$pages = $p['pages'];
			$w = $p['wiki'];
			// Wiki item record is $w['wiki']
			$wikiname = $w['urlName'];
			if (!$wikiname) {
				$wikiname = '';
			}
		}
	}
	$can_create = perm_is_allowed(\App::$profile['uid'],get_observer_hash(),'write_pages');

	return replace_macros(get_markup_template('gitwiki_page_list.tpl','addon/gitwiki'), array(
			'$hide' => $hide,
			'$resource_id' => $arr['resource_id'],
			'$not_refresh' => $not_refresh,
			'$header' => t('Wiki Pages'),
			'$channel' => $channelname,
			'$wikiname' => $wikiname,
			'$pages' => $pages,
			'$canadd' => $can_create,
			'$addnew' => t('Add new page'),
			'$pageName' => array('pageName', t('Page name')),
	));
}


function widget_gitwiki_list($arr) {

	$channel = channelx_by_n(App::$profile_uid);

	$wikis = wiki_list($channel, get_observer_hash());

	if($wikis) {
		return replace_macros(get_markup_template('gitwikilist_widget.tpl','addon/gitwiki'), array(
			'$header' => t('Wiki List'),
			'$channel' => $channel['channel_address'],
			'$wikis' => $wikis['wikis']
		));
	}
	return '';
}


function widget_gitwiki_page_history($arr) {

	$pageUrlName = ((array_key_exists('pageUrlName', $arr)) ? $arr['pageUrlName'] : '');
	$resource_id = ((array_key_exists('resource_id', $arr)) ? $arr['resource_id'] : '');
	$pageHistory = wiki_page_history(array('resource_id' => $resource_id, 'pageUrlName' => $pageUrlName));

	return replace_macros(get_markup_template('gitwiki_page_history.tpl','addon/gitwiki'), array(
		'$pageHistory' => $pageHistory['history'],
		'$permsWrite' => $arr['permsWrite']
	));

}
