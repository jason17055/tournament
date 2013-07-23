<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/pairing_functions.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,current_session,
	(SELECT MAX(round) FROM contest
		WHERE tournament=t.id
		AND session_num=t.current_session
		AND status <> 'proposed')
	FROM tournament t WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	'id' => $tournament_id,
	'name' => $row[0],
	'current_session' => $row[1],
	'last_round' => ($row[2] ?: 0)
	);

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if (isset($_REQUEST['action:cancel'])) {
		$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);
		header("Location: $next_url");
		exit();
	}

	if (isset($_REQUEST['action:add_seat'])) {
		$contest_id = $_REQUEST['contest'];

		$sql = "INSERT INTO contest_participant
			(contest) VALUES (".db_quote($contest_id).")";
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		echo '{"status":"success"}';
		exit();
	}

	if (isset($_REQUEST['action:assign_person_to_contest'])) {
		$person_id = $_REQUEST['person'];
		$contest_id = $_REQUEST['contest'];

		// TODO-unassign from any other contest that is during same
		// round 

		$sql = "INSERT INTO contest_participant
			(contest,player) VALUES (
			".db_quote($contest_id).",
			".db_quote($person_id).")";
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		echo '{"status":"success"}';
		exit();
	}
}

$page_title = "$tournament_info[name] - Generate Pairings";
begin_page($page_title);

if (!isset($_REQUEST['first_round'])) {
	$_REQUEST['first_round'] = ($tournament_info['last_round']+1);
}
if (!isset($_REQUEST['last_round'])) {
	$_REQUEST['last_round'] = $_REQUEST['first_round'];
}
if (!isset($_REQUEST['min_game_size'])) {
	$_REQUEST['min_game_size'] = 2;
}
if (!isset($_REQUEST['max_game_size'])) {
	$_REQUEST['max_game_size'] = 4;
}

?>
<div class="popup_menu" id="contest_popup_menu">
<ul>
<li><a href="#" onclick="edit_contest_clicked()">edit</a></li>
<li><a href="#" onclick="add_seat_clicked()">add seat</a></li>
<li><a href="#" onclick="remove_seat_clicked()">remove seat</a></li>
</ul>
</div>
<div class="pairings_container">
<table class="pairings_grid">
</table>
<div class="match_container template">
<div class="caption">
Round: <span class="round"></span>
Table: <span class="table"></span>
<button type="button" style="margin: -2pt" class="popup_menu_btn" data-for="contest_popup_menu">...</button>
</div>
<ul class="players_list">
</ul>
</div><!-- /.match_container.template -->
</div><!-- /.pairings_container -->

<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<div>First round to pair:
<input type="text" size="4" name="first_round" value="<?php h($_REQUEST['first_round'])?>">
Last round to pair:
<input type="text" size="4" name="last_round" value="<?php h($_REQUEST['last_round'])?>">
</div>
<div>Min game size:
<input type="text" size="4" name="min_game_size" value="<?php h($_REQUEST['min_game_size'])?>">
Max game size:
<input type="text" size="4" name="max_game_size" value="<?php h($_REQUEST['max_game_size'])?>">
</div>
<div>
<button type="submit" name="action:generate_pairings">GO</button>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php

function show_matching(&$matching)
{
$players = &$matching['players'];

usort($matching['assignments'], 'order_by_round_and_board');
?>
<table border="1" style="float:left; margin-right: 2em">
<caption>Fitness : <?php h(sprintf('%.4f',$matching['fitness']))?></caption>
<tr>
<th>Table</th>
<th>Players</th>
</tr>
<?php
foreach ($matching['assignments'] as $game) {
	if ($game['locked']) { continue; }
	?><tr>
<td><?php h("Table $game[round]-$game[board]")?></td>
<td><ul class="player_inline_list"><?php
	foreach ($game['players'] as $pid) {
		$p = $players[$pid];
		?><li><span class="player_name" data-player-id="<?php h($pid)?>"><?php h($p['name'] ?: $pid)?></span></li>
<?php
	}
	?></ul></td>
</tr>
<?php
}//end foreach table
?>
</table>
<table border="1">
<tr><th>Penalty</th><th>Points</th></tr>
<?php
	foreach ($matching['penalties'] as $pen_key => $pen_val) {
	?><tr>
	<td><?php h($pen_key)?></td>
	<td align="right"><?php h(sprintf('%.0f',$pen_val))?></td>
	</tr>
	<?php
	}
	?>
</table>
<div style="clear:both"></div>

<?php
} //end show_matching()

if (isset($_REQUEST['action:generate_pairings'])) {

$m = load_matching($tournament_id, $tournament_info['current_session']);

$matching = optimize_matching($m);
show_matching($matching);
propose_matching($matching);

} //endif action:generate_pairings

//$m = mutate_matching($matching);
//show_matching($m);

end_page();
