<?php
namespace Zotlabs\Module;


class Activity extends \Zotlabs\Web\Controller {

	function init() {

		if(pubcrawl_is_as_request()) {
			$item_id = argv(1);
			if(! $item_id)
				return;

			$item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 
				and item.item_delayed = 0 and item.item_blocked = 0 ";

			$sql_extra = item_permissions_sql(0);

			$r = q("select * from item where mid like '%s' $item_normal $sql_extra limit 1",
				dbesc($item_id . '%')
			);
			if(! $r) {
				$r = q("select * from item where mid like '%s' $item_normal limit 1",
					dbesc($item_id . '%')
				);
				if($r) {
					http_status_exit(403, 'Forbidden');
				}
				http_status_exit(404, 'Not found');
			}

			xchan_query($r,true);
			$items = fetch_post_tags($r,true);

			$x = array_merge(['@context' => [
				'https://www.w3.org/ns/activitystreams',
				[ 'me' => 'http://salmon-protocol.org/ns/magic-env' ],
				[ 'zot' => 'http://purl.org/zot/protocol' ]
				]], asencode_activity($items[0]));

			header('Content-Type: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
			json_return_and_die($x);

		}

	}

}