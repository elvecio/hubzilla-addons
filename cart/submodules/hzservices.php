<?php
/**
 * Name: hzservices
 * Description: Submodule for the Hubzilla Cart system to allow premium
 *              services.
 * Version: 0.2
 * MinCartVersion: 0.8
 * Author: Matthew Dent <dentm42@dm42.net>
 * MinVersion: 2.8
 */

class Cart_hzservices {

    public function __construct() {
      load_config("cart-hzservices");
    }

    static public function load (){
      Zotlabs\Extend\Hook::register('feature_settings', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::settings');
      Zotlabs\Extend\Hook::register('feature_settings_post', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::settings_post');
      Zotlabs\Extend\Hook::register('cart_myshop_menufilter', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::myshop_menuitems',1,1000);
      Zotlabs\Extend\Hook::register('cart_myshop_hzservices', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemadmin',1,1000);
      Zotlabs\Extend\Hook::register('cart_fulfill_hzservices', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::fulfill_hzservices',1,1000);
      Zotlabs\Extend\Hook::register('cart_get_catalog', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::get_catalog',1,1000);
      Zotlabs\Extend\Hook::register('cart_filter_catalog_display', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::filter_catalog_display',1,1000);
      Zotlabs\Extend\Hook::register('cart_post_hzservices_itemedit', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemedit_post',1,1000);
      Zotlabs\Extend\Hook::register('cart_post_hzservices_itemactivation', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemedit_activation_post',1,1000);
      Zotlabs\Extend\Hook::register('cart_post_hzservices_itemdeactivation', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemedit_deactivation_post',1,1000);
      cart_config_additemtype("hzservices");
      notice('Loaded submodule "hzservices"'.EOL);
    }

    static public function unload () {
      Zotlabs\Extend\Hook::unregister('feature_settings', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::settings');
      Zotlabs\Extend\Hook::unregister('feature_settings_post', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::settings_post');
      Zotlabs\Extend\Hook::unregister('cart_myshop_menufilter', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::myshop_menuitems');
      Zotlabs\Extend\Hook::unregister('cart_myshop_hzservices', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemadmin');
      Zotlabs\Extend\Hook::unregister('cart_fulfill_hzservices', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::fulfill_hzservices');
      Zotlabs\Extend\Hook::unregister('cart_get_catalog', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::get_catalog');
      Zotlabs\Extend\Hook::unregister('cart_filter_catalog_display', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::filter_catalog_display');
      Zotlabs\Extend\Hook::unregister('cart_post_hzservices_itemedit', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemedit_post');
      Zotlabs\Extend\Hook::unregister('cart_post_hzservices_itemactivation', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemedit_activation_post');
      Zotlabs\Extend\Hook::unregister('cart_post_hzservices_itemdeactivation', 'addon/cart/submodules/hzservices.php', 'Cart_hzservices::itemedit_deactivation_post');
    }

    static public function settings () {
      $id = local_channel();
      if (! $id)
        return;

      $enable_hzservices = get_pconfig ($id,'cart_hzservices','enable');
      $sc = replace_macros(get_markup_template('field_checkbox.tpl'), array(
                 '$field'	=> array('enable_cart_hzservices', t('Enable Hubzilla Services Module'),
                   (isset($enable_hzservices) ? $enable_hzservices : 0),
                   '',array(t('No'),t('Yes')))));
/*
            if (isset($enablecart)  && $enablecart == 1) {
          $testcatalog = get_pconfig ($id,'cart','enable_test_catalog');
          $sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
                 '$field'	=> array('enable_test_catalog', t('Enable Test Catalog'),
                   (isset($testcatalog) ? $testcatalog : 0),
                   '',array(t('No'),t('Yes')))));


          $manualpayments = get_pconfig ($id,'cart','enable_manual_payments');

          $sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
                 '$field'	=> array('enable_manual_payments', t('Enable Manual Payments'),
                   (isset($manualpayments) ? $manualpayments : 0),
                   '',array(t('No'),t('Yes')))));

            }
            /*
             * @todo: Set payment options order
             * @todo: Enable/Disable payment options
             * $paymentopts = Array();
             * call_hooks('cart_paymentopts',$paymentopts);
             * @todo: Configuure payment options
             */

      $s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
                 '$addon' 	=> array('cart',
                   t('Cart - Hubzilla Services Addon'), '',
                   t('Submit')),
                 '$content'	=> $sc));
            //return $s;

    }

    static public function settings_post () {
      if(!local_channel())
        return;

      $prev_enable = get_pconfig(local_channel(),'cart-hzservices','enable');
      $enable_cart_hzservices = isset($_POST['enable_cart_hzservices']) ? intval($_POST['enable_cart_hzservices']) : 0;
      set_pconfig( local_channel(), 'cart-hzservices', 'enable', $enable_cart_hzservices );
      if (!$enable_cart_hzservices || $enable_cart_hzservices != $prev_enable) {
        return;
      }
      /*
      set_pconfig( local_channel(), 'cart', 'enable_test_catalog', intval($_POST['enable_test_catalog']) );
      */

      Cart_hzservices::unload();
      Cart_hzservices::load();


    }

  static public function item_fulfill(&$orderitem) {
    // LOCK SKU from future edits.
    $skus=Cart_hzservices::get_itemlist();
    $skus[$orderitem["item"]["item_sku"]]["locked"]=true;
    Cart_hzservices::save_itemlist($skus);
  }

  static public function get_catalog(&$catalog) {
    // 		"sku-1"=>Array("item_sku"=>"sku-1","item_desc"=>"Description Item 1","item_price"=>5.55),
    $itemlist = Cart_hzservices::get_itemlist();
    foreach ($itemlist as $item) {
      $active = isset($item["item_active"]) ? $item["item_active"] : false;
      if ($active) {
        $catalog[$item["item_sku"]] = Array("item_sku"=>$item["item_sku"],
          "item_desc"=>$item["item_description"],
          "item_price"=>$item["item_price"],
          "item_type"=>"hzservices",
          "item_activate_commands"=>$item["activate_commands"],
          "item_deactivate_commands"=>$item["deactivate_commands"],
          "locked"=>false
        );

      }
    }
  }

  static public function canact($item) {
    //@TODO
    //Verify that all activate commands can be done
    return true;
  }

  static public function get_itemlist() {
    //$skus = get_pconfig(local_channel(),'cart-hzservices','skus');
    $skus = get_pconfig(\App::$profile['profile_uid'],'cart-hzservices','skus');
    $skus = $skus ? cart_maybeunjson($skus) : Array();
    return $skus;
  }

  static public function save_itemlist($itemlist) {
    $items=cart_maybejson($itemlist);
    set_pconfig(\App::$profile['profile_uid'],'cart-hzservices','skus',$items);
  }

  static public function itemadmin(&$pagecontent) {

    $is_seller = ((local_channel()) && (local_channel() == \App::$profile['profile_uid']) ? true : false);
    if (!$is_seller) {
      notice ("Access Denied.".EOL);
      return;
    }

    /*have SKU - display edit*/
    $sku = isset($_REQUEST["SKU"]) ? preg_replace("[^a-zA-Z0-9\-]",'',$_REQUEST["SKU"]) : null;
    if ($sku) {
      $pagecontent=Cart_hzservices::itemedit_form($sku);
      return;
    }

    /*no SKU - List existing SKUs and provide new SKU textbox*/
    $skus = get_pconfig(local_channel(),'cart-hzservices','skus');
    $skus = $skus ? cart_maybeunjson($skus) : Array();
    $skulist = '';
    $templatevalues=Array("security_token"=>get_form_security_token(),"skus"=>$skus);
    $skulist .= replace_macros(get_markup_template('hzservices.itemadmin.skulist.tpl','addon/cart/submodules/'),$templatevalues);

    $formelements= replace_macros(get_markup_template('field_input.tpl'), array(
                '$field'	=> array('SKU', t('New Sku'), "")));
    $formelements.=' <button class="btn btn-sm" type="submit" name="submit"><i class="fa fa-plus fa-fw" aria-hidden="true"></i></button>';
    $macrosubstitutes=Array("security_token"=>get_form_security_token(),"skulist"=>$skulist,"formelements"=>$formelements);

    $pagecontent .= replace_macros(get_markup_template('hzservices.itemadmin.tpl','addon/cart/submodules/'),$macrosubstitutes);
  }

  static public function itemedit_post() {

    if (!check_form_security_token()) {
  		notice (check_form_security_std_err_msg());
  		return;
  	}

    $is_seller = ((local_channel()) && (local_channel() == \App::$profile['profile_uid']) ? true : false);
    if (!$is_seller) {
      notice ("Access Denied.".EOL);
      return;
    }
    $skus = get_pconfig(local_channel(),'cart-hzservices','skus');
    $skus = $skus ? cart_maybeunjson($skus) : Array();

    $sku = isset($_POST["SKU"]) ? preg_replace("[^a-zA-Z0-9\-]",'',$_POST["SKU"]) : null;
    if (trim($sku)=='') {
      return;
    }

    if (!isset($skus[$sku])) {
      $item=Array();
      $item["item_sku"]=$sku;
    } else {
      $item=$skus[$sku];
    }

    if ($item["item_locked"] && isset($_POST["item_locked"])) {
      notice (t("Cannot save edits to locked item.").EOL);
      return;
    }
    $item["item_description"] = isset($_POST["item_description"]) ? $_POST["item_description"] : $item["item_description"];
    $item["item_price"] = isset($_POST["item_price"]) ? $_POST["item_price"]+0 : $item["item_price"];
    $item["item_active"] = isset($_POST["item_active"]) ? true : false;
    $item["item_locked"] = isset($_POST["item_locked"]) ? true : false;
    $skus[$sku]=$item;
    if ($item["item_active"]) {
       cart_config_additemtype('hzservices');
    }
    set_pconfig( local_channel(), 'cart-hzservices', 'skus', cart_maybejson($skus));
  }

  public static $activators = Array (
    "addconnection" => "Add Purchaser as Connection",
    "addtoprivacygroup" => "Add Purchaser to Privacy Group"
  );

  static public function get_groups ($uid,$match='') {
            $grps = array();
            $o = '';

            $r = q("SELECT * FROM groups WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
                    intval($uid)
            );
            $grps[] = array('name' => '', 'hash' => '0', 'selected' => '');
            if($r) {
                    foreach($r as $rr) {
                            $grps[] = array('name' => $rr['gname'], 'id' => $rr['hash'], 'selected' => (($match == $rr['hash']) ? 'true' : ''));
                    }

            }
            return $o;
  }

  static public function itemedit_activation_post () {
    $items=get_pconfig(local_channel(),'cart-hzservices','skus');
    $items = $items ? cart_maybeunjson($items) : Array();
    $sku = isset($_POST["SKU"]) ? preg_replace("[^a-zA-Z0-9\-]",'',$_POST["SKU"]) : null;

    $item= isset ($items[$sku]) ? $items[$sku] : null;

    if (!$item) {
      notice (t('SKU not found.')."[".$sku."]".EOL);
      return;
    }
    if ($item["item_locked"]) {
      notice ("Cannot save edits to locked item.");
      return;
    }

    if (isset($_POST["cmd"]) && !isset($_POST["del"])) {
     switch (strtolower($_POST["cmd"])) {
      case "addconnection":
        $cmdhash = md5("addconnection");
        $item["activate_commands"][$cmdhash]=Array(
          "cmdhash"=>$cmdhash,
          "cmd"=>"addconnection",
          "params"=>Array()
        );
        $cmdhash = md5("rmvconnection");
        $item["deactivate_commands"][$cmdhash]=Array(
          "cmdhash"=>$cmdhash,
          "cmd"=>"rmvconnection",
          "params"=>Array()
        );
        break;
      case "addtoprivacygroup":
        $privacygroup = isset($_POST['group-selection']) ? preg_replace("[^a-zA-Z0-9\-]",'',$_POST['group-selection']) : null;
        $cmdhash = md5("addtoprivacygroup".$privacygroup);
        $item["activate_commands"][$cmdhash]=Array(
          "cmdhash"=>$cmdhash,
          "cmd"=>"addtoprivacygroup",
          "params"=>Array("group"=>$privacygroup)
        );
        $cmdhash = md5("rmvfromprivacygroup".$privacygroup);
        $item["deactivate_commands"][$cmdhash]=Array(
          "cmdhash"=>$cmdhash,
          "cmd"=>"rmvfromprivacygroup",
          "params"=>Array("group"=>$privacygroup)
        );
        break;
      default:
        notice (t('Invalid Activation Directive.').EOL);
        return;
     }
    } else {
     if ($_POST["del"]) {
        $delcommand = isset($_POST['del']) ? preg_replace("[^a-zA-Z0-9\-]",'',$_POST['del']) : null;
        notice ("DEL: $delcommand  ".$_POST['del']);
        if ($delcommand) {
          unset($item["activate_commands"][$delcommand]);
        }
     }
    }

    $items[$sku]=$item;
    set_pconfig( local_channel(), 'cart-hzservices', 'skus', cart_maybejson($items));
  }


  static public function itemedit_deactivation_post () {
    $items=get_pconfig(local_channel(),'cart-hzservices','skus');
    $items = $items ? cart_maybeunjson($items) : Array();
    $sku = isset($_POST["SKU"]) ? preg_replace("[^a-zA-Z0-9\-]",'',$_POST["SKU"]) : null;

    $item= isset ($items[$sku]) ? $items[$sku] : null;

    if (!$item) {
      notice (t('SKU not found.').EOL);
      return;
    }
    if ($item["item_locked"]) {
      notice ("Cannot save edits to locked item.");
      return;
    }
    if (isset($_POST["cmd"]) && !isset($_POST["del"])) {
     switch (strtolower($_POST["cmd"])) {
      case "rmvconnection":
        $item["deactivate_commands"][]=Array(
          "cmdhash"=>md5("delconnection".$privacygroup),
          "cmd"=>"rmvconnection",
          "params"=>Array()
        );
        $item["deactivate_commands"]=array_unique($item["deactivate_commands"]);
        break;
      case "rmvfromprivacygroup":
        $privacygroup=$_POST["group"];

        $item["deactivate_commands"][]=Array(
          "cmdhash"=>md5("delfromprivacygroup".$privacygroup),
          "cmd"=>"rmvfromprivacygroup",
          "params"=>Array("group"=>$privacygroup)
        );
        $item["deactivate_commands"]=array_unique($item["deactivate_commands"]);
        break;
      default:
        notice (t('Invalid Deactivation Directive.').EOL);
        return;
     }
    } else {
     if ($_POST["del"]) {
        $delcommand = isset($_POST['del']) ? preg_replace("[^a-zA-Z0-9\-]",'',$_POST['del']) : null;
        notice ("DEL: $delcommand  ".$_POST['del']);
        if ($delcommand) {
          unset($item["deactivate_commands"][$delcommand]);
        }
     }
    }

    $items[$sku]=$item;
    set_pconfig( local_channel(), 'cart-hzservices', 'skus', cart_maybejson($items));

  }

  static public function fulfill_hzservices(&$calldata) {
    $orderhash=$calldata["item"]["order_hash"];
    $order=cart_loadorder($orderhash);
    $seller_hash=$order["seller_channel"];
    $seller_chaninfo = channelx_by_hash($seller_hash);
    $seller_address = $seller_chaninfo["xchan_addr"];
    $seller_uid = $seller_chaninfo["channel_id"];
    $buyer_xchan = $order["buyer_xchan"];
    $buyer_channel = xchan_fetch(Array("hash"=>$buyer_xchan));
    logger("[cart-hzservices] seller_channel: ".print_r($seller_chaninfo,true),LOGGER_DEBUG);
    logger("[cart-hzservices] buyer_channel: ".print_r($buyer_channel,true),LOGGER_DEBUG);
    $skus=get_pconfig(local_channel(),'cart-hzservices','skus');
    $skus = $skus ? cart_maybeunjson($skus) : Array();
    $sku = $calldata["item"]["item_sku"];

    $item= isset ($skus[$sku]) ? $skus[$sku] : null;

    $commandlist = $item["activate_commands"];
    foreach ($commandlist as $command) {
      logger("[cart-hzservices] Fulfill Command: ".print_r($command,true),LOGGER_DEBUG);
      switch ($command["cmd"]) {
        case "addtoprivacygroup":
          $grouphash = $command["params"]["group"];
          $grouprecord = group_rec_byhash($seller_uid,$grouphash);
          if (!$grouprecord) {
            $calldata["error"]="Unable to add buyer to group: [Group Not Found] ".$groupname;
            //return;
            //continue;
          }
          $groupname = $grouprecord["gname"];
          $r=group_add_member($seller_uid,$groupname,$buyer_xchan);
          if (!$r) {
            $calldata["error"]="Unable to add buyer to group: ".$groupname;
            notice ("Unable to add buyer to group".EOL);
            //return;
            //continue;
          }
          break;
        case "addconnection":
          notice ("Add connection".EOL);
          $buyer_url=$buyer_channel["address"];
          require_once ('include/follow.php');
          $result=new_contact($seller_chaninfo["channel_id"],$buyer_url,$seller_chaninfo,false, true);
          logger("[cart-hzservices] new_contact: new_contact(".$seller_chaninfo['channel_id'].",$buyer_url,$seller_chaninfo,false, true);",LOGGER_DEBUG);
          if (!$result["success"]){
            $calldata["error"]=$result["message"];
            notice ($result["message"].EOL);
            return;
          }
          break;
        default:
      }
    }
  }

  static public function rollback_hzservices(&$calldata) {
    //@TODO  - **********Convert to remove from add*************
    $orderhash=$calldata["item"]["order_hash"];
    $order=cart_loadorder($orderhash);

    $seller_hash=$order["seller_channel"];
    $seller_chaninfo = channelx_by_hash($seller_hash);
    $seller_address = $seller_chaninfo["xchan_address"];
    $seller_uid = $seller_chaninfo["channel_id"];
    $buyer_xchan = $order["buyer_xchan"];
    $buyer_channel = xchan_fetch(Array("hash"=>$buyer_xchan));
    logger("BUYER: ".$buyer_channel,LOGGER_DEBUG);

    $items=get_pconfig(local_channel(),'cart-hzservices','skus');
    $skus = $skus ? cart_maybeunjson($skus) : Array();
    $sku = $calldata["item"]["item_sku"];

    $item= isset ($items[$sku]) ? $items[$sku] : null;

    foreach ($item["deactivate_commands"] as $command) {
      switch ($command["cmd"]) {
        case "rmvfromprivacygroup":
          $grouphash = $command["params"]["group"];
          $grouprecord = group_rec_byhash($seller_uid,$grouphash);
          if (!$grouprecord) {
            $calldata["error"]="Unable to remove buyer from group: [Group Not Found] ".$groupname;
            return;
          }
          $r=group_add_member($seller_uid,$groupname,$buyer_xchan);
          if (!$r) {
            $calldata["error"]="Unable to remove buyer from group: ".$groupname;
            return;
          }
          break;
        case "rmvconnection":
          $buyer_url=buyer_channel["xchan_addr"];
          $result=new_contact($seller_uid,$buyer_url,$seller_chaninfo,false, true);
          if (!$result["success"]){
            $calldata["error"]=$result["message"];
            return;
          }
          break;
        default:
      }
    }
  }

  static public function groupselect($uid,$group='') {
    /* From:  include/groups.php  function mini_groups_select*/
    $grps = array();
    $o = '';

    $r = q("SELECT * FROM groups WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
        intval($uid)
    );
    $grps[] = array('name' => '', 'hash' => '0', 'selected' => '');
    if($r) {
        foreach($r as $rr) {
                $grps[] = array('name' => $rr['gname'], 'id' => $rr['hash'], 'selected' => (($group == $rr['hash']) ? 'true' : ''));
        }

    }
    logger('Cart_hzservices::groupselect: ' . print_r($grps,true), LOGGER_DATA);

    $o = replace_macros(get_markup_template('group_selection.tpl'), array(
        '$label' => t('Add to this privacy group'),
        '$groups' => $grps
    ));
    return $o;
  }

  static public function itemedit_form($sku) {

    $is_seller = ((local_channel()) && (local_channel() == \App::$profile['profile_uid']) ? true : false);
    if (!$is_seller) {
      notice ("Access Denied.".EOL);
      return;
    }
    $seller_uid = \App::$profile['profile_uid'];

    $skus = get_pconfig(local_channel(),'cart-hzservices','skus');
    $items = $skus ? cart_maybeunjson($skus) : Array();

    $item= isset ($items[$sku]) ? $items[$sku] : Array("item_sku"=>$sku,"locked"=>0,"item_description"=>"New Item","item_price"=>0,"item_active"=>false);

    $formelements["submit"]=t("Submit");
    $formelements["uri"]=strtok($_SERVER["REQUEST_URI"],'?').'?SKU='.$sku;
    // item_locked, item_desc, item_price, item_active
    // @TODO: List current rules
    // @TODO: Deal with locked items (report only, do not allow changes except for unlock)
    // @TODO: DEACTIVATION rules
    // @TODO: Delete delete rule button
    $formelements["itemdetails"].= replace_macros(get_markup_template('field_checkbox.tpl'), array(
  				     '$field'	=> array('item_locked', t('Changes Locked'),
  							 (isset($item["item_locked"]) ? $item["item_locked"] : 0),
  							 '',array(t('No'),t('Yes')))));
    $formelements["itemdetails"].= replace_macros(get_markup_template('field_checkbox.tpl'), array(
   				     '$field'	=> array('item_active', t('Item available for purchase.'),
							 (isset($item["item_active"]) ? $item["item_active"] : 0),
							 '',array(t('No'),t('Yes')))));
    $formelements["itemdetails"].= replace_macros(get_markup_template('field_input.tpl'), array(
                '$field'	=> array('item_description', t('Description'),
                (isset($item["item_description"]) ? $item["item_description"] : "New Item"))));
    $formelements["itemdetails"].= replace_macros(get_markup_template('field_input.tpl'), array(
                '$field'	=> array('item_price', t('Price'),
                (isset($item["item_price"]) ? $item["item_price"] : "0.00"))));
    $formelements["itemactivation"].= replace_macros(get_markup_template('field_radio.tpl'), array(
   				     '$field'	=> array('cmd', t('Add buyer to privacy group'),
							 "addtoprivacygroup","Add purchaser to the selected privacy group"
							 )));
    $formelements["itemactivation"].=Cart_hzservices::groupselect(App::$profile['uid']);
    $formelements["itemactivation"].= replace_macros(get_markup_template('field_radio.tpl'), array(
   				     '$field'	=> array('cmd', t('Add buyer as connection'),
							 "addconnection","Add purchaser as a channel connection"
							 )));
    if (isset($item["activate_commands"])) {
      $formelements["activate_commands"]="<UL>\n";
      foreach ($item["activate_commands"] as $command) {
        $cmdtext="";
        switch($command["cmd"]) {
          case "addtoprivacygroup":
            $cmdtext.="Add buyer to privacy group: ";
            $grouprec=group_rec_byhash($seller_uid,$command["params"]["group"]);
            $cmdtext.=$grouprec["gname"];
            $cmdtext.=' <button class="btn btn-sm" type="submit" name="del" value="'.$command["cmdhash"].'"><i class="fa fa-trash fa-fw" aria-hidden="true"></i></button>';
            break;
          case "addconnection":
            $cmdtext.="Add buyer as connection.";
            $cmdtext.=' <button class="btn btn-sm" type="submit" name="del" value="'.$command["cmdhash"].'"><i class="fa fa-trash fa-fw" aria-hidden="true"></i></button>';
        }
        $formelements["activate_commands"].="<LI>".$cmdtext."</LI>\n";
      }
      $formelements["activate_commands"].="</UL>\n";
    }
    if (isset($item["deactivate_commands"])) {
      $formelements["deactivate_commands"]="<UL>\n";
      foreach ($item["deactivate_commands"] as $command) {
        $cmdtext="";
        switch($command["cmd"]) {
          case "rmvfromprivacygroup":
            $cmdtext.="Add buyer to privacy group: ";
            $grouprec=group_rec_byhash($seller_uid,$command["params"]["group"]);
            $cmdtext.=$grouprec["gname"];
            $cmdtext.=' <button class="btn btn-sm" type="submit" name="del" value="'.$command["cmdhash"].'"><i class="fa fa-trash fa-fw" aria-hidden="true"></i></button>';
            break;
          case "rmvconnection":
            $cmdtext.="Add buyer as connection.";
            $cmdtext.=' <button class="btn btn-sm" type="submit" name="del" value="'.$command["cmdhash"].'"><i class="fa fa-trash fa-fw" aria-hidden="true"></i></button>';
        }
        $formelements["deactivate_commands"].="<LI>".$cmdtext."</LI>\n";
      }
      $formelements["deactivate_commands"].="</UL>\n";
    }
    $macrosubstitutes=Array("security_token"=>get_form_security_token(),"sku"=>$sku,"formelements"=>$formelements);

    return replace_macros(get_markup_template('hzservices.itemedit.tpl','addon/cart/submodules/'), $macrosubstitutes);
  }

  static public function myshop_menuitems (&$menu) {
    $urlroot = '/' . argv(0) . '/' . argv(1) . '/myshop';
    $menu .= "<a href='".$urlroot."/hzservices'>Add/Remove Service Items</a><BR />";
  }
}
