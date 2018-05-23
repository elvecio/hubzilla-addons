<?php

function cart_myshop_load(){
	Zotlabs\Extend\Hook::register('cart_main_myshop','addon/cart/myshop.php','cart_myshop_main',1,99);
	Zotlabs\Extend\Hook::register('cart_aside_filter','addon/cart/myshop.php','cart_myshop_aside',1,99);
	Zotlabs\Extend\Hook::register('cart_myshop_order','addon/cart/myshop.php','cart_myshop_order',1,99);
	Zotlabs\Extend\Hook::register('cart_post_myshop_item_fulfill','addon/cart/myshop.php','cart_myshop_item_fulfill',1,99);
	Zotlabs\Extend\Hook::register('cart_post_myshop_clear_item_exception','addon/cart/myshop.php','cart_myshop_clear_item_exception',1,99);
	Zotlabs\Extend\Hook::register('cart_post_myshop_add_itemnote','addon/cart/myshop.php','cart_myshop_add_itemnote',1,99);
	Zotlabs\Extend\Hook::register('cart_post_myshop_add_ordernote','addon/cart/myshop.php','cart_myshop_add_ordernote',1,99);
	Zotlabs\Extend\Hook::register('cart_myshop_allorders','addon/cart/myshop.php','cart_myshop_allorders',1,99);
	Zotlabs\Extend\Hook::register('cart_myshop_openorders','addon/cart/myshop.php','cart_myshop_openorders',1,99);
	Zotlabs\Extend\Hook::register('cart_myshop_closedorders','addon/cart/myshop.php','cart_myshop_closedorders',1,99);
	Zotlabs\Extend\Hook::register('cart_post_myshop_order_markpaid','addon/cart/myshop.php','cart_myshop_order_markpaid',1,99);
}

function cart_myshop_unload(){

	Zotlabs\Extend\Hook::unregister('cart_main_myshop','addon/cart/myshop.php','cart_myshop_main');
	Zotlabs\Extend\Hook::unregister('cart_aside_filter','addon/cart/myshop.php','cart_myshop_aside');
	Zotlabs\Extend\Hook::unregister('cart_myshop_order','addon/cart/myshop.php','cart_myshop_order');
	Zotlabs\Extend\Hook::unregister('cart_post_myshop_item_fulfill','addon/cart/myshop.php','cart_myshop_item_fulfill');
	Zotlabs\Extend\Hook::unregister('cart_post_myshop_clear_item_exception','addon/cart/myshop.php','cart_myshop_clear_item_exception');
	Zotlabs\Extend\Hook::unregister('cart_post_myshop_add_itemnote','addon/cart/myshop.php','cart_myshop_add_itemnote');
	Zotlabs\Extend\Hook::unregister('cart_post_myshop_add_ordernote','addon/cart/myshop.php','cart_myshop_add_ordernote');
	Zotlabs\Extend\Hook::unregister('cart_myshop_allorders','addon/cart/myshop.php','cart_myshop_allorders');
	Zotlabs\Extend\Hook::unregister('cart_myshop_openorders','addon/cart/myshop.php','cart_myshop_openorders');
	Zotlabs\Extend\Hook::unregister('cart_myshop_closedorders','addon/cart/myshop.php','cart_myshop_closedorders');
	Zotlabs\Extend\Hook::unregister('cart_post_myshop_order_markpaid','addon/cart/myshop.php','cart_myshop_order_markpaid');
}

/* FUTURE/TODO

function cart_myshop_searchparams ($search) {

  $keys = Array (
		"order_hash"=>Array("key"=>"order_hash","cast"=>"'%s'","escfunc"=>"dbesc"),

		"item_desc"=>Array("key"=>"item_desc","cast"=>"'%s'","escfunc"=>"dbesc"),
		"item_type"=>Array("key"=>"item_type","cast"=>"'%s'","escfunc"=>"dbesc"),
		"item_sku"=>Array("key"=>"item_sku","cast"=>"'%s'","escfunc"=>"dbesc"),
		"item_qty"=>Array("key"=>"item_qty","cast"=>"%d","escfunc"=>"intval"),
		"item_price"=>Array("key"=>"item_price","cast"=>"%f","escfunc"=>"floatval"),
		"item_tax_rate"=>Array("key"=>"item_tax_rate","cast"=>"%f","escfunc"=>"floatval"),
		"item_meta"=>Array("key"=>"item_meta","cast"=>"'%s'","escfunc"=>"dbesc"),
		);

	$colnames = '';
	$valuecasts = '';
	$params = Array();
	$count=0;
	foreach ($keys as $key=>$cast) {
		if (isset($search[$key])) {
			$colnames .= ($count > 0) ? "," : '';
			$colnames .= $cast["key"];
			$valuecasts .= ($count > 0) ? "," : '';
			$valuecasts .= $cast["cast"];
                        $escfunc = $cast["escfunc"];
                        logger ("[cart] escfunc = ".$escfunc);
			$params[] = $escfunc($item[$key]);
			$count++;
		}
	}
}
*/

function cart_myshop_main (&$pagecontent) {

	$sellernick = argv(1);

        if (argv(2)!='myshop') {
		notice( t('Access Denied.') . EOL);
		goaway('/' . argv(0) . '/' . argv(1));
        }

	$seller = channelx_by_nick($sellernick);

	if(! $seller) {
				notice( t('Invalid channel') . EOL);
				goaway('/' . argv(0));
	}

	$observer_hash = get_observer_hash();

	$is_seller = ((local_channel()) && (local_channel() == \App::$profile['profile_uid']) ? true : false);


	$urlroot = '/' . argv(0) . '/' . argv(1) . '/myshop';
        $rendered = '';

        if (argc() > 3) {
           $hookname=preg_replace('/[^a-zA-Z0-9\_]/','',argv(3));
  	   call_hooks('cart_myshop_'.$hookname,$rendered);
        }

	if ($rendered == '') {
	   $rendered = cart_myshop_menu();
	}
        $templatevalues=Array('content'=>$rendered);
	$template = get_markup_template('myshop.tpl','addon/cart/');
	$pagecontent = replace_macros($template, $templatevalues);

	return ($pagecontent);
}

function cart_myshop_menu() {
	$urlroot = '/' . argv(0) . '/' . argv(1) . '/myshop';
	$openorders=cart_myshop_get_openorders(null,10000,0);
	$allorders=cart_myshop_get_allorders(null,10000,0);
	$closedorders=cart_myshop_get_closedorders(null,10000,0);
        $rendered = '';
	$rendered .= "<a href='".$urlroot."'>Home</a><BR />";
	//$rendered .= "<a href='".$urlroot."/openorders'>Open Orders (".count($openorders).")</a><BR />";
	//$rendered .= "<a href='".$urlroot."/closedorders'>Closed Orders (".count($closedorders).")</a><BR />";
	$rendered .= "<a href='".$urlroot."/allorders'>All Orders (".count($allorders).")</a><BR />";
	call_hooks('cart_myshop_menufilter',$rendered);
        return $rendered;
}

function cart_myshop_openorders (&$pagecontent) {
  $pagecontent.="<h1>OPEN ORDERS</h1>";
}

function cart_myshop_closedorders (&$pagecontent) {
	$pagecontent.="<h1>CLOSED ORDERS</h1>";
}

function cart_myshop_allorders (&$pagecontent) {
/*
  myshop_orderlist.tpl variables
    $orders - results of cart_myshop_get_(.*)orders
*/
  $templatevalues=Array();
  $templatevalues["urlprefix"]="/".argv(0)."/".argv(1)."/myshop/order";
  $templatevalues["orders"] = cart_myshop_get_allorders(null,100000,0);
  $templatevalues["debug"] = print_r($templatevalues,true);
  $templateinfo = array('name'=>'myshop_orderlist.tpl','path'=>'addon/cart/');
  call_hooks('cart_filter_myshop_orderlist_template',$templateinfo);
  $template = get_markup_template($templateinfo['name'],$templateinfo['path']);
  $rendered = replace_macros($template, $templatevalues);
  $pagecontent = $rendered;
}

function cart_myshop_order(&$pagecontent) {
  $orderhash = argv(4);
  $orderhash = preg_replace('/[^a-z0-9]/','',$orderhash);
	$order = cart_loadorder($orderhash);
  $channel=\App::get_channel();
	$channel_hash=$channel["channel_hash"];
	if (!$order || $order["seller_channel"]!=$channel_hash) {
		return "<h1>".t("Order Not Found")."</h1>";
	}
  $permission=Array();
	$permissions['manualfilfillment_permitted']=true;
	call_hooks('cart_myshop_order_permissions',$permissions);
	$templatevalues=$order;
	$templatevalues['permissions']=$permissions;
	$templatevalues["security_token"]=get_form_security_token();

	$templateinfo = array('name'=>'myshop_order.tpl','path'=>'addon/cart/');
	call_hooks('cart_filter_myshop_order',$templateinfo);
	$template = get_markup_template($templateinfo['name'],$templateinfo['path']);
	//HOOK: cart_post_myshop_order
	$rendered = replace_macros($template, $templatevalues);
	$pagecontent = $rendered;
}
function cart_myshop_order_markpaid () {
	if (!check_form_security_token()) {
		notice (check_form_security_std_err_msg());
		return;
	}
	$itemid = preg_replace('/[^0-9]/','',$_POST["itemid"]);
	$orderhash = argv(4);
	$orderhash = preg_replace('/[^a-z0-9]/','',$orderhash);
	$order = cart_loadorder($orderhash);
	$channel=\App::get_channel();
	$channel_hash=$channel["channel_hash"];
	if (!$order || $order["seller_channel"]!=$channel_hash) {
		notice (t("Access Denied"));
		return;
	}
        $hookinfo=Array();
        $hookinfo["order"]=$order;
	cart_do_orderpaid ($hookinfo);
	if (isset($hookinfo["error"])) {
	        $order_meta=cart_getorder_meta ($orderhash);
	        $order_meta["notes"][]=date("Y-m-d h:i:sa T - ")."Error marking order paid: ".$hookinfo["error"];
	        cart_updateorder_meta($order_meta,$orderhash);
		notice (t($hookinfo["error"]));
		return;
	}

	$order_meta=cart_getorder_meta ($orderhash);
	$order_meta["notes"][]=date("Y-m-d h:i:sa T - ")."Marked Paid";
	cart_updateorder_meta($order_meta,$orderhash);

}

function cart_myshop_item_fulfill () {
	if (!check_form_security_token()) {
		notice (check_form_security_std_err_msg());
		return;
	}
	$itemid = preg_replace('/[^0-9]/','',$_POST["itemid"]);
	$orderhash = argv(4);
	$orderhash = preg_replace('/[^a-z0-9]/','',$orderhash);
	$order = cart_loadorder($orderhash);
	$channel=\App::get_channel();
	$channel_hash=$channel["channel_hash"];
	if (!$order || $order["seller_channel"]!=$channel_hash) {
		notice (t("Access Denied"));
		return;
	}
	foreach ($order["items"] as $item) {
		if ($item["id"]==$itemid) {
			$itemtofulfill=$itemid;
		}
	}
	if (!$itemtofulfill) {
		notice (t("Invalid Item"));
		return;
	}
	$itemtofulfill=Array('order_hash'=>$orderhash,'id'=>$itemid);


	cart_do_fulfillitem ($itemtofulfill);
	if (isset($itemtofulfill["error"])) {
		notice (t($itemtofulfill["error"]));
		return;
	}

	$item_meta=cart_getitem_meta ($itemid,$orderhash);
	$item_meta["notes"][]=date("Y-m-d h:i:sa T - ")."Manual Fulfillment";
	cart_updateitem_meta($itemid,$item_meta,$orderhash);

}

function cart_myshop_clear_item_exception () {
	if (!check_form_security_token()) {
		notice (check_form_security_std_err_msg());
		return;
	}

	$itemid = preg_replace('/[^0-9]/','',$_POST["itemid"]);
	$orderhash = argv(4);
	$orderhash = preg_replace('/[^a-z0-9]/','',$orderhash);
	$order = cart_loadorder($orderhash);
	$channel=\App::get_channel();
	$channel_hash=$channel["channel_hash"];
	if (!$order || $order["seller_channel"]!=$channel_hash) {
		notice (t("Access Denied"));
		return;
	}
	$itemtoclear=null;
	foreach ($order["items"] as $item) {
		if ($item["id"]==$itemid) {
			$itemtoclear=$itemid;
		}
	}
	if (!$itemtoclear) {
		notice (t("Invalid Item"));
		return;
	}

	$r=q("update cart_orderitems set item_exception = false where order_hash = '%s' and id = %d",
			dbesc($orderhash),intval($itemid));

	$item_meta=cart_getitem_meta ($itemid,$orderhash);
	$item_meta["notes"][]=date("Y-m-d h:i:sa T - ")."Exception Cleared";
	cart_updateitem_meta($itemid,$item_meta,$orderhash);
	return;
}

function cart_myshop_add_ordernote () {
	if (!check_form_security_token()) {
		notice (check_form_security_std_err_msg());
		return;
	}

	$orderhash = argv(4);
	$orderhash = preg_replace('/[^a-z0-9]/','',$orderhash);
	$order = cart_loadorder($orderhash);
	$channel=\App::get_channel();
	$channel_hash=$channel["channel_hash"];
	if (!$order || $order["seller_channel"]!=$channel_hash) {
		notice (t("Access Denied"));
		return;
	}

        $order_meta=cart_getorder_meta ($orderhash);
	$order_meta["notes"][]=date("Y-m-d h:i:sa T - ").filter_var($_POST["notetext"], FILTER_SANITIZE_STRING);
	cart_updateorder_meta($order_meta,$orderhash);
	return;
}

function cart_myshop_add_itemnote () {
	if (!check_form_security_token()) {
		notice (check_form_security_std_err_msg());
		return;
	}

	$itemid = preg_replace('/[^0-9]/','',$_POST["itemid"]);
	$orderhash = argv(4);
	$orderhash = preg_replace('/[^a-z0-9]/','',$orderhash);
	$order = cart_loadorder($orderhash);
	$channel=\App::get_channel();
	$channel_hash=$channel["channel_hash"];
	if (!$order || $order["seller_channel"]!=$channel_hash) {
		notice (t("Access Denied"));
		return;
	}
	$itemtonote=null;
	foreach ($order["items"] as $item) {
		if ($item["id"]==$itemid) {
			$itemtonote=$itemid;
		}
	}
	if (!$itemtonote) {
		notice (t("Invalid Item"));
		return;
	}

  $item_meta=cart_getitem_meta ($itemid,$orderhash);
	$item_meta["notes"][]=date("Y-m-d h:i:sa T - ").filter_var($_POST["notetext"], FILTER_SANITIZE_STRING);
  if (isset($_POST["exception"])) {
  	$r=q("update cart_orderitems set item_exception = true where order_hash = '%s' and id = %d",
			dbesc($orderhash),intval($itemid));
		$item_meta["notes"][]=date("Y-m-d h:i:sa T - ")."Exception Set";
	}
	cart_updateitem_meta($itemid,$item_meta,$orderhash);
	return;
}


function cart_myshop_aside (&$aside) {
	$is_seller = ((local_channel()) && (local_channel() == \App::$profile['profile_uid']) ? true : false);

	    // Determine if the observer is the channel owner so the ACL dialog can be populated
        if (!$is_seller) {
			return $aside;
	}

	$rendered = '';
	$urlroot = '/' . argv(0) . '/' . argv(1) . '/myshop';
	$rendered .= "<li><a href='".$urlroot."'>Home</a></li>";
        //$openorders=cart_myshop_get_openorders(null,10000,0);
	$allorders=cart_myshop_get_allorders(null,10000,0);
	//$closedorders=cart_myshop_get_closedorders(null,10000,0);
        //$rendered .= "<li><a href='".$urlroot."/openorders'>Open Orders (".count($openorders).")</a></li>";
	//$rendered .= "<li><a href='".$urlroot."/closedorders'>Closed Orders (".count($closedorders).")</a></li>";
	$rendered .= "<li><a href='".$urlroot."/allorders'>All Orders (".count($allorders).")</a></li>";
	$templatevalues["content"]=$rendered;
	$template = get_markup_template('myshop_aside.tpl','addon/cart/');
	$rendered = replace_macros($template, $templatevalues);
	$aside = $rendered . $aside;

	return ($aside);
}

function cart_myshop_get_allorders ($search=null,$limit=100000,$offset=0) {
/**
  * search = Array of search terms:  //NOT YET IMPLEMENTED
  *   [""]
***/
  $seller_hash=get_observer_hash();
  $r=q("select distinct cart_orders.order_hash from cart_orders,cart_orderitems
        where cart_orders.order_hash = cart_orderitems.order_hash and
        seller_channel = '%s'
        limit %d offset %d",
      dbesc($seller_hash),
      intval($limit), intval($offset));
  $orders=Array();
  if (!$r) {return Array();}
  foreach ($r as $order) {
    $orders[] = cart_loadorder($order["order_hash"]);
  }
  return $orders;
}

function cart_myshop_get_openorders ($search=null,$limit=100,$offset=1) {
/**
  * search = Array of search terms:
  *   [""]
***/
  $seller_hash=get_observer_hash();

/*
  $r=q("select distinct cart_orders.order_hash from cart_orders,cart_orderitems
        where cart_orders.order_hash = cart_orderitems.order_hash and
        seller_channel = '%s' and cart_orderitems.item_fulfilled is NULL
        and cart_orderitems.item_confirmed is not NULL
        limit %d offset %d",
      dbesc($seller_hash),
      intval($limit), intval($offset));
*/

  $r=q("select distinct cart_orders.order_hash from cart_orders,cart_orderitems
        where cart_orders.order_hash = cart_orderitems.order_hash and
        seller_channel = '%s' and
        cart_orderitems.item_fulfilled != true and
        cart_orderitems.confirmed = true
        limit %d offset %d",
      dbesc($seller_hash),
      intval($limit), intval($offset));
  if (!$r) {return Array();}

  $orders=Array();
  foreach ($r as $order) {
    $orders[] = cart_loadorder($order["order_hash"]);
  }
  return $orders;
}

function cart_myshop_get_closedorders ($search=null,$limit=100,$offset=1) {

  $seller_hash=get_observer_hash();
  $r=q("select distinct order_hash from cart_orders where
        seller_channel = '%s' and
        cart_orders.order_hash not in (select order_hash from cart_orderitems
        where item_fulfilled is not NULL)
        limit %d offset %d",
      dbesc($seller_hash),
      intval($limit), intval($offset));

  if (!$r) {return Array();}

  foreach ($r as $order) {
    $orders[$order["order_hash"]] = cart_loadorder($order["order_hash"]);
  }
  return $orders;
}
