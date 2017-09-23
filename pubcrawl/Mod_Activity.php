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

			$chan = channelx_by_n($items[0]['uid']);

			$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
				]], asencode_activity($items[0]));


			$headers = [];
			$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;

			$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
			$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
			$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
			$headers['Digest'] = 'SHA-256=' . $hash;  
			\Zotlabs\Web\HTTPSig::create_sig('',$headers,$chan['channel_prvkey'],z_root() . '/channel/' . $chan['channel_address'],true);
			echo $ret;
			killme();

		}

	}

}