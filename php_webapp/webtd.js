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
