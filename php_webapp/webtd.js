function create_player_sel_box(idx, el)
{
	var $text_entry = $(el);
	var $hidden_el = $('<input type="hidden">');
	var el_name = $text_entry.attr('name');

	$hidden_el.attr('name', el_name);
	$hidden_el.attr('value', $text_entry.attr('data-player_id'));

	$text_entry.removeAttr('name');

	$text_entry.after($hidden_el);
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
