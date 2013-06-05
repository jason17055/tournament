<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];
	$sql = "SELECT multi_game,multi_session,current_session FROM tournament
		WHERE id=".db_quote($tournament_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");
	$tournament_info = array(
		multi_game => $row[0],
		multi_session => $row[1],
		current_session => $row[2]
		);
	$_REQUEST['session_num'] = $tournament_info['current_session'];
}
else if (isset($_GET['id'])) {
	$sql = "SELECT tournament,multi_game,multi_session,
		game,board,status,started,finished,round,session_num,notes
		FROM contest c
		JOIN tournament t ON t.id=c.tournament
		WHERE c.id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Invalid contest id");
	$tournament_id = $row[0];
	$tournament_info = array(
		multi_game => $row[1],
		multi_session => $row[2]
		);

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['game'] = $row[3];
		$_REQUEST['board'] = $row[4];
		$_REQUEST['status'] = $row[5];
		$_REQUEST['started'] = $row[6];
		$_REQUEST['finished'] = $row[7];
		$_REQUEST['round'] = $row[8];
		$_REQUEST['session_num'] = $row[9];
		$_REQUEST['notes'] = $row[10];
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

	if (!is_director($tournament_id)) {
		die("Not authorized.");
	}

	if (isset($_REQUEST['action:create_contest'])) {

		$sql = "INSERT INTO contest (tournament,game,board,status,round,session_num,notes)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['game']).",
			".db_quote($_REQUEST['board']).",
			".db_quote($_REQUEST['status']).",
			".db_quote($_REQUEST['round']).",
			".db_quote($_REQUEST['session_num']).",
			".db_quote($_REQUEST['notes'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));
		$contest_id = mysqli_insert_id($database);

		$self_url = "contest.php?id=".urlencode($contest_id);
		if (isset($_REQUEST['next_url'])) {
			$self_url .= '&next_url='.urlencode($_REQUEST['next_url']);
		}
		$url = "contest_participant.php?contest=".urlencode($contest_id)."&next_url=".urlencode($self_url);
		header("Location: $url");
		exit();
	}

	else if (isset($_REQUEST['action:update_contest'])) {

		$updates = array();
		if ($tournament_info['multi_game']=='Y') {
		$updates[] = "game=".db_quote($_REQUEST['game']);
		}
		$updates[] = "board=".db_quote($_REQUEST['board']);
		$updates[] = "status=".db_quote($_REQUEST['status']);
		$updates[] = "round=".db_quote($_REQUEST['round']);
		$updates[] = "session_num=".db_quote($_REQUEST['session_num']);
		$updates[] = "notes=".db_quote($_REQUEST['notes']);

		$sql = "UPDATE contest
		SET ".implode(',',$updates)."
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

begin_page($_GET['id'] ? "Edit Game" : "New Game");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="session_num_entry">Session:</label></td>
<td><input type="text" id="session_num_entry" name="session_num" value="<?php h($_REQUEST['session_num'])?>"></td>
</tr>
<tr>
<td><label for="round_entry">Round:</label></td>
<td><input type="text" id="round_entry" name="round" value="<?php h($_REQUEST['round'])?>"></td>
</tr>
<tr>
<td><label for="board_entry">Board/Table No.:</label></td>
<td><input type="text" id="board_entry" name="board" value="<?php h($_REQUEST['board'])?>"></td>
</tr>
<?php if ($tournament_info['multi_game']=='Y'){?>
<tr>
<td><label for="game_entry">Game:</label></td>
<td><input type="text" id="game_entry" name="game" value="<?php h($_REQUEST['game'])?>"></td>
</tr>
<?php }//endif multi_game tournament?>
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
<tr>
<td valign="top"><label for="notes_entry">Notes:</label></td>
<td><textarea name="notes" rows="4" cols="60"><?php h($_REQUEST['notes'])?></textarea></td>
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
		JOIN person p ON p.id=cp.player
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
	if (is_director($tournament_id)) {
	$add_participant_url = "contest_participant.php?contest=".urlencode($_GET['id'])
		."&next_url=".urlencode($_SERVER['REQUEST_URI']);
	?>
<div><a href="<?php h($add_participant_url)?>">Add Participant</a></div>
	<?php } //endif is_director ?>
</td>
<?php
	} // endif contest id known
	?>
</table>

<div class="form_buttons_bar">
<?php if ($_GET['id']) { ?>
<button type="submit" name="action:update_contest">Update Game</button>
<button type="submit" name="action:delete_contest">Delete Game</button>
<?php } else { ?>
<button type="submit" name="action:create_contest">Create Game</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
