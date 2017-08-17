
<div class="widget" id="chess-verify-move">
	<h3>Verify Move</h3>
	<p>You can continue to move pieces if desired. When your move is complete, press the Accept button.</p>
	<ul class="nav nav-pills nav-stacked">
		<li><a class="btn btn-success" href="#" onclick="chess_accept_move(); return false;">Accept</a></li>
		<li><a class="btn btn-warning" href="#" onclick="chess_undo_move(); return false;">Undo</a></li>
	</ul>
	<hr>
	<p>Ending the game will prevent future changes to the board but will not delete the game.</p>
	<ul class="nav nav-pills nav-stacked">
		<li><a class="btn btn-danger" href="#" onclick="chess_end_game(); return false;">Accept Move and End Game</a></li>
	</ul>
</div>

<div class="widget">

	<div class="panel-group" id="chess-controls" role="tablist" aria-multiselectable="true">
		<div class="panel" id="chess-controls-game">
			<div class="section-subtitle-wrapper" role="tab" id="chess-controls-game"  data-toggle="collapse" data-parent="#chess-controls-game" href="#chess-controls-game-collapse" aria-expanded="true" aria-controls="chess-controls-game-collapse">
				<h3>Chess</h3>
			</div> <!-- section-subtitle-wrapper -->
			<div id="chess-controls-game-collapse" class="panel-collapse collapse show" role="tabpanel" aria-labelledby="chess-controls-game">
				{{if $owner}}
				<ul class="nav nav-pills nav-stacked">
					<li><a href='/chess/{{$channel}}/new/'>New</a></li>
				</ul>
				<hr>
				{{/if}}
				{{if $gameinfo.players}}
				<div  id="chess-game-info" style="padding-left: 20px; padding-top: 10px;">
					<h3 id="chess-game-info">Game Info</h3>
					<p><a href="{{$gameinfo.plink}}" target="_blank" title="Open conversation">{{$gameinfo.players.0}} vs. {{$gameinfo.players.1}}</a></p>
					<p id="chess-resume-game"><a href="#" onclick="chess_resume_game(); return false;" title="Resume game">Resume game</a></p>
				</div>
				{{/if}}
			</div> <!-- chess-controls-game-collapse -->
			<br>
		</div> <!-- panel -->
		{{if $games}}
		<div class="panel" id="chess-controls-games">
			<div class="section-subtitle-wrapper" role="tab" id="chess-controls-games"  data-toggle="collapse" data-parent="#chess-controls-games" href="#chess-controls-games-collapse" aria-expanded="true" aria-controls="chess-controls-games-collapse">
				<h3>Games</h3>
			</div> <!-- section-subtitle-wrapper -->
			<div id="chess-controls-games-collapse" class="panel-collapse collapse show" role="tabpanel" aria-labelledby="chess-controls-games">
				<h4 style="padding-left: 10px;">Active Games</h4>
				{{if $games.owner_active}}
				<ul class="nav nav-pills nav-stacked" id="chess-games-owner">
					{{foreach $games.owner_active as $game}}
					<li class="dropdown" id="chess-game-{{$game.game_id}}">
						<a class="dropdown-toggle" data-toggle="dropdown" href="#">
							{{if $game.active}}
							<img src="/addon/chess/view/img/chesspieces/wikipedia/wN.png" height="19" style="padding-right: 5px;">
							{{/if}}
							{{$game.date}} : <b>{{$game.opponent}}</b><b class="icon-caret-down pull-right"></b>
						</a>
						<ul class="dropdown-menu  pull-right">
							<li><a href="/chess/{{$channel}}/{{$game.game_id}}" title="Play game">Play game</a></li>
							<li><a href="{{$game.plink}}" target="_blank" title="View conversation">View conversation</a></li>
							<li class="divider"></li>
							<li><a href="#" onclick="chess_delete_game('{{$game.game_id}}'); return false;" title="Delete game {{$game.game_id}}">Delete game</a></li>
						</ul>
					</li>
					{{/foreach}}
				</ul>
				{{/if}}
				{{if $games.player_active}}
				<ul class="nav nav-pills nav-stacked" id="chess-games-owner">
					{{foreach $games.player_active as $game}}
					<li class="dropdown" id="chess-game-{{$game.game_id}}">
						<a class="dropdown-toggle" data-toggle="dropdown" href="#">
							{{if $game.active}}
							<img src="/addon/chess/view/img/chesspieces/wikipedia/wN.png" height="19" style="padding-right: 5px;">
							{{/if}}
							{{$game.date}} : <b>{{$game.opponent}}</b><b class="icon-caret-down pull-right"></b>
						</a>
						<ul class="dropdown-menu  pull-right">
							<li><a href="/chess/{{$channel}}/{{$game.game_id}}" title="Play game">Play game</a></li>
							<li><a href="{{$game.plink}}" target="_blank" title="View conversation">View conversation</a></li>
						</ul>
					</li>
					{{/foreach}}
				</ul>
				{{/if}}
				<hr>
				<h4 style="padding-left: 10px;">Completed Games</h4>
				{{if $games.owner_ended}}
				<ul class="nav nav-pills nav-stacked" id="chess-games-owner">
					{{foreach $games.owner_ended as $game}}
					<li class="dropdown" id="chess-game-{{$game.game_id}}">
						<a class="dropdown-toggle" data-toggle="dropdown" href="#">{{$game.date}} : <b>{{$game.opponent}}</b><b class="icon-caret-down pull-right"></b></a>
						<ul class="dropdown-menu  pull-right">
							<li><a href="/chess/{{$channel}}/{{$game.game_id}}" title="Play game">Play game</a></li>
							<li><a href="{{$game.plink}}" target="_blank" title="View conversation">View conversation</a></li>
							<li class="divider"></li>
							<li><a href="#" onclick="chess_delete_game('{{$game.game_id}}'); return false;" title="Delete game {{$game.game_id}}">Delete game</a></li>
						</ul>
					</li>
					{{/foreach}}
				</ul>
				{{/if}}
				{{if $games.player_ended}}
				<ul class="nav nav-pills nav-stacked" id="chess-games-owner">
					{{foreach $games.player_ended as $game}}
					<li class="dropdown" id="chess-game-{{$game.game_id}}">
						<a class="dropdown-toggle" data-toggle="dropdown" href="#">{{$game.date}} : <b>{{$game.opponent}}</b><b class="icon-caret-down pull-right"></b></a>
						<ul class="dropdown-menu  pull-right">
							<li><a href="/chess/{{$channel}}/{{$game.game_id}}" title="Play game">Play game</a></li>
							<li><a href="{{$game.plink}}" target="_blank" title="View conversation">View conversation</a></li>
						</ul>
					</li>
					{{/foreach}}
				</ul>
				{{/if}}
			</div> <!-- chess-controls-games-collapse -->
			<br>
		</div> <!-- panel -->
		{{/if}}
		{{if $historyviewer}}
		<div class="panel" id="chess-controls-history">
			<div class="section-subtitle-wrapper" role="tab" id="chess-controls-history"  data-toggle="collapse" data-parent="#chess-controls-history" href="#chess-controls-history-collapse" aria-expanded="true" aria-controls="chess-controls-history-collapse">
				<h3>History Viewer</h3>
			</div> <!-- section-subtitle-wrapper -->
			<div id="chess-controls-history-collapse" class="panel-collapse collapse" role="tabpanel" aria-labelledby="chess-controls-history">
				<div id="chess-revert">
					<ul class="nav nav-pills nav-stacked">
						<li><div id="chess-revert-info"></div><br><a class="btn btn-sm btn-danger" href="#" onclick="alert('chess_revert_position() not operational yet'); return false;">Revert</a><br></li>
					</ul>
				</div>
				<p>Select a position to view the game history. Select Current Position to resume play.</p>
				<ul class="nav flex-column" id="chess-move-history">
				</ul>
			</div> <!-- chess-controls-history-collapse -->

		</div> <!-- panel -->
		{{/if}}
		<div class="panel" id="chess-controls-settings">
			<div class="section-subtitle-wrapper" role="tab" id="chess-controls-settings"  data-toggle="collapse" data-parent="#chess-controls-settings" href="#chess-controls-settings-collapse" aria-expanded="true" aria-controls="chess-controls-settings-collapse">
				<h3>Settings</h3>
			</div> <!-- section-subtitle-wrapper -->
			<div id="chess-controls-settings-collapse" class="panel-collapse collapse" role="tabpanel" aria-labelledby="chess-controls-settings">
				<div style="padding-left: 10px;">
					<label class="settings-label" for="id_{{$notify_toggle.0}}">{{$notify_toggle.1}}</label>
					<input type="checkbox" name='{{$notify_toggle.0}}' id='id_{{$notify_toggle.0}}' value="1" {{if $notify_toggle.2}}checked="checked"{{/if}} {{if $notify_toggle.5}}{{$notify_toggle.5}}{{/if}} />
				</div>
			</div> <!-- chess-controls-settings-collapse -->

		</div> <!-- panel -->

	</div> <!-- panel-group -->
</div>
<p class="descriptive-text" style="margin-left: 15px;">{{$version}}</p>
