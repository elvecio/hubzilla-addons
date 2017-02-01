<?php


/**
 * Name: external cron
 * Description: Use external server or service to run background tasks regularly
 * Version: 1.0
 * Author: Mike Macgirvin <mike@macgirvin.com>
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 * 
 * Notes: External service needs to make a web request to http(s)://yoursite/extcron
 */

function extcron_load() {}

function extcron_unload() {}

function extcron_module() {}

function extcron_init(&$a) {
	Zotlabs\Daemon\Master::Summon(array('Cron'));
	killme();
}
