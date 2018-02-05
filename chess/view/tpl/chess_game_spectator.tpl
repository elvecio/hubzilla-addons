<script>
var chess_game_id = '{{$game_id}}';
var chess_timer = null;
var chess_board = null;
var chess_viewing_history = false;
var chess_original_pos = '{{$position}}';
var chess_new_pos = [];
var chess_viewing_position = '';
var chess_viewing_mid = '';
var chess_game_ended = {{$ended}};
var chess_game = null;
var chess_game_move = null;
var chess_init_active = '{{$active}}';
var chess_history = null;
var chess_moves = null;

var chess_init = function () {
	$('#'+chess_init_active).show();
	$("#chess-verify-move").hide();
	if($("#chess-game-" + chess_game_id).length) {
		$("#chess-game-" + chess_game_id).css("font-weight","bold");
		$("#chess-game-" + chess_game_id + " a").css("color", "blue");
	}
	$("#chess-revert").hide();
	$("#expand-aside").on('click', function () {
		setTimeout(chess_fit_board(),500)
	});
	if (chess_game_ended === 1) {
		$("#chess-turn-indicator").html('Game Over');
		$("#chess-resume-game").show();
	} else {
		$("#chess-resume-game").hide();
	}
	var cfg = {
		//sparePieces: true,
		position: '{{$position}}',
		orientation: 'white',
		//dropOffBoard: 'snapback',
		onDragStart: false
	};
	chess_board = new ChessBoard('chessboard', cfg);
	chess_game = new Chess(chess_original_pos);
	
	$(window).resize(chess_fit_board);
	setTimeout(chess_fit_board,300);
	chess_timer = setTimeout(chess_update_game,300);
	
	$("#position-index").on('click', function () {
		chess_viewing_history = false;
		chess_timer = setTimeout(chess_update_game,300);
	});
	$("#history-prev").on('click', function () {
		showPosition('prev');
	});
	$("#history-next").on('click', function () {
		showPosition('next');
	});
};

var showPosition = function (direction) {
	chess_viewing_history = true;
	var currentPositionIdx = $('#position-index').html();
	var currentPosition = ChessBoard.objToFen(chess_board.position());
	for(var i=1; i<chess_moves.length; i++) {
		if(chess_moves[i].indexOf(currentPosition) >= 0) {
			switch(direction) {
				case 'prev':
					var deltaIdx = -1;
					break;
				case 'next':
					if(i === chess_moves.length-1) {
						var deltaIdx = 2-chess_moves.length;
					} else {
						var deltaIdx = 1;
					}
					break;
			}
			chess_board.position(chess_moves[i+deltaIdx]);
			$("#position-index").html(i+deltaIdx);
			return false;
		}
	}
	chess_board.position(chess_moves[chess_moves.length-1]);
	$("#position-index").html(chess_moves.length-1);
	chess_viewing_history = true;
	return false;
};

var chess_fit_board = function () {
	var viewportHeight = $(window).height() - $("#chessboard").offset().top;
	var leftOffset = 0;
	if($(window).width() > 767) {
		leftOffset = 1.1*$('#region_1').width() + $('#region_1').offset().left;
	} else if ($("main").hasClass('region_1-on') ) {
		$('#chessboard').css('zIndex',-100);
		$('#region_1').css('background-color', 'rgba(255,255,255,0.8)');
		//leftOffset = $('#region_1').width();
	} else {
		$('#chessboard').css('zIndex','auto');
	}
	$("#chessboard").offset({ left: leftOffset});
	var centerRegionWidth = $('#region_2').width();
	if (viewportHeight < centerRegionWidth * 1.25) {
		$("#chessboard").css('width', viewportHeight / 1.25);
		$("#white-player-name").css('margin-top', viewportHeight / 1.2);
		$("#white-player-name").css('margin-left', 20);
		$("#black-player-name").css('margin-left', 20);
	} else {
		$("#chessboard").css('width', centerRegionWidth * 1.0);
		$("#white-player-name").css('margin-top', centerRegionWidth * 1.05);
		$("#white-player-name").css('margin-left', 20);
		$("#black-player-name").css('margin-left', 20);
	}
	chess_board.resize();
};
// only allow pieces to be dragged when the board is oriented
// in their direction
var chess_onDragStart = function(source, piece, position, orientation) {
	return false;
};


var chess_update_game = function () {
	if(chess_viewing_history) {
		return false;
	}
	$.post("chess/update", {game_id: chess_game_id} ,
	function(data) {
		//window.console.log('update received: '+JSON.stringify(data));
		if (data['status']) {
			chess_game = new Chess(data['position']);
			chess_board.position(data['position']);
			chess_game_ended = data['ended'];
			if (chess_game_ended) {
				$('#chess-turn-indicator').html("Game Over");
				return false;
			} else {
				$('.turn-indicator').hide();
				$('.player-name').css('font-weight', 'normal');
				$('#'+data['active']+'_name').css('font-weight', 'bold');
				$('#'+data['active']).show();
			}
		} else {
			window.console.log('Error updating: ' + data['errormsg']);
		}
		return false;
	},
	'json');
	chess_timer = setTimeout(chess_update_game,15000);
	chess_get_history();
	
};

var chess_get_history = function () {
	$.post("chess/history", {game_id: chess_game_id} , function(data) {
		if (data['status']) {
			chess_history = data['history'];
			chess_moves = [];
			for(var i=0; i<chess_history.length; i++) {
				var move = JSON.parse(chess_history[i]['obj']);
				chess_moves.push(move['position']);
			}
			$("#position-index").html(chess_moves.length-1);
		} else {
			window.console.log('Error: ' + data['errormsg']);
		}
		return false;
	},
	'json');
}

$(document).ready(chess_init);
</script>
<h2 id='chess-turn-indicator'>
	{{if $ended}}
	Game Over
	{{/if}}
</h2>
<div id="chess-enforce-legal-moves"></div>
<div id="history-controls"  style="font-size: 1.5em;">
	<span id="history-prev" class="fakelink"><i class="fa fa-arrow-left fa-3xs" aria-hidden="true"></i></span>
		&nbsp;
		<span id="position-index" class="fakelink"></span>
		&nbsp;
		<span id="history-next" class="fakelink"><i class="fa fa-arrow-right fa-3xs" aria-hidden="true"></i></span>
</div>
<div id="black-player-name">
	<h2>
		<span class="turn-indicator" id="{{$black_xchan_hash}}" style="display: none;">
			<img src="/addon/chess/view/img/chesspieces/wikipedia/bN.png" height="40" style="padding-right: 5px;">
		</span>
		<span class="player-name" id="{{$black_xchan_hash}}_name">{{$blackplayer}}</span>
	</h2>
</div>

<div id="chessboard" style="width: 400px; position: fixed;"></div>

<div id="white-player-name" style="margin-top: 400px;">
	<h2>
		<span class="turn-indicator" id="{{$white_xchan_hash}}" style="display: none;">
			<img src="/addon/chess/view/img/chesspieces/wikipedia/wN.png" height="40" style="padding-right: 5px;">
		</span>
		<span class="player-name" id="{{$white_xchan_hash}}_name">{{$whiteplayer}}</span>
	</h2>
</div>
