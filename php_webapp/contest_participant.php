<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

if (isset($_GET['contest'])) {
	$contest_id = $_GET['contest'];
	$sql = "SELECT tournament,
		CONCAT(c.round, '-', c.board) AS contest_name
		FROM contest c WHERE id=".db_quote($contest_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");
	$tournament_id = $row[0];
	$contest_name = $row[1];
}
else if (isset($_GET['id'])) {
	$sql = "SELECT cp.contest AS contest,
		c.tournament AS tournament,
		CONCAT(c.round, '-', c.board) AS contest_name,
		player,seat,turn_order,score,placement,
		p.name AS player_name
		FROM contest_participant cp
		JOIN contest c ON c.id=cp.contest
		LEFT JOIN person p ON p.id=cp.player
		WHERE cp.id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");
	$contest_id = $row[0];
	$tournament_id = $row[1];
	$contest_name = $row[2];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['player'] = $row[3];
		$_REQUEST['seat'] = $row[4];
		$_REQUEST['turn_order'] = $row[5];
		$_REQUEST['score'] = $row[6];
		$_REQUEST['placement'] = $row[7];
		$_REQUEST['player_name'] = $row[8];
	}
}
else {
	die("Invalid query string");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = $_REQUEST['next_url'] ?: 'contest.php?tournament='.urlencode($contest_id);

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	if (!is_director($tournament_id)) {
		die("Not authorized.");
	}

	if (isset($_REQUEST['action:create_contest_participant'])) {

		$sql = "INSERT INTO contest_participant (contest,player,seat,turn_order,score,placement)
			VALUES (
			".db_quote($contest_id).",
			".db_quote($_REQUEST['player']).",
			".db_quote($_REQUEST['seat']).",
			".db_quote($_REQUEST['turn_order']).",
			".db_quote($_REQUEST['score']).",
			".db_quote($_REQUEST['placement'])."
			)";
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$contest_participant_id = mysqli_insert_id($database);

		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:update_contest_participant'])) {

		$sql = "UPDATE contest_participant
			SET player=".db_quote($_REQUEST['player']).",
			seat=".db_quote($_REQUEST['seat']).",
			turn_order=".db_quote($_REQUEST['turn_order']).",
			score=".db_quote($_REQUEST['score']).",
			placement=".db_quote($_REQUEST['placement'])."
			WHERE id=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

begin_page($_GET['id'] ? "Edit Contest Participant" : "New Contest Participant");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td>Contest:</td>
<td><?php h($contest_name)?></td>
</tr>
<tr>
<td><label for="player_cb">Player:</label></td>
<td><input id="player_cb" name="player" value="<?php h($_REQUEST['player_name'])?>" data-player_id="<?php h($_REQUEST['player'])?>" class="player_sel">
<?php
	include('list_roster.inc.php');
	?>
</tr>
</tr>
<tr>
<td><label for="seat_entry">Seat:</label></td>
<td><input type="text" id="seat_entry" name="seat" value="<?php h($_REQUEST['seat'])?>"></td>
</tr>
<tr>
<td><label for="turn_order_entry">Turn Order:</label></td>
<td><input type="text" id="turn_order_entry" name="turn_order" value="<?php h($_REQUEST['turn_order'])?>"></td>
</tr>
<tr>
<td><label for="score_entry">Score:</label></td>
<td><input type="text" id="score_entry" name="score" value="<?php h($_REQUEST['score'])?>"></td>
</tr>
<tr>
<td><label for="placement_entry">Placement:</label></td>
<td><input type="text" id="placement_entry" name="placement" value="<?php h($_REQUEST['placement'])?>"></td>
</tr>
</table>

<div class="form_buttons_bar">
<?php if ($_GET['id']) { ?>
<button type="submit" name="action:update_contest_participant">Update Participant</button>
<button type="submit" name="action:delete_contest_participant">Delete Participant</button>
<?php } else { ?>
<button type="submit" name="action:create_contest_participant">Create Participant</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
