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
		'multi_game' => $row[0],
		'multi_session' => $row[1],
		'multi_round' => $row[2],
		'current_session' => $row[3]
		);

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['session_num'] = $tournament_info['current_session'];
		$_REQUEST['round'] = "";
		$_REQUEST['board'] = "";
		$_REQUEST['game'] = "";
		$_REQUEST['scenario'] = "";
		$_REQUEST['status'] = "";
		$_REQUEST['started'] = strftime('%Y-%m-%d', time());
		$_REQUEST['finished'] = "";
		$_REQUEST['notes'] = "";
	}
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
		'multi_game' => $row[1],
		'multi_session' => $row[2],
		'multi_round' => $row[3]
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

$can_edit = is_director($tournament_id);

function update_contest_participants($contest_id)
{
	global $database;

		$p_updates = array();
		foreach ($_POST as $k => $v) {
			if (preg_match('/^participant_(_?\d+)_(.*)$/', $k, $m)) {
				$cpid = $m[1];
				$field = $m[2];
				if (!isset($p_updates[$cpid])) {
					$p_updates[$cpid] = array();
				}
				$p_updates[$cpid][$field] = $v;
			}
		}
		foreach ($p_updates as $cpid => $cp_post) {

			if (isset($cp_post['delete'])) {
				$sql = "DELETE FROM contest_participant
					WHERE id=".db_quote($cpid)."
					AND contest=".db_quote($contest_id);
				mysqli_query($database, $sql)
					or die("SQL error: ".db_error($database));
				continue;
			}

			$updates = array();
			$count_nonempty = 0;
			foreach ($cp_post as $k => $v) {
				if ($k == 'player' || $k == 'seat' ||
				$k == 'turn_order' || $k == 'score' ||
				$k == 'placement')
				{
					$updates[] = "$k=".db_quote($v);
					if (strlen($v)) { $count_nonempty++; }
				}
				else if ($k == 'commit') {
					//handled below
				}
				else {
					die("unrecognized participant field : $k");
				}
			}

			if (count($updates) == 0) {
				continue;
			}

			$updates[] = "status=".db_quote($cp_post['commit']?'C':NULL);

			if (preg_match('/^(\d+)$/', $cpid, $m)) {
				$sql = "UPDATE contest_participant
					SET ".implode(',',$updates)."
					WHERE id=".db_quote($cpid)."
					AND contest=".db_quote($contest_id);
				mysqli_query($database, $sql);
			}
			else if ($count_nonempty) {
				array_unshift($updates, "contest=".db_quote($contest_id));
				$sql = "INSERT INTO contest_participant
					SET ".implode(',',$updates);
				mysqli_query($database, $sql)
					or die("SQL error: ".db_error($database));
			}
		}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	if (!$can_edit) {
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

		update_contest_participants($contest_id);

		header("Location: $next_url");
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

		update_contest_participants($_GET['id']);

		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:delete_contest'])) {
		$sql = "DELETE FROM contest_participant
			WHERE contest=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$sql = "DELETE FROM contest WHERE id=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

split_datetime($_REQUEST['started'], $_REQUEST['started_date'], $_REQUEST['started_time']);
split_datetime($_REQUEST['finished'], $_REQUEST['finished_date'], $_REQUEST['finished_time']);

begin_page(isset($_GET['id']) ? "Edit Game" : "New Game");

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
		'name' => "status",
		'value' => $_REQUEST['status'],
		'options' => array(
			'' => "--select--",
			'proposed' => "Proposed",
			'assigned' => "Assigned",
			'started' => "Started",
			'suspended' => "Suspended",
			'aborted' => "Aborted",
			'completed' => "Completed"
			),
		'read_only' => !$can_edit
		)) ?></td>
</tr>
<tr>
<td valign="top"><label for="notes_entry">Notes:</label></td>
<td><textarea name="notes" rows="4" cols="60"><?php h($_REQUEST['notes'])?></textarea></td>
</tr>
<tr>
<td valign="top"><label>Participants:</label></td>
<td>
<?php if ($can_edit) { ?>
<table id="participants_table" class="tabular_form">
<tr>
<th class="commit_col">Commit</th>
<th class="player_col">Player</th>
<th class="seat_col">Seat</th>
<th class="turn_order_col">Turn Order</th>
<th class="score_col">Score</th>
<th class="placement_col">Placement</th>
</tr>
<?php
if (isset($_GET['id'])) {
	$sql = "SELECT cp.id AS id,
		p.id AS player_id,
		p.name AS player_name,
		r.prior_rating AS prior_rating,
		seat,turn_order,score,placement,
		cp.status
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
		$status = $row[8];
		$commit = ($status == 'C');
		$pre = 'participant_'.$cpid;
		?>
<tr data-rowid="<?php h($cpid)?>">
<td class="commit_col">
<input type="checkbox" name="<?php h($pre."_commit")?>"<?php echo($commit ? ' checked="checked"':'')?>>
</td>
<td class="player_col"><input type="text" name="<?php h($pre.'_player')?>" value="<?php h($player_name)?>" data-player_id="<?php h($player_id)?>" class="player_sel"></td>
<td class="seat_col"><input type="text" size="4" name="<?php h($pre.'_seat')?>" value="<?php h($seat)?>"></td>
<td class="turn_order_col"><input type="text" size="4" name="<?php h($pre.'_turn_order')?>" value="<?php h($turn_order)?>"></td>
<td class="score_col"><input type="text" size="4" name="<?php h($pre.'_score')?>" value="<?php h($score)?>"></td>
<td class="placement_col"><input type="text" size="4" name="<?php h($pre.'_placement')?>" value="<?php h($placement)?>"></td>
<td class="actions_col"><button type="button" class="delete_row_btn" title="Delete this participant"><img src="images/red_cross.png" alt="Delete"></button></td>
</tr>
<?php
	} // end foreach participant
} //end if existing contest
	?>
<tr id="new_participant_row" class="template">
<td class="commit_col">
<input type="checkbox" name="_commit" checked="checked">
</td>
<td class="player_col"><input type="text" name="_player" class="player_sel"></td>
<td class="seat_col"><input type="text" size="4" name="_seat"></td>
<td class="turn_order_col"><input type="text" size="4" name="_turn_order"></td>
<td class="score_col"><input type="text" size="4" name="_score"></td>
<td class="placement_col"><input type="text" size="4" name="_placement"></td>
<td class="actions_col"><button type="button" class="delete_row_btn" title="Delete this participant"><img src="images/red_cross.png" alt="Delete"></button></td>
</tr>
</table>
<?php
	if ($can_edit) {
		if (isset($_GET['id'])) {
			$add_participant_url = "contest_participant.php?contest=".urlencode($_GET['id'])
		."&next_url=".urlencode($_SERVER['REQUEST_URI']);
		} else {
			$add_participant_url = "#";
		}
	?>
<div><a href="#<?php h($add_participant_url)?>" id="add_participant_link">Add Participant</a></div>
	<?php } //endif is_director ?>
<?php
	} // endif can_edit
	else { ?>
<table id="participants_table" class="tabular">
<tr>
<th class="player_col">Player</th>
<th class="rating_col">Rating</th>
<th class="score_col">Score</th>
<th class="placement_col">Placement</th>
<th class="performance_col">P Points</th>
</tr>
<?php
	$sql = "SELECT cp.id AS id,
		p.id AS player_id,
		p.name AS player_name,
		r.prior_rating AS prior_rating,
		seat,turn_order,score,placement,
		performance
		FROM contest_participant cp
		JOIN contest c ON c.id=cp.contest
		JOIN person p ON p.id=cp.player
		LEFT JOIN player_rating r ON r.id=p.id
			AND r.session_num=c.session_num
		WHERE contest=".db_quote($_GET['id'])."
		ORDER BY placement,turn_order,player_name,cp.id";
	$query = mysqli_query($database, $sql);
	while ($row = mysqli_fetch_row($query)) {
		$cpid = $row[0];
		$player_id = $row[1];
		$p_url = "player_scorecard.php?id=".urlencode($player_id)."&next_url=".urlencode($_SERVER['REQUEST_URI']);
		$player_name = $row[2];
		$prior_rating = $row[3];
		$seat = $row[4];
		$turn_order = $row[5];
		$score = $row[6];
		$placement = $row[7];
		$performance = $row[8];

		$pre = 'participant_'.$cpid;
		?>
<tr data-rowid="<?php h($cpid)?>">
<td class="player_col"><a href="<?php h($p_url)?>"><?php h($player_name)?></a></td>
<td class="rating_col"><?php h(sprintf('%.0f', $prior_rating))?></td>
<td class="score_col"><?php h($score)?></td>
<td class="placement_col"><?php h($placement)?></td>
<td class="performance_col"><?php if (!is_null($performance)) { h(sprintf('%.3f', $performance)); } ?></td>
</tr>
<?php
	} // end foreach participant
?>
</table>
<?php
	} // end if !can_edit
	?>
</td>
</tr>
</table>
<?php
	include('list_roster.inc.php');
	?>

<?php if ($can_edit) { ?>
<div class="form_buttons_bar">
<?php if (isset($_GET['id'])) { ?>
<button type="submit" name="action:update_contest">Update Game</button>
<button type="submit" name="action:delete_contest"
	onclick="return confirm('Really delete this game?')">Delete Game</button>
<?php } else { ?>
<button type="submit" name="action:create_contest">Create Game</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
<?php } else {
	$go_back_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);
?>
<p>
<a href="<?php h($go_back_url)?>">Go Back</a>
</p>
<?php } //endif !can_edit ?>
</form>

<?php
end_page();
