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
$current_session = $row[1];
$tournament_info = array(
	'id' => $tournament_id,
	'name' => $row[0],
	'current_session' => $row[1],
	'last_round' => ($row[2] ?: 0)
	);

function add_table_to_round($round, $new_table_id)
{
	global $database;
	global $tournament_id;
	global $current_session;

	$sql = "INSERT INTO contest (tournament,session_num,round,board,status)
		VALUES (
		".db_quote($tournament_id).",
		".db_quote($current_session).",
		".db_quote($round).",
		".db_quote($new_table_id).",
		'proposed')";
	mysqli_query($database, $sql)
		or die("SQL error 2: ".db_error($database));

	$contest_id = mysqli_insert_id($database);

	$sql = "INSERT INTO contest_participant (contest,turn_order)
		VALUES (".db_quote($contest_id).",1),
		       (".db_quote($contest_id).",2)
		";
	mysqli_query($database, $sql)
		or die("SQL error 3: ".db_error($database));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if (isset($_REQUEST['action:cancel'])) {
		$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);
		header("Location: $next_url");
		exit();
	}

	if (isset($_REQUEST['action:mutate_matching'])) {
		$m = load_matching($tournament_id, $current_session);
		$m = mutate_matching($m);
		save_matching($m);
		$url = $_SERVER['REQUEST_URI'];
		header("Location: $url");
		exit();
	}

	if (isset($_REQUEST['action:reset_matching'])) {

		$contests_sql = "
			tournament=".db_quote($tournament_id)."
			AND status='proposed'
			AND session_num=".db_quote($current_session);

		$sql = "DELETE FROM contest_participant
			WHERE contest IN (SELECT id FROM contest WHERE $contests_sql)";
		mysqli_query($database, $sql)
			or die("SQL error:".db_error($sql));

		$sql = "DELETE FROM contest
			WHERE $contests_sql";
		mysqli_query($database, $sql)
			or die("SQL error:".db_error($sql));

		$url = $_SERVER['REQUEST_URI'];
		header("Location: $url");
		exit();
	}

	if (isset($_REQUEST['action:add_seat'])) {
		$contest_id = $_REQUEST['contest'];

		$sql = "SELECT MAX(turn_order) FROM contest_participant
			WHERE contest=".db_quote($contest_id);
		$query = mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$row = mysqli_fetch_row($query);

		$next_turn_order = ($row[0] ?: 0) + 1;

		$sql = "INSERT INTO contest_participant
			(contest,turn_order) VALUES (
				".db_quote($contest_id).",
				".db_quote($next_turn_order).")";
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		echo '{"status":"success"}';
		exit();
	}

	if (isset($_REQUEST['action:remove_seat'])) {
		$contest_id = $_REQUEST['contest'];

		$sql = "SELECT id FROM contest_participant
			WHERE contest=".db_quote($contest_id)."
			ORDER BY CASE WHEN player IS NULL THEN 0 ELSE 1 END ASC,
			turn_order DESC,
			id DESC
			LIMIT 1";
		$query = mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$row = mysqli_fetch_row($query);

		if ($row) {
			$sql = "DELETE FROM contest_participant
				WHERE id=".db_quote($row[0]);
			mysqli_query($database, $sql)
				or die("SQL error: ".db_error($database));
		}

		echo '{"status":"success"}';
		exit();
	}

	if (isset($_REQUEST['action:add_table'])) {
		$round = $_REQUEST['first_round'];

		// determine what table numbers already exist in the
		// selected starting round
		$sql = "SELECT DISTINCT board
			FROM contest
			WHERE tournament=".db_quote($tournament_id)."
			AND session_num=".db_quote($current_session)."
			AND round=".db_quote($round);
		$query = mysqli_query($database, $sql)
			or die("SQL error 1: ".db_error($database));
		$seen_table_numbers = array();
		while ($row = mysqli_fetch_row($query)) {
			$seen_table_numbers[$row[0]] = TRUE;
		}

		// select next table number
		$new_table_id = 1;
		while ($seen_table_numbers["".$new_table_id]) {
			$new_table_id++;
		}

		add_table_to_round($round, $new_table_id);

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

$page_title = "$tournament_info[name] - Pairings";
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
<li><a href="#" onclick="add_table_clicked()">add table</a></li>
</ul>
</div>
<div class="pairings_container">
<table class="pairings_grid">
<tr class="sitout_row"></tr>
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
<!--
<div>Min game size:
<input type="text" size="4" name="min_game_size" value="<?php h($_REQUEST['min_game_size'])?>">
Max game size:
<input type="text" size="4" name="max_game_size" value="<?php h($_REQUEST['max_game_size'])?>">
</div>
-->
<div>
<button type="submit" name="action:generate_pairings">Initialize</button>
<button type="submit" name="action:mutate_matching">Mutate (One Step)</button>
<button type="submit" name="action:optimize_matching">Optimize</button>
<button type="submit" name="action:reset_matching">Reset</button>
<button type="submit" name="action:cancel">Go Back</button>
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
	if ($game->locked) { continue; }
	?><tr>
<td><?php h("Table ".$game->round."-".$game->board)?></td>
<td><ul class="player_inline_list"><?php
	foreach ($game->seats as $seat) {
		$p = $players[$seat->player];
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


for ($round_no = $_REQUEST['first_round']; $round_no <= $_REQUEST['last_round']; $round_no++) {

	// check whether any table exists for this round
	$sql = "SELECT COUNT(*) FROM contest
		WHERE tournament=".db_quote($tournament_id)."
		AND session_num=".db_quote($current_session)."
		AND round=".db_quote($round_no);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query);
	if ($row[0] == 0) {

		// add a table to this round
		add_table_to_round($round_no, 1);
	}
}

$m = load_matching($tournament_id, $current_session);

echo "(2)Number of games: ".count($m['assignments'])."<br>\n";
$m = initialize_matching($m);
echo "(3)Number of games: ".count($m['assignments'])."<br>\n";
//$matching = optimize_matching($m);
//show_matching($matching);
save_matching($m);
echo "(4)Number of games: ".count($m['assignments'])."<br>\n";

} //endif action:generate_pairings

if (isset($_REQUEST['action:optimize_matching'])) {

	$m = load_matching($tournament_id, $current_session);
	$m = optimize_matching($m);
	save_matching($m);

	show_matching($m);
}


//$m = mutate_matching($matching);
//show_matching($m);

end_page();
