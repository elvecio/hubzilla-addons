<div style="margin-left:5em;">{{if !$item.item_fulfilled}}
<div><span style="font-weight:bold;">Item Not Fulfilled</span></div>
{{else}}
<div><span style="font-weight:bold;">Item Fulfilled</span></div>
{{/if}}
<div class="cart-myshop-itemfulfill-form">
<form method="post">
<input type=hidden name="form_security_token" value="{{$security_token}}">
<input type="hidden" name="cart_posthook" value="myshop_item_fulfill">
<input type="hidden" name="itemid" value="{{$item.id}}">
<button class="btn btn-primary" type="submit" name="cart-myshop-fullfill-item" id="newchannel-submit-button" value="{{$item.item_sku}}">Fulfill</button>
</form>
</div>
{{if $item.item_fulfilled}}<div class="warning">Warning: May result in duplicate product being sent.</div>{{/if}}
{{if $item.item_exception}}<div class="warning">Item Exception: Please review notes.</div>
<div class="cart-myshop-itemexception-form">
<form method="post">
<input type=hidden name="form_security_token" value="{{$security_token}}">
<input type="hidden" name="cart_posthook" value="myshop_clear_item_exception">
<input type="hidden" name="itemid" value="{{$item.id}}">
<input type="hidden" name="exception" value="false">
<button class="btn btn-primary" type="submit" name="cart-myshop-clear-item-exception" value="{{$item.id}}">Clear Exception</button>
</form>
</div>
{{/if}}
<div class="cart-myshop-itemnotes">
{{foreach $item.item_meta.notes as $note}}
<li>{{$note}}</li>
{{/foreach}}
</div>
<div class="cart-myshop-itemnotes-form">
<form method="post">
<input type=hidden name="form_security_token" value="{{$security_token}}">
<input type="hidden" name="cart_posthook" value="myshop_add_itemnote">
<input type="hidden" name="itemid" value="{{$item.id}}">
<textarea name="notetext" rows=3 cols=80></textarea>
<br><input type="checkbox" name="exception">EXCEPTION<br>
<button class="btn btn-primary" type="submit" name="add" id="cart-myshop-add-item-note" value="add">Add Note</button>
</form>
</div>
{{if $item.meta.notes}}
<ul>
{{foreach $item.meta.notes as $note}}
<li>{{$note}}
{{/foreach}}
</ul>
{{/if}}
</div>
