function create_player_sel_box(idx, el)
{
	var $text_entry = $(el);
	var $hidden_el = $('<input type="hidden">');
	var el_name = $text_entry.attr('name');

	$hidden_el.attr('name', el_name);
	$hidden_el.attr('value', $text_entry.attr('data-player_id'));

	$text_entry.removeAttr('name');

	$text_entry.after($hidden_el);
	setup_player_sel_box($text_entry, $hidden_el);
}

function setup_player_sel_box($text_entry, $hidden_el)
{
	$text_entry.autocomplete({
		source: players_src,
		focus: function(evt, ui) {
			$text_entry.val( ui.item.label );
			return false;
			},
		select: function(evt, ui) {
			$text_entry.val( ui.item.label );
			$hidden_el.val( ui.item.value );
			return false;
			}
		});
}

$(function() {
	$('input.player_sel').each(create_player_sel_box);
	});

var nextUniqueRowId = 1;
function on_add_participant_clicked(evt)
{
	evt.preventDefault();

	var name_prefix = 'participant__'+(nextUniqueRowId++);

	var $r = $('#new_participant_row').clone();
	$r.removeClass('template');
	$r.removeAttr('id');

	$('input', $r).each(function(idx,el) {
		var n = $(el).attr('name');
		if (n) {
			n = name_prefix+n;
			$(el).attr('name', n);
		}
		});

	setup_player_sel_box(
		$('input.player_sel', $r),
		$('.player_col input[type=hidden]', $r)
		);

	$('#participants_table').append($r);

	$('.delete_row_btn', $r).click(function(evt) {
		$r.remove();
		});

	return false;
}

function on_delete_participant_clicked(evt)
{
	var el = this;
	var row_el = null;
	var rowid = null;

	while (el && !rowid) {
		rowid = el.getAttribute('data-rowid');
		row_el = el;
		el = el.parentNode;
	}

	if (!rowid) { return; }

	var $x = $('<input type="hidden">');
	$x.attr('name', 'participant_'+rowid+'_delete');
	$x.attr('value', '1');
	$('#participants_table').after($x);

	$(row_el).remove();
}

$(function() {
	$('#add_participant_link').click(on_add_participant_clicked);
	$('.delete_row_btn').click(on_delete_participant_clicked);
});

$(function() {
	$('.driller_content').hide();
	$('.driller_heading').click(function() {
		$(this).next('.driller_content').slideToggle(500);
		});
});

var popup_menu_trigger_btn = null;
function connect_popup_menu_btn(el)
{
	$(el).click(function(evt) {
		popup_menu_trigger_btn = this;
		var elId = $(this).attr('data-for');
		var el = document.getElementById(elId);
		var $menu = $(el);
		$menu.css({
			left: 0,
			top: 0
			});
		$menu.position(
			{
			my: "right top",
			at: "right bottom",
			of: this,
			collision: "none"
			});
		$menu.show();
		$('ul', $menu).focus();
		});
}

$(function() {
	$('.popup_menu').each(function(idx,el) {
		$('ul', $(el)).menu();
		$('ul', $(el)).on('blur', function(evt) {
			$(el).hide();
			});
	});

	$('.popup_menu_btn').each(function(idx,el) {
		connect_popup_menu_btn(el);
		});
	});

function on_new_player_select(evt, ui)
{
	var f = document.getElementById('new_person_form');
	f.name.value = ui.item.name;
	f.member_number.value = ui.item.member_number;
	f.entry_rank.value = ui.item.rating;
	f.home_location.value = ui.item.home_location;
}

$(function() {
	$('#new_person_form #name_entry').autocomplete({
		source: 'autocomplete-new-person.php?tournament='+webtd_tournament_id,
		minLength: 2,
		select: on_new_player_select
	});

	$('#new_person_form #member_number_entry').autocomplete({
		source: 'autocomplete-new-person.php?tournament='+webtd_tournament_id+'&field=member_number',
		select: on_new_player_select
	});
});

function make_game_results_file()
{
	var onError = function(jqxhr, status, errorThrown) {
		alert(status + ' ' + errorThrown);
	};
	var onSuccess = function(data) {

		var w = window.open(null, null, "width=360,height=480,scrollbars=yes");
		w.document.write("<pre>");
		w.document.write("TOURNEY "+data.tournament.name);
		if (data.tournament.location) {
			w.document.write(", "+data.tournament.location);
		}
		w.document.write("\n");
		if (data.tournament.start_time) {
			w.document.write("        start="+data.tournament.start_time+"\n");
		}
		if (data.tournament.end_time) {
			w.document.write("        finish="+data.tournament.end_time+"\n");
		}
		w.document.write("        rules=AGA\n");

		var players = data.players;
		var max_name_len = 0;
		for (var i in players) {
			var p = players[i];
			if (p.name.length > max_name_len) {
				max_name_len = p.name.length;
			}
		}

		w.document.write("PLAYERS\n");

		var players_by_pid = {};
		var tmp_number = 99999;
		for (var i in players) {
			var p = players[i];
			if (!p.member_number) {
				p.member_number = tmp_number--;
			}
			players_by_pid[p.pid] = p;
			w.document.write(strpad_l(p.member_number,5)+" ");
			w.document.write(strpad_r(p.name, max_name_len) + " ");
			w.document.write(p.entryRank + "\n");
		}

		w.document.write("GAMES\n");
		var plays = data.games;
		for (var i in plays) {
			var play = plays[i];
			var p1 = players_by_pid[play['player.W']];
			if (!p1) {
				w.document.write("WARNING: invalid W player\n");
				continue;
			}
			var p2 = players_by_pid[play['player.B']];
			if (!p2) {
				w.document.write("WARNING: invalid B player\n");
				continue;
			}

			var handicapStones = 0;
			var m = play.scenario.match(/(\d+) stone/);
			if (m) {
				handicapStones = +m[1];
			}
			var komi = 0;
			var m = play.scenario.match(/([\d.]+) komi/);
			if (m) {
				komi = +m[1];
			}
			w.document.write(p1.member_number +
				" " + p2.member_number +
				" " + (play.winner == 'W' ? 'w' : 'b') + " " +
				handicapStones + " " +
				komi + "\n");
		}
		w.document.write("</pre>");
	};

	$.ajax({
		url: 'scoreboard-data.js.php?tournament='+escape(webtd_tournament_id),
		dataType: 'json',
		success: onSuccess,
		error: onError
		});
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

$(function() {
	$('#make_game_results_link').click(make_game_results_file);
});

function load_pairings_into(pairings_data, container_el)
{
	var players_raw = pairings_data.players;
	var assignments = pairings_data.contests;

	var players = {};
	for (var i = 0; i < players_raw.length; i++) {
		players[players_raw[i].pid] = players_raw[i];
	}

	var all_rounds = {};
	var all_tables = {};
	for (var i = 0; i < assignments.length; i++) {
		var a = assignments[i];
		all_rounds[a.round] = {};
		all_tables[a.table] = {};
	}

	var rounds_sorted = Object.keys(all_rounds).sort();
	var tables_sorted = Object.keys(all_tables).sort();

	function setup_contest_box_handlers(contest_box_el)
	{
		var contest_id = contest_box_el.getAttribute('data-webtd-contest');
		function onDragEnter(evt) {
			this.classList.add('over');
		}
		function onDragOver(evt) {
			evt.preventDefault();
			evt.stopPropagation();
			if (evt.dataTransfer.types.contains('application/webtd+person')) {
				evt.dataTransfer.dropEffect = 'move';
			}
			else {
				evt.dataTransfer.dropEffect = 'none';
			}
			return false;
		}
		function onDragLeave(evt) {
			this.classList.remove('over');
		}
		function onDrop(evt) {
			evt.preventDefault();
			evt.stopPropagation();

			var data;
			var dataType = null;
			if (data = evt.dataTransfer.getData('application/webtd+person')) {
				dataType = 'person';
			}
			else if (data = evt.dataTransfer.getData('application/webtd+seat')) {
				dataType = 'seat';
			}
			else {
				dataType = 'unknown';
			}

			if (dataType == 'person') {
				move_person_to(data, contest_id);
			}
			else {
				alert('got '+dataType+' '+data);
				return;
			}
		}
		contest_box_el.addEventListener('dragenter', onDragEnter);
		contest_box_el.addEventListener('dragover', onDragOver);
		contest_box_el.addEventListener('dragleave', onDragLeave);
		contest_box_el.addEventListener('drop', onDrop);
		
		$('.popup_menu_btn', contest_box_el).each(function(idx,el) {
			connect_popup_menu_btn(el);
			});
	}

	function setup_seat_box_handlers($p)
	{
		var el = $p.get(0);

		function handleDragStart(evt) {
			this.style.opacity = '0.4';
			evt.dataTransfer.effectAllowed = 'move';

			if (el.getAttribute('data-webtd-person')) {
				evt.dataTransfer.setData('application/webtd+person', el.getAttribute('data-webtd-person'));
			}
			else {
				evt.dataTransfer.setData('application/webtd+seat', el.getAttribute('data-webtd-seat'));
			}
		}
		el.addEventListener('dragstart', handleDragStart, false);
	}

	for (var i = 0; i < tables_sorted.length; i++) {
		var table_id = tables_sorted[i];
		var $tr = $('<tr></tr>');
		$tr.attr('data-webtd-table', table_id);

		for (var j = 0; j < rounds_sorted.length; j++) {
			var round_name = rounds_sorted[j];
			var $td = $('<td></td>');
			$td.attr('data-webtd-round', round_name);
			$tr.append($td);
		}

		$('.pairings_grid tr.sitout_row', container_el).before($tr);
	}

	function get_cell(round, table)
	{
		return $('.pairings_grid tr[data-webtd-table='+table+'] td[data-webtd-round='+round+']', container_el);
	}

	var SEAT_BOX_HTML = '<li class="seat_box" draggable="draggable"><img src="" class="seat_icon" style="display:none"><img src="images/person_icon.png" width="18" height="18"><span class="person_name"></span></li>';
	for (var i in assignments) {
		var a = assignments[i];
		var $a = $('.match_container.template',container_el).clone();
		$a.attr('data-webtd-contest', a.id);
		setup_contest_box_handlers($a.get(0));
		$a.removeClass('template');
		if (a.status) {
			$('.contest_status_icon',$a).attr('src', 'images/contest_'+a.status+'_icon.png');
			$('.contest_status_icon',$a).attr('alt', a.status);
			$('.contest_status_icon',$a).attr('title', a.status);
		}
		$('.round',$a).text(a.round);
		$('.table',$a).text(a.table);
		for (var j in a.players) {
			var pid = a.players[j].pid;
			var p = players[pid];
			var $p = $(SEAT_BOX_HTML);
			$p.attr('data-webtd-person', pid);

			var seat_name = a.players[j].seat;
			$p.attr('data-webtd-seat', seat_name);
			if (seat_name && !seat_name.match(/^\d+/)) {
				$('img.seat_icon', $p).
				attr('src', 'images/seat_'+seat_name+'_icon.png').
				attr('alt', seat_name).
				attr('title', seat_name).
				show();
			}
			if (pid) {
				$('.person_name',$p).text(p != null ? p.name : ("?"+pid));
			} else {
				$('.person_name',$p).text('(empty)');
			}
			$('.players_list',$a).append($p);

			setup_seat_box_handlers($p);
		}

		var $td = get_cell(a.round, a.table);
		$td.append($a);
	}

	$tr = $('.pairings_grid tr.sitout_row', container_el);
	for (var j = 0; j < rounds_sorted.length; j++) {
		var round_name = rounds_sorted[j];
		var $td = $('<td><div class="sitout_box"><div class="caption">SITOUT</div><ul class="players_list"></ul></div></td>');
		$td.attr('data-webtd-round', round_name);

		var busy = {};
		for (var k in assignments) {
			var a = assignments[k];
			if (a.round == round_name) {
				for (var q = 0; q < a.players.length; q++) {
					var pid = a.players[q].pid;
					busy[pid] = true;
				}
			}
		}

		var any_found = false;
		for (var pid in players) {
			var p = players[pid];
			if (p.status == 'ready' && !busy[pid]) {
				var $p = $(SEAT_BOX_HTML);
				$p.attr('data-webtd-person', pid);
				$('.person_name',$p).text(p.name);
				$('.players_list',$td).append($p);
				any_found = true;
			}
		}

		if (!any_found) {
			$('.sitout_box', $td).hide();
		}

		$tr.append($td);
	}
}

function handle_vocabulary(vocabulary)
{
	if (vocabulary.table == 'court') {
		$('.lbl_table').text('court');
		$('.lbl_Table').text('Court');
	}
}

$(function() {
	function onError(jqxhr, textStatus, errorThrown) { }
	function onSuccess(data) {
		if (data.vocabulary) {
			handle_vocabulary(data.vocabulary);
		}
		$('.pairings_container').each(function(idx,el) {
			load_pairings_into(data, el);
		});
	}

	if ($(".pairings_container").length) {
		$.ajax({
		url: 'assignments-data.js.php?tournament='+escape(webtd_tournament_id),
		dataType: 'json',
		error: onError,
		success: onSuccess
		});
	}
});

function edit_contest_clicked()
{
	var el = popup_menu_trigger_btn;
	while (el && !el.hasAttribute('data-webtd-contest')) {
		el = el.parentElement;
	}
	if (!el) { return false; }

	var contest_id = el.getAttribute('data-webtd-contest');
	location.href='contest.php?id='+escape(contest_id)+'&next_url='+escape(location.href);
}

function pairings_designer_onAjaxError(jqxhr, textStatus, errorThrown)
{
	alert("AJAX error: "+textStatus + ' ' + errorThrown);
}

function add_table_clicked()
{
	var el = popup_menu_trigger_btn;
	var round_id = null;
	while (el) {
		if (round_id == null && el.hasAttribute('data-webtd-round')) {
			round_id = el.getAttribute('data-webtd-round');
		}
		el = el.parentElement;
	}
	if (round_id == null) { return false; }

	var onSuccess = function(data) { location.reload(); };

	$.ajax({
		url: 'pairings.php?tournament='+escape(webtd_tournament_id),
		type: 'POST',
		data: {
			'action:add_table': '1',
			'first_round': round_id
			},
		dataType: 'text',
		error: pairings_designer_onAjaxError,
		success: onSuccess
		});
	return false;
}

function add_seat_clicked()
{
	var el = popup_menu_trigger_btn;
	while (el && !el.hasAttribute('data-webtd-contest')) {
		el = el.parentElement;
	}
	if (!el) { return false; }

	var contest_id = el.getAttribute('data-webtd-contest');

	var onSuccess = function(data) { location.reload(); };

	$.ajax({
		url: 'pairings.php?tournament='+escape(webtd_tournament_id),
		type: 'POST',
		data: {
			'action:add_seat': '1',
			'contest': contest_id
			},
		dataType: 'json',
		error: pairings_designer_onAjaxError,
		success: onSuccess
		});
	return false;
}

function remove_seat_clicked()
{
	var el = popup_menu_trigger_btn;
	while (el && !el.hasAttribute('data-webtd-contest')) {
		el = el.parentElement;
	}
	if (!el) { return false; }

	var contest_id = el.getAttribute('data-webtd-contest');
	var onSuccess = function(data) { location.reload(); };

	$.ajax({
		url: 'pairings.php?tournament='+escape(webtd_tournament_id),
		type: 'POST',
		data: {
			'action:remove_seat': '1',
			'contest': contest_id
			},
		dataType: 'json',
		error: pairings_designer_onAjaxError,
		success: onSuccess
		});
	return false;
}

function move_person_to(person_id, contest_id)
{
	var onSuccess = function(data) { location.reload(); };

	$.ajax({
		url: 'pairings.php?tournament='+escape(webtd_tournament_id),
		type: 'POST',
		data: {
			'action:assign_person_to_contest': '1',
			'person': person_id,
			'contest': contest_id
			},
		dataType: 'json',
		error: pairings_designer_onAjaxError,
		success: onSuccess
		});
	return false;
}
