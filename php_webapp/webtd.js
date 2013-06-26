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

$(function() {
	$('.popup_menu').each(function(idx,el) {
		$('ul', $(el)).menu();
		$('ul', $(el)).on('blur', function(evt) {
			$(el).hide();
			});
	});

	$('.popup_menu_btn').click(function(evt) {
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
