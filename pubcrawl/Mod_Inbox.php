<?php
namespace Zotlabs\Module;


class Inbox extends \Zotlabs\Web\Controller {

	function post() {
		if(argc() <= 1)
			return;

		$is_public = false;

		if(argv(1) === '[public]') {
			$channel = get_sys_channel();
			$is_public = true;
		}
		else {
			$channel = channelx_by_nick(argv(1));
		}

		if(! $channel)
			return;

		$data = file_get_contents('php:input'));
		if(! $data)
			return;

		$AS = new ActivityStreams($data);

		if(! $AS->is_valid())
			return;

		$saved_recips = [];
		foreach( [ 'to', 'cc', 'audience' ] as $x ) {
			if(array_key_exists($x,$AS->data) {
				$saved_recips[$x] = $AS->data[$x];
			}
		}

		switch($AS->type) {
			case 'Follow':
				if($AP->obj & $AP->obj['type'] === 'Person') {
					// do follow activity

				}
				break;
			default:
				break;

		}

		// Look up actor
		// These activities require permissions		

		switch($AS->type) {
			case 'Create':
			case 'Update':
			case 'Delete':
			case 'Follow':
			case 'Add':
			case 'Remove':
			case 'Like':
			case 'Announce':
			case 'Undo':
				break;
			default:
				break;

		}







	}

	function get() {

	}

}



