function next_id_helper(prefix, key)
{
	var id = 1;
	while (localStorage.getItem(prefix+"."+id+"."+key))
	{
		id++;
	}
	return id;
}

function next_tournament_id()
{
	return next_id_helper("webtd.tournament", "created");
}

function next_player_id()
{
	return next_id_helper("webtd.roster", "name");
}

function next_play_id()
{
	return next_id_helper("webtd.plays", "player1");
}

function get_tournament(eid)
{
	var e = {
	"name":      localStorage.getItem("webtd.tournament."+eid+".name"),
	"location":  localStorage.getItem("webtd.tournament."+eid+".location"),
	"startDate": localStorage.getItem("webtd.tournament."+eid+".startDate"),
	"endDate":   localStorage.getItem("webtd.tournament."+eid+".endDate"),
	"startTime": localStorage.getItem("webtd.tournament."+eid+".startTime"),
	"endTime":   localStorage.getItem("webtd.tournament."+eid+".endTime"),
	"player_param1": localStorage.getItem("webtd.tournament."+eid+".player_param1"),
	"eid": eid
	};
	return e;
}

function get_player(pid)
{
	var name = localStorage.getItem("webtd.roster."+pid+".name");
	var givenName = localStorage.getItem("webtd.roster."+pid+".givenName");
	var number = localStorage.getItem("webtd.roster."+pid+".number");
	var entryRank = localStorage.getItem("webtd.roster."+pid+".entryRank");
	var p = {
		"name": name,
		"givenName": givenName,
		"number": number,
		"entryRank": entryRank,
		"pid": pid
		};
	p.fullName = p.name + (p.givenName != null && p.givenName != ""
		? (", " + p.givenName) : "");
	return p;
}

function get_all_tournaments()
{
	var list_str = localStorage.getItem("webtd.all_tournaments");
	var list = list_str ? list_str.split(',') : [];
	var rv = [];
	for (var i in list)
	{
		var eid = list[i];
		rv.push(get_tournament(eid));
	}
	return rv;
}

function get_all_players(eventId)
{
	var roster_str = localStorage.getItem("webtd.tournament."+eventId+".roster");
	var roster = roster_str != null ? roster_str.split(',') : new Array();
	var players = new Array();
	for (var i in roster)
	{
		var pid = roster[i];
		players.push(get_player(pid));
	}
	return players.sort(function(a,b) {
			if (a.name.toLowerCase() > b.name.toLowerCase()) {
				return 1;
			} else if (a.name.toLowerCase() < b.name.toLowerCase()) {
				return -1;
			} else {
				return 0;
			}
		});
}

function save_tournament_from_form(eid, form)
{
	var e = {
	"eid": eid,
	"name": form.name.value,
	"location": form.location.value,
	"startDate": form.startDate.value,
	"endDate": form.endDate.value,
	"startTime": form.startTime.value,
	"endTime": form.endTime.value,
	"player_param1": form.player_param1.value
	};

	localStorage.setItem("webtd.tournament."+eid+".created", 1);
	localStorage.setItem("webtd.tournament."+eid+".name", e.name);
	localStorage.setItem("webtd.tournament."+eid+".location", e.location);
	localStorage.setItem("webtd.tournament."+eid+".startDate", e.startDate);
	localStorage.setItem("webtd.tournament."+eid+".endDate", e.endDate);
	localStorage.setItem("webtd.tournament."+eid+".startTime", e.startTime);
	localStorage.setItem("webtd.tournament."+eid+".endTime", e.endTime);
	localStorage.setItem("webtd.tournament."+eid+".player_param1", e.player_param1);
}

function save_player_from_form(pid, form)
{
	var p = {
	name: form.name.value,
	givenName: form.givenName.value,
	entryRank: form.entryRank.value,
	number: form.number.value,
	pid: pid
	};

	localStorage.setItem("webtd.roster."+pid+".name", p.name);
	localStorage.setItem("webtd.roster."+pid+".givenName", p.givenName);
	localStorage.setItem("webtd.roster."+pid+".number", p.number);
	localStorage.setItem("webtd.roster."+pid+".entryRank", p.entryRank);
}


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

function refresh_player_form()
{
	var pid = get_url_param("p");
	var p = get_player(pid);

	document.player_form.name.value = p.name;
	document.player_form.givenName.value = p.givenName;
	document.player_form.number.value = p.number;
	document.player_form.entryRank.value = p.entryRank;
}

// can be called multiple times
function refresh_tournament_form(form)
{
	var eid = get_url_param("e");
	var e = get_tournament(eid);

	form.name.value = e.name;
	form.location.value = e.location;
	form.startDate.value = e.startDate;
	form.startTime.value = e.startTime;
	form.endDate.value = e.endDate;
	form.endTime.value = e.endTime;
	form.player_param1.value = e.player_param1;

	if (eid) {
		$('.delete_btn', $(form)).show();
	} else {
		$('.delete_btn', $(form)).hide();
	}
}

function on_tournament_cancel()
{
	window.location = ".";
	return false;
}

function on_tournament_delete()
{
	if (confirm("Really delete this tournament?"))
	{
		var eid = get_url_param("e");
		maybe_remove_from_set("webtd.all_tournaments", eid);
	}

	window.location = ".";
	return false;
}

function on_player_cancel()
{
	window.location = ".";
}

function on_player_submit()
{
	var pid = get_url_param("p");
	save_player_from_form(pid, document.player_form);

	window.location = ".";
}

function on_tournament_submit()
{
	var eid = get_url_param("e");
	if (!eid) {
		eid = next_tournament_id();
	}

	save_tournament_from_form(eid, document.tournament_form);

	maybe_add_to_set("webtd.all_tournaments", eid);

	window.location = ".";
	return false;
}

function maybe_add_to_set(k, x)
{
	var list_str = localStorage.getItem(k);
	var list = list_str ? list_str.split(',') : [];
	for (var i in list)
	{
		if (list[i] == x) return false;
	}
	list.push(x);
	localStorage.setItem(k, list.join(","));
	return true;
}

function maybe_remove_from_set(k, x)
{
	var list_str = localStorage.getItem(k);
	var list = list_str ? list_str.split(',') : [];
	var changed = false;
	for (var i = list.length-1; i >= 0; i--)
	{
		if (list[i] == x) {
			list.splice(i, 1);
			changed = true;
		}
	}

	if (changed) {
		localStorage.setItem(k, list.join(','));
	}
	return changed;
}

function on_player_delete()
{
	if (!confirm("Really delete this player?"))
		return;

	var pid = get_url_param("p");
	var eventId = get_current_event_id();
	var roster = localStorage.getItem("webtd.tournament."+eventId+".roster").split(",");
	for (var i in roster)
	{
		if (roster[i] == pid)
		{
			roster.splice(i, 1);
			break;
		}
	}
	localStorage.setItem("webtd.tournament."+eventId+".roster", roster.join(","));
	localStorage.removeItem("webtd.roster."+pid+".name");
	localStorage.removeItem("webtd.roster."+pid+".givenName");
	localStorage.removeItem("webtd.roster."+pid+".number");
	localStorage.removeItem("webtd.roster."+pid+".entryRank");

	window.location = ".";
}

function get_play(id)
{
	var prefix = "webtd.plays."+id;
	var play = {
		"id": id,
		"player1":   localStorage.getItem(prefix+".player1"),
		"player2":   localStorage.getItem(prefix+".player2"),
		"winner":    localStorage.getItem(prefix+".winner"),
		"handicapStones": localStorage.getItem(prefix+".handicapStones"),
		"komi":      localStorage.getItem(prefix+".komi")
		};
	return play;
}

function get_all_plays()
{
	var eid = get_current_event_id();
	var plays_str = localStorage.getItem("webtd.tournament."+eid+".plays");
	var plays_arr = plays_str != null ? plays_str.split(",") : new Array();
	var plays = new Array();
	for (var i in plays_arr)
	{
		plays.push(get_play(plays_arr[i]));
	}
	return plays;
}

function refresh_tournaments_table()
{
	$('tr.oddRow', $(this)).remove();
	var tournaments = get_all_tournaments();
	for (var i in tournaments)
	{
		var e = tournaments[i];
		var $row = $('tr.aTemplate', $(this)).clone();
		$row.removeClass('aTemplate');
		$row.addClass('oddRow');
		$('.name_col a', $row).text(e.name);
		$('.name_col a', $row).attr("href", "tournament.html?e=" + escape(e.eid));
		$('.location_col', $row).text(e.location);
		$('.date_col', $row).text(e.startDate);
		$(this).append($row);
	}
}

function refresh_players_table(tableId)
{
	var eventId = get_current_event_id();
	var tableEl = document.getElementById(tableId);
	var players = get_all_players(eventId);
	for (var i in players)
	{
		var p = players[i];
		var $row = $('<tr><td class="name_col"><a></a></td><td class="idnum_col"></td><td class="rank_col"></td></tr>');
		$('.name_col a', $row).text(p.fullName);
		$('.name_col a', $row).attr("href", "player.html?p=" + escape(p.pid));
		$('.idnum_col', $row).text(p.number);
		$('.rank_col', $row).text(p.entryRank);
		$(tableEl).append($row);
	}
}

function refresh_plays_table()
{
	var plays = get_all_plays();
	var count = 0;
	for (var i in plays)
	{
		var play = plays[i];
		var p1 = get_player(play.player1);
		var p2 = get_player(play.player2);
		var $row = $('<tr><td class="number_col"><a></a></td><td class="player1_col"></td><td class="player2_col"></td><td class="winner_col"></td><td class="handicap_col"></td><td class="komi_col"></td></tr>');
		$('.number_col a', $row).text(++count);
		$('.number_col a', $row).attr('href', "play.html?id=" + escape(play.id));
		$('.player1_col', $row).text(p1 != null ? p1.fullName : play.player1);
		$('.player2_col', $row).text(p2 != null ? p2.fullName : play.player2);
		$('.winner_col', $row).text(play.winner);
		$('.handicap_col', $row).text(play.handicapStones);
		$('.komi_col', $row).text(play.komi);
		$('#plays_table').append($row);
	}
}

function on_play_cancel()
{
	window.location = ".";
}

function refresh_players_list(selectEl)
{
	var eventId = get_current_event_id();
	var players = get_all_players(eventId);
	for (var i in players)
	{
		var p = players[i];
		var $opt = $('<option></option>');
		$opt.attr('value', p.pid);
		$opt.text(p.fullName);
		$(selectEl).append($opt);
	}
}

function refresh_play_form()
{
	var id = get_url_param("id");
	var play = get_play(id);
	var f = document.play_form;

	f.player1.value = play.player1;
	f.player2.value = play.player2;
	f.winner.value = play.winner;
	f.handicapStones.value = play.handicapStones;
	f.komi.value = play.komi;
}

function save_play_from_form(id, form)
{
	var player1 = form.player1.value;
	var player2 = form.player2.value;
	var winner = form.winner.value;
	var handicapStones = form.handicapStones.value;
	var komi = form.komi.value;

	localStorage.setItem("webtd.plays."+id+".player1", player1);
	localStorage.setItem("webtd.plays."+id+".player2", player2);
	localStorage.setItem("webtd.plays."+id+".winner", winner);
	localStorage.setItem("webtd.plays."+id+".handicapStones", handicapStones);
	localStorage.setItem("webtd.plays."+id+".komi", komi);
}

function on_newplay_submit()
{
	alert("unexpected");
}

function get_current_event_id()
{
	return 1;
}

function on_play_submit()
{
	var id = get_url_param("id");
	if (id != null)
	{
		//update an existing play
		save_play_from_form(id, document.play_form);
	}
	else
	{
		//new play
		id = next_play_id();
		save_play_from_form(id, document.play_form);

		var eid = get_current_event_id();
		var plays_str = localStorage.getItem("webtd.event."+eid+".plays");
		var plays_arr = plays_str != null ? plays_str.split(",") : new Array();
		plays_arr.push(id);
		localStorage.setItem("webtd.tournament."+eid+".plays", plays_arr.join(","));
	}


	window.location = ".";
}

function on_play_delete()
{
	if (!confirm("Really delete this play?"))
		return;

	var eid = get_current_event_id();
	var play_id = get_url_param("id");
	var plays_arr = localStorage.getItem("webtd.tournament."+eid+".plays").split(",");
	for (var i in plays_arr)
	{
		if (plays_arr[i] == play_id)
		{
			plays_arr.splice(i, 1);
			break;
		}
	}
	localStorage.setItem("webtd.tournament."+eid+".plays", plays_arr.join(","));
	localStorage.removeItem("webtd.plays."+play_id+".player1");
	localStorage.removeItem("webtd.plays."+play_id+".player2");
	localStorage.removeItem("webtd.plays."+play_id+".handicapStones");
	localStorage.removeItem("webtd.plays."+play_id+".komi");
	localStorage.removeItem("webtd.plays."+play_id+".winner");

	window.location = ".";
}

function strpad_l(str, len)
{
	while (len - str.length >= 10)
		str = "          " + str;
	if (str.length < len)
		str = "          ".substr(0,len - str.length) + str;
	return str;
}

function strpad_r(str, len)
{
	while (len - str.length >= 10)
		str += "          ";
	if (str.length < len)
		str += "          ".substr(0,len - str.length);
	return str;
}

function make_game_results_file()
{
	var eid = '1';
	var e = get_tournament(eid);
	var rules = "AGA";

	var w = window.open(null, null, "width=360,height=480,scrollbars=yes");
	w.document.write("<pre>");
	w.document.write("TOURNEY " + e.name + "\n");
	if (e.startDate) {
		w.document.write("        start="+e.startDate+"\n");
	}
	if (e.endDate) {
		w.document.write("        finish="+e.endDate+"\n");
	}
	if (rules) {
		w.document.write("        rules="+rules+"\n");
	}
	w.document.write("PLAYERS\n");

	var players = get_all_players(eid);
	var players_by_pid = new Array();
	var tmp_number = 99999;
	for (var i in players) {
		var p = players[i];
		if (!p.number) {
			p.number = tmp_number--;
		}
		players_by_pid[p.pid] = p;
		w.document.write(strpad_l(p.number,5) + " ");
		w.document.write(strpad_r(p.fullName, 30) + " ");
		w.document.write(p.entryRank + "\n");
	}

	w.document.write("GAMES\n");
	var plays = get_all_plays();
	for (var i in plays)
	{
		var play = plays[i];
		var p1 = players_by_pid[play.player1];
		var p2 = players_by_pid[play.player2];
		w.document.write(p1.number + " " + p2.number + " " + 
			play.winner + " " + play.handicapStones + " " +
			play.komi + "\n");
	}

	w.document.write("</pre>");
}

function onTableBoxDragOver(evt)
{
	if (evt.dataTransfer.types.contains('application/webtd+player'))
	{
		evt.stopPropagation();
		evt.preventDefault();
		evt.dropEffect = 'move';
	}
	return false;
}

function onTableBoxDrop(evt)
{
}

function createTableBox(tableName)
{
	var $tableBox = $('.play_box.template').clone();
	$tableBox.removeClass('template');
	$tableBox.attr('table-id', tableName);
	$('#main_org_area').append($tableBox);
	$('.play_box_table_name', $tableBox).text(tableName);
	$tableBox.show();

	var el = $tableBox.get(0);
if (el)
{
	el.addEventListener('dragover', onTableBoxDragOver, false);
	el.addEventListener('drop', onTableBoxDragOver, false);
}

	return $tableBox;
}

function onPlayerBoxDragStart(evt)
{
	var playerId = this.getAttribute('player-id');

	evt.dataTransfer.effectAllowed = 'move';
	evt.dataTransfer.setData('application/webtd+player', playerId);
	$(this).addClass('beingDragged');
}

function onPlayerBoxDragEnd(evt)
{
	$(this).removeClass('beingDragged');

}

function onPlayerBoxNameEdited($playerBox)
{
	var v = $('.player_name_entry', $playerBox).get(0).value;
	$('.edit_form', $playerBox).remove();

	$playerBox.removeClass('isEditing');
	$playerBox.addClass('draggable');
	$playerBox.attr('draggable', 'true');

	$('.player_name', $playerBox).text(v);
}

function onPlayerBoxClicked(evt)
{
	var pbEl = this;
	var $playerBox = $(pbEl);

	if ($playerBox.hasClass('isEditing'))
		return;

	$playerBox.addClass('isEditing');
	$playerBox.removeClass('draggable');
	$playerBox.attr('draggable', 'false');

	$playerBox.append('<form class="edit_form"><input type="text" class="player_name_entry"></form>');
	$('.edit_form', $playerBox).submit(function() {
		onPlayerBoxNameEdited($playerBox);
		return false;
		});
	var entryEl = $('.player_name_entry', $playerBox).get(0);
	entryEl.value = $('.player_name', $playerBox).text();
	entryEl.select();
	entryEl.focus();
}

function addPlayerBoxEventListeners(pbEl)
{
	if (!pbEl) { return; }

	$(pbEl).click(onPlayerBoxClicked);
	pbEl.addEventListener('dragstart', onPlayerBoxDragStart, false);
	pbEl.addEventListener('dragend', onPlayerBoxDragEnd, false);
}

function createPlayer($tableBox, playerName)
{
	var $playerBox = $('.player_box.template').clone();
	$playerBox.removeClass('template');
	$playerBox.attr('player-id', 'abc');
	$playerBox.attr('draggable','true');
	$('.player_name', $playerBox).text(playerName);
	$('.players_container', $tableBox).append($playerBox);
	addPlayerBoxEventListeners($playerBox.get(0));
	return $playerBox;
}

function makeTables()
{
	var t1 = createTableBox("Table 1");
	createPlayer(t1, "Jason 3775");
	createPlayer(t1, "Alfred 12");
	createPlayer(t1, "Banana 34");

	var t2 = createTableBox("Table 2");
	createPlayer(t2, "Bob 99");
	createPlayer(t2, "Charles 88");

	var t3 = createTableBox("Unassigned");
	t3.addClass('unassignedTable');
	createPlayer(t3, "Blah");
}

// should only be called once
function initialize_tournament_form()
{
	refresh_tournament_form(this);
	$(this).submit(on_tournament_submit);
	$('.cancel_btn', $(this)).click(on_tournament_cancel);
	$('.delete_btn', $(this)).click(on_tournament_delete);
	$('input[name="startDate"]', $(this)).datepicker({ dateFormat: 'yy-mm-dd' });
	$('input[name="endDate"]', $(this)).datepicker({ dateFormat: 'yy-mm-dd' });
}

$(function() {

	$('form[name="tournament_form"]').each(initialize_tournament_form);
	$('table.tournaments_list').each(refresh_tournaments_table);

	makeTables();
});
