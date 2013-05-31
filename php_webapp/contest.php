<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];
}
else if (isset($_GET['id'])) {
	$sql = "SELECT tournament,
		game,board,status,started,finished,round
		FROM contest WHERE id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Invalid contest id");
	$tournament_id = $row[0];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['game'] = $row[1];
		$_REQUEST['board'] = $row[2];
		$_REQUEST['status'] = $row[3];
		$_REQUEST['started'] = $row[4];
		$_REQUEST['finished'] = $row[5];
		$_REQUEST['round'] = $row[6];
	}
}
else {
	die("Invalid query string");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:create_contest'])) {

		$sql = "INSERT INTO contest (tournament,game,board,status,round)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['game']).",
			".db_quote($_REQUEST['board']).",
			".db_quote($_REQUEST['status']).",
			".db_quote($_REQUEST['round'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));
		$contest_id = mysqli_insert_id($database);

		$url = "contest_participant.php?contest=".urlencode($contest_id)."&next_url=".urlencode($next_url);
		header("Location: $url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

begin_page($_GET['id'] ? "Edit Contest" : "New Contest");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="round_entry">Round:</label></td>
<td><input type="text" id="round_entry" name="round" value="<?php h($_REQUEST['round'])?>"></td>
</tr>
<tr>
<td><label for="board_entry">Board/Table No.:</label></td>
<td><input type="text" id="board_entry" name="board" value="<?php h($_REQUEST['board'])?>"></td>
</tr>
<tr>
<td><label for="game_entry">Game:</label></td>
<td><input type="text" id="game_entry" name="game" value="<?php h($_REQUEST['game'])?>"></td>
</tr>
<tr>
<td><label for="status_cb">Status:</label></td>
<td><?php select_widget(array(
		name => "status",
		value => $_REQUEST['status'],
		options => array(
			'' => "--select--",
			proposed => "Proposed",
			assigned => "Assigned",
			started => "Started",
			suspended => "Suspended",
			aborted => "Aborted",
			completed => "Completed"
			)
		)) ?></td>
</tr>
<?php
	if ($_GET['id']) {?>
<tr>
<td valign="top"><label>Participants:</label></td>
<td>
<table border="1">
<tr>
<th>Player</th>
<th>Score</th>
<th>Placement</th>
</tr>
<?php
	$sql = "SELECT cp.id AS id,
		p.name AS player_name,
		score,placement
		FROM contest_participant cp
		JOIN player p ON p.id=cp.player
		WHERE contest=".db_quote($_GET['id'])."
		ORDER BY turn_order,player_name,cp.id";
	$query = mysqli_query($database, $sql);
	while ($row = mysqli_fetch_row($query)) {
		$cpid = $row[0];
		$url = "contest_participant.php?id=".urlencode($cpid)."&next_url=".urlencode($_SERVER['REQUEST_URI']);
		$player_name = $row[1];
		$score = $row[2];
		$placement = $row[3];
		?>
<tr>
<td class="player_col"><a href="<?php h($url)?>"><?php h($player_name)?></a></td>
<td class="score_col"><?php h($score)?></td>
<td class="placement_col"><?php h($placement)?></td>
</tr>
<?php
	} // end foreach participant
	?>
</table>
<?php
	$add_participant_url = "contest_participant.php?contest=".urlencode($_GET['id'])
		."&next_url=".urlencode($_SERVER['REQUEST_URI']);
	?>
<div><a href="<?php h($add_participant_url)?>">Add Participant</a></div>
</td>
<?php
	} // endif contest id known
	?>
</table>

<div class="form_buttons_bar">
<?php if ($_GET['id']) { ?>
<button type="submit" name="action:update_contest">Update Contest</button>
<button type="submit" name="action:delete_contest">Delete Contest</button>
<?php } else { ?>
<button type="submit" name="action:create_contest">Create Contest</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
