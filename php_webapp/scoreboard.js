function get_url_param(p)
{
	var args = document.location.search.substring(1).split("&");
	for (var i in args)
	{
		var x = args[i].split("=", 2);
		if (unescape(x[0]) == p)
		{
			return unescape(x[1]);
		}
	}
	return null;
}

function generate_short_name(p)
{
	var n = p.name;
	if (n.match(/,/)) {
		var i = n.indexOf(',');
		n = n.substring(i+1) + ' ' + n.substring(0,i);
	}

	var parts = n.split(/ /);

	var x = "";
	for (var i in parts) {
		x += parts[i].substr(0,1);
	}
	return x;
}
function do_short_names(players_arr)
{
	for (var i in players_arr)
	{
		var p = players_arr[i];
		if (!p.initials) {
			p.initials = generate_short_name(p);
		}
	}
}
function to_rank(rankStr)
{
	return parseInt(rankStr.substr(0,rankStr.length-1)) *
			(rankStr.substr(rankStr.length-1) == 'k' ? -1 : 1);
}
function cmp_rank(a, b)
{
	var a_r = to_rank(a.entryRank || "30k");
	var b_r = to_rank(b.entryRank || "30k");
	return -(a_r - b_r);
}

function fetch_all_players()
{
	var onError = function(jqxhr, status, errorThrown) {
		alert(status + ' ' + errorThrown);
	};

	$.ajax({
		url: 'scoreboard-data.js.php?tournament='+escape(get_url_param('tournament')),
		dataType: 'json',
		success: on_players_fetched,
		error: onError
		});
}

var players = [];
var games = [];
var S = {
	start: get_url_param('start'),
	ROWS: 7,
	COLS: 12,
	DELAY: 30 //seconds
	};

function on_players_fetched(data)
{
	players = data.players;
	games = data.games;

	players = players.sort(cmp_rank);
	do_short_names(players);

for (var i in players)
{
	var pc = players[i];
	var $cell = $('<th width="35"></th>');
	$cell.text(pc.initials);
	$cell.attr('class', 'opponent'+pc.pid+'_col');
	$('#th_row').append($cell);
}
if (S.start == null) { S.start = 0; }
S.min_opp_by_pid = new Array();
S.max_opp_by_pid = new Array();
for (var i = 0; i < players.length; i++)
{
	var pr = players[i];
	var $row = $('<tr><td class="fullname_cell"></td><td class="wins_cell"></td><td class="plays_cell"></td><td></td></tr>');
	$row.attr('class', (i % 2 == 0) ? 'oddrow' : 'evenrow');
	$row.attr('id', "scoreboard_row"+i);
	$('.fullname_cell', $row).text(pr.name + (pr.entryRank != null ? (' ' + pr.entryRank) : ""));
	var count_wins = 0;
	var count_plays = 0;
	for (var j in players)
	{
		var pc = players[j];
		var $cell = $('<td></td>');
		$cell.attr('class', 'opponent'+pc.pid+'_col');
		for (var k in games)
		{
			var play = games[k];
			var result = null;
			if (play['player.W'] == pr.pid && play['player.B'] == pc.pid)
			{
				result = play.in_progress ? 'P' :
					play.winner == 'W' ? 'W' : 'L';
			}
			else if (play['player.W'] == pc.pid && play['player.B'] == pr.pid)
			{
				result = play.in_progress ? 'P' :
					play.winner == 'B' ? 'W' : 'L';
			}
			else
			{
				continue;
			}

			if (result != 'P') {
				count_plays++;
			}
			if (result == 'W') {
				count_wins++;
			}
			if (S.min_opp_by_pid[pr.pid] == null) {
				S.min_opp_by_pid[pr.pid] = j;
			}
			S.max_opp_by_pid[pr.pid] = j;

			var $img = $('<div><img width="28" height="28"></div>');
			$('img',$img).attr('alt', result);
			$('img',$img).attr('title', 'details');
			$('img',$img).attr('src',
				result == "P" ? 'images/game_in_progress_icon.png' :
				result == "W" ? 'images/win_icon.png' : 'images/lose_icon.png');
			$cell.append($img);
		}
		$row.append($cell);
	}
	$('.wins_cell', $row).text(count_wins);
	$('.plays_cell', $row).text(count_plays);
	$('#scoreboard_table').append($row);
	if (i < S.start || i >= S.start + S.ROWS)
	{
		$row.hide();
	}
} //end foreach player row

	prune_columns(S.start, S.start+S.ROWS);
	setTimeout(nextPage,S.DELAY * 1000);
}

function prune_columns(row0, row1)
{
	var min_opp = row0;
	var max_opp = row1 - 1;
	for (var a = row0; a < row1 && a < players.length; a++)
	{
		if (S.min_opp_by_pid[players[a].pid] != null
			&& S.min_opp_by_pid[players[a].pid] < min_opp)
		{
			min_opp = S.min_opp_by_pid[players[a].pid];
		}
		if (S.max_opp_by_pid[players[a].pid] != null
			&& S.max_opp_by_pid[players[a].pid] > max_opp)
		{
			max_opp = S.max_opp_by_pid[players[a].pid];
		}
	}

	var d = S.COLS - (max_opp - min_opp + 1);
	if (d > 0)
	{
		min_opp -= d;
	}
	if (min_opp < 0)
	{
		var x = -min_opp;
		max_opp += x;
		min_opp += x;
	}

	for (var a = 0; a < players.length; a++)
	{
		var $s = $('.opponent'+players[a].pid+'_col');
		if (a < min_opp || a > max_opp)
		{
			$s.hide();
		}
		else
		{
			$s.show();
		}
	}
}

function nextPage()
{
	if (S.start+S.ROWS < players.length)
	{
		var i = S.start;
		var j = S.start + S.ROWS;

		var $old = $('#scoreboard_row'+i);
		for (var a = i + 1; a < j; a++)
		{
			$old = $old.add('#scoreboard_row'+a);
		}

		var $new = $('#scoreboard_row'+j);
		for (var a = j + 1; a < j + S.ROWS && a < players.length; a++)
		{
			$new = $new.add('#scoreboard_row'+a);
		}

		$old.hide();
		$new.show();
		S.start += S.ROWS;
		prune_columns(S.start, S.start + S.ROWS);

		setTimeout(nextPage, S.DELAY * 1000);
	}
	else
	{
		window.location.reload();
	}
}

fetch_all_players();


