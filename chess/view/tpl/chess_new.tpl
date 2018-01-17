<script>
	$("#chess-verify-move").hide();
	$("#chess-revert").hide();
</script>
<div class="generic-content-wrapper-styled">
	<h1>New Game</h1>

	<hr>
	<form id="chess-new-form" action="chess/{{$channel}}/new/" method="post" class="acl-form" data-form_id="chess-new-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>

		<h4>Choose your color. White takes the first turn. Random color if left blank.</h4>
		<div class="form-check form-check">
			<label class="form-check-label">
				<input class="form-check-input" type="radio" name="color" id="id_chess_color1" value="white"> White
			</label>
		</div>
		<div class="form-check form-check">
			<label class="form-check-label">
				<input class="form-check-input" type="radio" name="color" id="id_chess_color2" value="black"> Black
			</label>
		</div>
		<br>
		<h4>Do you want to enforce legal moves? Leave unchecked to allow free play.</h4>
		<div class="form-check form-check">
			<label class="form-check-label">
				<input class="form-check-input" type="checkbox" name="playmode" id="id_chess_play_mode" value="0"> Enforce legal moves
			</label>
			
		</div>
		<br>
		<h4>Select your opponent by choosing "Custom selection" in the "Permissions" dialog.</h4>
		<div>
			<button id="dbtn-acl" class="btn btn-sm btn-success" data-toggle="modal" data-target="#aclModal" onclick="return false;" >Choose opponent</button>
		</div>
		<hr>
		<input class="btn btn-primary" id="dbtn-submit" type="submit" name="submit" value="Create Game" />
	</form>
	{{$acl}}
</div>
