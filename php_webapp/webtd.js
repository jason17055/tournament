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
	return false;
}

$(function() {
	$('#add_participant_link').click(on_add_participant_clicked);
});
