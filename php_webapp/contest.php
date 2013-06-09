<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];
	$sql = "SELECT multi_game,multi_session,multi_round,current_session
		FROM tournament
		WHERE id=".db_quote($tournament_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");
	$tournament_info = array(
		multi_game => $row[0],
		multi_session => $row[1],
		multi_round => $row[2],
		current_session => $row[3]
		);
	$_REQUEST['session_num'] = $tournament_info['current_session'];
}
else if (isset($_GET['id'])) {
	$sql = "SELECT tournament,multi_game,multi_session,multi_round,
		session_num,round,board,
		game,scenario,status,
		started,finished,notes
		FROM contest c
		JOIN tournament t ON t.id=c.tournament
		WHERE c.id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Invalid contest id");
	$tournament_id = $row[0];
	$tournament_info = array(
		multi_game => $row[1],
		multi_session => $row[2],
		multi_round => $row[3]
		);

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['session_num'] = $row[4];
		$_REQUEST['round'] = $row[5];
		$_REQUEST['board'] = $row[6];
		$_REQUEST['game'] = $row[7];
		$_REQUEST['scenario'] = $row[8];
		$_REQUEST['status'] = $row[9];
		$_REQUEST['started'] = $row[10];
		$_REQUEST['finished'] = $row[11];
		$_REQUEST['notes'] = $row[12];
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

	$_REQUEST['started'] = parse_date_time($_REQUEST['started_date'],$_REQUEST['started_time']);
	$_REQUEST['finished'] = parse_date_time($_REQUEST['finished_date'],$_REQUEST['finished_time']);

	if (isset($_REQUEST['action:create_contest'])) {

		$sql = "INSERT INTO contest (tournament,session_num,round,board,game,scenario,status,started,finished,notes)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['session_num']).",
			".db_quote($_REQUEST['round']).",
			".db_quote($_REQUEST['board']).",
			".db_quote($_REQUEST['game']).",
			".db_quote($_REQUEST['scenario']).",
			".db_quote($_REQUEST['status']).",
			".db_quote($_REQUEST['started']).",
			".db_quote($_REQUEST['finished']).",
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
		if ($tournament_info['multi_session']=='Y') {
		$updates[] = "session_num=".db_quote($_REQUEST['session_num']);
		}
		if ($tournament_info['multi_game']=='Y') {
		$updates[] = "game=".db_quote($_REQUEST['game']);
		}
		$updates[] = "board=".db_quote($_REQUEST['board']);
		$updates[] = "status=".db_quote($_REQUEST['status']);
		if ($tournament_info['multi_round']=='Y') {
		$updates[] = "round=".db_quote($_REQUEST['round']);
		}
		$updates[] = "scenario=".db_quote($_REQUEST['scenario']);
		$updates[] = "notes=".db_quote($_REQUEST['notes']);
		$updates[] = "started=".db_quote($_REQUEST['started']);
		$updates[] = "finished=".db_quote($_REQUEST['finished']);

		$sql = "UPDATE contest
		SET ".implode(',',$updates)."
		WHERE id=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		$p_updates = array();
		foreach ($_POST as $k => $v) {
			if (preg_match('/^participant_(\d+)_(.*)$/', $k, $m)) {
				$cpid = $m[1];
				$field = $m[2];
				if (!isset($p_updates[$cpid])) {
					$p_updates[$cpid] = array();
				}
				$p_updates[$cpid][$field] = $v;
			}
		}
		foreach ($p_updates as $cpid => $cp_post) {
			$updates = array();
			foreach ($cp_post as $k => $v) {
				if ($k == 'player' || $k == 'seat' ||
				$k == 'turn_order' || $k == 'score' ||
				$k == 'placement')
				{
					$updates[] = "$k=".db_quote($v);
				}
			}
			if (count($updates)) {
				$sql = "UPDATE contest_participant
					SET ".implode(',',$updates)."
					WHERE id=".db_quote($cpid);
				mysqli_query($database, $sql);
			}
		}

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

split_datetime($_REQUEST['started'], $_REQUEST['started_date'], $_REQUEST['started_time']);
split_datetime($_REQUEST['finished'], $_REQUEST['finished_date'], $_REQUEST['finished_time']);

begin_page($_GET['id'] ? "Edit Game" : "New Game");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<?php if ($tournament_info['multi_session']=='Y') {?>
<tr>
<td><label for="session_num_entry">Session:</label></td>
<td><input type="text" id="session_num_entry" name="session_num" value="<?php h($_REQUEST['session_num'])?>"></td>
</tr>
<?php }//endif multi_session tournament?>
<?php if ($tournament_info['multi_round']=='Y') {?>
<tr>
<td><label for="round_entry">Round:</label></td>
<td><input type="text" id="round_entry" name="round" value="<?php h($_REQUEST['round'])?>"></td>
</tr>
<?php }//endif multi_round tournament?>
<tr>
<td><label for="board_entry">Board/Table No.:</label></td>
<td><input type="text" id="board_entry" name="board" value="<?php h($_REQUEST['board'])?>"></td>
</tr>
<tr>
<td><label for="started_date_entry">Start Date/Time:</label></td>
<td>
<label>Date: <input type="date" id="started_date_entry" name="started_date" value="<?php h($_REQUEST['started_date'])?>"></label>
<label>Time: <input type="time" name="started_time" value="<?php h($_REQUEST['started_time'])?>"></label>
</td>
</tr>
<tr>
<td><label for="finished_date_entry">Finish Date/Time:</label></td>
<td>
<label>Date: <input type="date" id="finished_date_entry" name="finished_date" value="<?php h($_REQUEST['finished_date'])?>"></label>
<label>Time: <input type="time" name="started_time" value="<?php h($_REQUEST['started_time'])?>"></label>
</td>
</tr>
<?php if ($tournament_info['multi_game']=='Y'){?>
<tr>
<td><label for="game_entry">Game:</label></td>
<td><input type="text" id="game_entry" name="game" value="<?php h($_REQUEST['game'])?>"></td>
</tr>
<?php }//endif multi_game tournament?>
<tr>
<td><label for="scenario_entry">Scenario:</label></td>
<td><input type="text" id="scenario_entry" name="scenario" value="<?php h($_REQUEST['scenario'])?>"></td>
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
<th>Seat</th>
<th>Turn Order</th>
<th>Score</th>
<th>Placement</th>
</tr>
<?php
	$sql = "SELECT cp.id AS id,
		p.id AS player_id,
		p.name AS player_name,
		r.prior_rating AS prior_rating,
		seat,turn_order,score,placement
		FROM contest_participant cp
		JOIN contest c ON c.id=cp.contest
		JOIN person p ON p.id=cp.player
		LEFT JOIN player_rating r ON r.id=p.id
			AND r.session_num=c.session_num
		WHERE contest=".db_quote($_GET['id'])."
		ORDER BY turn_order,player_name,cp.id";
	$query = mysqli_query($database, $sql);
	while ($row = mysqli_fetch_row($query)) {
		$cpid = $row[0];
		$url = "contest_participant.php?id=".urlencode($cpid)."&next_url=".urlencode($_SERVER['REQUEST_URI']);
		$player_id = $row[1];
		$player_name = $row[2];
		$prior_rating = $row[3];
		$seat = $row[4];
		$turn_order = $row[5];
		$score = $row[6];
		$placement = $row[7];
		$pre = 'participant_'.$cpid;
		?>
<tr>
<td class="player_col"><input type="text" name="<?php h($pre.'_player')?>" value="<?php h($player_name)?>" data-player_id="<?php h($player_id)?>" class="player_sel"></td>
<td class="seat_col"><input type="text" size="4" name="<?php h($pre.'_seat')?>" value="<?php h($seat)?>"></td>
<td class="turn_order_col"><input type="text" size="4" name="<?php h($pre.'_turn_order')?>" value="<?php h($turn_order)?>"></td>
<td class="score_col"><input type="text" size="4" name="<?php h($pre.'_score')?>" value="<?php h($score)?>"></td>
<td class="placement_col"><input type="text" size="4" name="<?php h($pre.'_placement')?>" value="<?php h($placement)?>"></td>
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
<?php
	include('list_roster.inc.php');
	?>

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
