<div id="cart-hzservices-edititem-wrapper">
  <h3>Edit Item: {{$sku}}</h3>
  <div id="cart-hzservices-itemdetails-wrapper"><div class="panel panel-default">
    <div class="panel-heading"><div class="panel-title">
      <h4><a data-toggle="collapse" data-parent="#cart-hzservices-itemedit-wrapper" href="#itemdetails">Item Details</a></h4>
    </div></div>
    <div id="itemdetails" class="panel-collapse collapse in"><div id="cart-hzservices-edititem-form-wrapper">
      <h1>Item Details</h1>
      <form id="cart-hzservices-edititem-form" method="post" action="{{$formelements.uri}}">
      <input type=hidden name="form_security_token" value="{{$security_token}}">
      <input type=hidden name="cart_posthook" value="hzservices_itemedit">
      <input type=hidden name="SKU" value="{{$sku}}">
      {{$formelements.itemdetails}}
      {{$formelements.item}}
      <button id="itemdetails-submit" class="btn btn-primary" type="submit" name="submit" >{{$formelements.submit}}</button>
      </form>
    </div></div>
  </div></div>
  <div id="cart-hzservices-itemactivatecommands-wrapper"><div class="panel panel-default">
    <div class="panel-heading"><div class="panel-title">
      <h4><a data-toggle="collapse" data-parent="#cart-hzservices-itemedit-wrapper" href="#activatecommands">Activation Commands</a></h4>
    </div></div>
    <div id="activatecommands" class="panel-collapse collapse"><div id="cart-hzservices-edititem-form-wrapper">
      <h1>Activation Commands</h1>
      <form id="cart-hzservices-edititem-activation-form" method="post" action="{{$formelements.uri}}">
      <input type=hidden name="form_security_token" value="{{$security_token}}">
      <input type=hidden name="cart_posthook" value="hzservices_itemactivation">
      <input type=hidden name="SKU" value="{{$sku}}">
      {{$formelements.itemactivation}}
      <button id="activatecommands-submit" class="btn btn-primary" type="submit" name="submit" >{{$formelements.submit}}</button>
      {{$formelements.activate_commands}}
      </form>
    </div></div>
  </div></div>
  <div id="cart-hzservices-itemdeactivatecommands-wrapper"><div class="panel panel-default">
    <div class="panel-heading"><div class="panel-title">
      <h4><a data-toggle="collapse" data-parent="#cart-hzservices-itemedit-wrapper" href="#deactivatecommands">Deactivation Commands</a></h4>
    </div></div>
    <div id="deactivatecommands" class="panel-collapse collapse"><div id="cart-hzservices-edititem-form-wrapper">
      <h1>Deactivation Commands</h1>
      <form id="cart-hzservices-edititem-deactivation-form" method="post" action="{{$formelements.uri}}">
      <input type=hidden name="form_security_token" value="{{$security_token}}">
      <input type=hidden name="cart_posthook" value="hzservices_itemdeactivation">
      <input type=hidden name="SKU" value="{{$sku}}">
      {{$formelements.itemdeactivation}}
      <button id="deactivatecommands-submit" class="btn btn-primary" type="submit" name="submit" >{{$formelements.submit}}</button>
      {{$formelements.deactivate_commands}}
      </form>
    </div></div>
  </div></div>
</div>
