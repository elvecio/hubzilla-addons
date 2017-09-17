<?php
namespace Zotlabs\Module;


class Inbox extends \Zotlabs\Web\Controller {

	function post() {
		if(argc() <= 1)
			return;

		$sys_disabled = false;

		if(get_config('system','disable_discover_tab') || get_config('system','disable_activitypub_discover_tab')) {
			$sys_disabled = true;
		}

		$is_public = false;

		if(argv(1) === '[public]') {
			$is_public = true;
		}
		else {
			$channels = [ channelx_by_nick(argv(1)) ];
		}


		$data = file_get_contents('php://input');
		if(! $data)
			return;

		logger('inbox_activity: ' . jindent($data), LOGGER_DATA);

		$AS = new \Zotlabs\Lib\ActivityStreams($data);

		//		logger('debug: ' . $AS->debug());

		if(! $AS->is_valid())
			return;

		if(is_array($AS->actor) && array_key_exists('id',$AS->actor))
			as_actor_store($AS->actor['id'],$AS->actor);



		$observer_hash = $AS->actor['id'];
		if(! $observer_hash)
			return;

		if($is_public) {

			$channels = q("SELECT * from channel where channel_id in ( SELECT abook_channel from abook left join xchan on abook_xchan = xchan_hash WHERE xchan_network = 'activitypub' and xchan_hash = '%s' ) and channel_removed = 0 ",
		        dbesc($observer_hash)
			);

			if($channels === false)
				$channels = [];

			if(! $sys_disabled)
				$channels[] = get_sys_channel();

			if(in_array(ACTIVITY_PUBLIC_INBOX,$AS->recips)) {

				// look for channels with send_stream = PERMS_PUBLIC

				$r = q("select * from channel where channel_id in (select uid from pconfig where cat = 'perm_limits' and k = 'send_stream' and v = 1 ) and channel_removed = 0 ");
				if($r) {
					$channels = array_merge($channels,$r);
				}
			}

		}

		if(! $channels)
			return;

		$saved_recips = [];
		foreach( [ 'to', 'cc', 'audience' ] as $x ) {
			if(array_key_exists($x,$AS->data)) {
				$saved_recips[$x] = $AS->data[$x];
			}
		}

		foreach($channels as $channel) {

			switch($AS->type) {
				case 'Follow':
					if($AS->obj & $AS->obj['type'] === 'Person') {
						// do follow activity
						as_follow($channel,$AS);
						continue;
					}
					break;
				case 'Accept':
					if($AS->obj & $AS->obj['type'] === 'Follow') {
						// do follow activity
						as_follow($channel,$AS);
						continue;
					}
					break;

				case 'Reject':

				default:
					break;

			}


			// These activities require permissions		

			switch($AS->type) {
				case 'Create':
				case 'Update':
					as_create_action($channel,$observer_hash,$AS);
					continue;
				case 'Like':
				case 'Dislike':
					as_like_action($channel,$observer_hash,$AS);
					continue;
				case 'Undo':
					if($AS->obj & $AS->obj['type'] === 'Follow') {
						// do unfollow activity
						as_unfollow($channel,$AS);
						continue;
					}
				case 'Delete':
				case 'Add':
				case 'Remove':
				case 'Announce':


					break;
				default:
					break;

			}

		}
		http_status_exit(200,'OK');
	}

	function get() {

	}

}



