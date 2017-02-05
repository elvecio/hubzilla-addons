<?php

/**
 * Name: Firefox 
 * Description: Provide Firefox Social provider interface
 * Version: 1.0
 * Maintainer: none
 */


function firefox_load() {
	Zotlabs\Extend\Hook::register('channel_settings','addon/firefox/firefox.php','firefox_channel_settings');
}

function firefox_unload() {
	Zotlabs\Extend\Hook::unregister('channel_settings','addon/firefox/firefox.php','firefox_channel_settings');
}


function firefox_channel_settings(&$x) {

	$x['misc'] .= '<div class="ffsapilink"><a type="button" class="btn btn-success" href="/firefox" target="_blank" >' .
		t('Install Firefox Sharing Tools') . '</a></div>' . "\r\n";

}


function firefox_module() {}

function firefox_content($a) {

	$baseurl = z_root();
	$name = get_config('system','sitename');
	$description = t('Share content from Firefox to $Projectname');
	$author = 'Mike Macgirvin';
	$homepage = 'http://hubzilla.org';
	$activate = t('Install Firefox Sharing Tools to this web browser');
	
	$s = <<< EOT
	
	<script>
	
	var baseurl = '$baseurl';
	
	var data = {
	  "origin": baseurl,
	  // currently required
	  "name": '$name',
	  "iconURL": baseurl+"/images/hz-16.png",
	  "icon32URL": baseurl+"/images/hz-32.png",
	  "icon64URL": baseurl+"/images/hz-64.png",
	
	  // at least one of these must be defined
	  // "workerURL": baseurl+"/worker.js",
	  // "sidebarURL": baseurl+"/sidebar.htm",
	  "shareURL": baseurl+"/rpost?f=&url=%{url}",
	
	  // status buttons are scheduled for Firefox 26 or 27
	  //"statusURL": baseurl+"/statusPanel.html",
	
	  // social bookmarks are available in Firefox 26
	  "markURL": baseurl+"/rbmark?f=&url=%{url}&title=%{title}",
	  // icons should be 32x32 pixels
	  // "markedIcon": baseurl+"/images/checkbox-checked-32.png",
	  // "unmarkedIcon": baseurl+"/images/checkbox-unchecked-32.png",
	  "unmarkedIcon": baseurl+"/images/hz-bookmark-32.png",
	
	  // should be available for display purposes
	  "description": "$description",
	  "author": "$author",
	  "homepageURL": "$homepage",
	
	  // optional
	  "version": "1.0"
	}
	
	function activate(node) {
	  var event = new CustomEvent("ActivateSocialFeature");
	  var jdata = JSON.stringify(data);
	  node.setAttribute("data-service", JSON.stringify(data));
	  node.dispatchEvent(event);
	}
	</script>
	
	<button onclick="activate(this)" title="$activate" class="btn btn-primary">$activate</button>
	
EOT;
	
	return $s;
	
}