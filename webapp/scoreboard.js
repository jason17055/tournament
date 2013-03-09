function generate_short_name(p)
{
	if (p.givenName)
	{
		return p.givenName.substr(0,1) + p.name.substr(0,1);
	}
	else
	{
		return p.name.substr(0,2);
	}
}
function do_short_names(players_arr)
{
	for (var i in players_arr)
	{
		var p = players_arr[i];
		if (!p.shortName) {
			p.shortName = generate_short_name(p);
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
	var a_r = to_rank(a.entryRank);
	var b_r = to_rank(b.entryRank);
	return -(a_r - b_r);
}

var players = get_all_players();
players = players.sort(cmp_rank);
do_short_names(players);
var games = get_all_plays();
for (var i in players)
{
	var pc = players[i];
	var $cell = $('<th width="35"></th>');
	$cell.text(pc.shortName);
	$cell.attr('class', 'opponent'+pc.pid+'_col');
	$('#th_row').append($cell);
}
var start = get_url_param('start');
if (start == null) { start = 0; }
var ROWS = 6;
var COLS = 12;
var DELAY = 30; //seconds
var min_opp_by_pid = new Array();
var max_opp_by_pid = new Array();
for (var i = 0; i < players.length; i++)
{
	var pr = players[i];
	var $row = $('<tr><td class="fullname_cell"></td><td class="wins_cell"></td><td class="plays_cell"></td><td></td></tr>');
	$row.attr('class', (i % 2 == 0) ? 'oddrow' : 'evenrow');
	$row.attr('id', "scoreboard_row"+i);
	$('.fullname_cell', $row).text(pr.fullName + ' ' + pr.entryRank);
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
			if (play.player1 == pr.pid && play.player2 == pc.pid)
			{
				result = play.winner == 'b' ? 'L' : 'W';
			}
			else if (play.player1 == pc.pid && play.player2 == pr.pid)
			{
				result = play.winner == 'b' ? 'W' : 'L';
			}
			else
			{
				continue;
			}

			count_plays++;
			if (result == 'W')
				count_wins++;
			if (min_opp_by_pid[pr.pid] == null)
				min_opp_by_pid[pr.pid] = j;
			max_opp_by_pid[pr.pid] = j;

			var $img = $('<div><img width="28" height="28"></div>');
			$('img',$img).attr('alt', result);
			$('img',$img).attr('title', 'details');
			$('img',$img).attr('src',
				result == "W" ? 'images/win_icon.png' : 'images/lose_icon.png');
			$cell.append($img);
		}
		$row.append($cell);
	}
	$('.wins_cell', $row).text(count_wins);
	$('.plays_cell', $row).text(count_plays);
	$('#scoreboard_table').append($row);
	if (i < start || i >= start + ROWS)
	{
		$row.hide();
	}
}

function prune_columns(row0, row1)
{
	var min_opp = row0;
	var max_opp = row1 - 1;
	for (var a = row0; a < row1 && a < players.length; a++)
	{
		if (min_opp_by_pid[players[a].pid] != null
			&& min_opp_by_pid[players[a].pid] < min_opp)
		{
			min_opp = min_opp_by_pid[players[a].pid];
		}
		if (max_opp_by_pid[players[a].pid] != null
			&& max_opp_by_pid[players[a].pid] > max_opp)
		{
			max_opp = max_opp_by_pid[players[a].pid];
		}
	}

	var d = COLS - (max_opp - min_opp + 1);
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
	if (start+ROWS < players.length)
	{
		var i = start;
		var j = start + ROWS;

		var $old = $('#scoreboard_row'+i);
		for (var a = i + 1; a < j; a++)
		{
			$old = $old.add('#scoreboard_row'+a);
		}

		var $new = $('#scoreboard_row'+j);
		for (var a = j + 1; a < j + ROWS && a < players.length; a++)
		{
			$new = $new.add('#scoreboard_row'+a);
		}

		$old.hide();
		$new.show();
		start += ROWS;
		prune_columns(start, start + ROWS);

		setTimeout(nextPage, DELAY * 1000);
	}
	else
	{
		window.location.reload();
	}
}
prune_columns(start, start+ROWS);
setTimeout(nextPage,DELAY * 1000);

