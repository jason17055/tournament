<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');
require_once('includes/form.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];
	$sql = "SELECT multi_game,multi_session,multi_round,current_session,vocab_table,
		(SELECT MIN(id) FROM game_definition WHERE tournament=t.id) AS default_game,
		multi_venue,use_teams
		FROM tournament t
		WHERE id=".db_quote($tournament_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");
	$tournament_info = array(
		'multi_game' => $row[0],
		'multi_session' => $row[1],
		'multi_round' => $row[2],
		'current_session' => $row[3],
		'vocab_table' => $row[4],
		'default_game' => $row[5],
		'multi_venue' => $row[6]=='Y',
		'use_teams' => $row[7]=='Y'
		);

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		default_form_property('venue', '');
		default_form_property('starts', '');
		$_REQUEST['session_num'] = $tournament_info['current_session'];
		$_REQUEST['round'] = "";
		$_REQUEST['game'] = $tournament_info['default_game'];
		$_REQUEST['scenario'] = "";
		$_REQUEST['status'] = "";
		$_REQUEST['started'] = strftime('%Y-%m-%d', time());
		$_REQUEST['finished'] = "";
		$_REQUEST['notes'] = "";
	}
}
else if (isset($_GET['id'])) {
	$sql = "SELECT c.tournament,multi_game,multi_session,multi_round,
		session_num,round,venue,
		game,scenario,status,
		starts,started,finished,
		notes,
		vocab_table,multi_venue,use_teams
		FROM contest c
		JOIN tournament t ON t.id=c.tournament
		WHERE c.id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$row = mysqli_fetch_row($query)
		or die("Invalid contest id");
	$tournament_id = $row[0];
	$tournament_info = array(
		'multi_game' => $row[1],
		'multi_session' => $row[2],
		'multi_round' => $row[3],
		'vocab_table' => $row[14],
		'multi_venue' => $row[15]=='Y',
		'use_teams' => $row[16]=='Y'
		);

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['session_num'] = $row[4];
		$_REQUEST['round'] = $row[5];
		$_REQUEST['venue'] = $row[6];
		$_REQUEST['game'] = $row[7];
		$_REQUEST['scenario'] = $row[8];
		$_REQUEST['status'] = $row[9];
		$_REQUEST['starts'] = $row[10];
		$_REQUEST['started'] = $row[11];
		$_REQUEST['finished'] = $row[12];
		$_REQUEST['notes'] = $row[13];
	}
}
else {
	die("Invalid query string");
}

$can_edit = is_director($tournament_id);

function update_contest_participants($contest_id)
{
	global $database;

	// get game definition, if available
	$game_definition = array();
	$sql = "SELECT seat_names
		FROM contest c
		JOIN game_definition gd
			ON gd.id=c.game
		WHERE c.id=".db_quote($contest_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query);
	if ($row) {
		$game_definition['seat_names'] = $row[0];
	}

	$p_updates = array();
	foreach ($_POST as $k => $v) {
		if (preg_match('/^participant_([_m]?\d+)_(.*)$/', $k, $m)) {
			$cpid = $m[1];
			$field = $m[2];
			if (!isset($p_updates[$cpid])) {
				$p_updates[$cpid] = array();
			}
			$p_updates[$cpid][$field] = $v;
		}
	}

	$seen_seats = array();
	foreach ($p_updates as $cpid => $cp_post) {

		if (isset($cp_post['delete'])) {
			$sql = "DELETE FROM contest_participant
				WHERE id=".db_quote($cpid)."
				AND contest=".db_quote($contest_id);
			mysqli_query($database, $sql)
				or die("SQL error: ".db_error($database));
			continue;
		}

		if ($cp_post['seat']) {
			$seen_seats[$cp_post['seat']] = $cpid;
		}

		$updates = array();
		$count_nonempty = 0;
		foreach ($cp_post as $k => $v) {
			if ($k == 'player' || $k == 'seat' ||
			$k == 'turn_order' || $k == 'score' ||
			$k == 'placement' || $k = 'participant_status')
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

	// TODO- delete empty, invalid seats...
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

	$_REQUEST['starts'] = parse_date_time($_REQUEST['starts_date'],$_REQUEST['starts_time']);
	$_REQUEST['started'] = parse_date_time($_REQUEST['started_date'],$_REQUEST['started_time']);
	$_REQUEST['finished'] = parse_date_time($_REQUEST['finished_date'],$_REQUEST['finished_time']);

	if (isset($_REQUEST['action:create_contest'])) {

		mysqli_autocommit($database, FALSE);

		$sql = "INSERT INTO contest (tournament,session_num,round,game,scenario,status,starts,started,finished,notes,venue)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['session_num']).",
			".db_quote($_REQUEST['round']).",
			".db_quote($_REQUEST['game']).",
			".db_quote($_REQUEST['scenario']).",
			".db_quote($_REQUEST['status']).",
			".db_quote($_REQUEST['starts']).",
			".db_quote($_REQUEST['started']).",
			".db_quote($_REQUEST['finished']).",
			".db_quote($_REQUEST['notes']).",
			".db_quote($_REQUEST['venue'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));
		$contest_id = mysqli_insert_id($database);

		update_contest_participants($contest_id);
		mysqli_commit($database);

		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:update_contest'])) {

		mysqli_autocommit($database, FALSE);

		$updates = array();
		if ($tournament_info['multi_session']=='Y') {
		$updates[] = "session_num=".db_quote($_REQUEST['session_num']);
		}
		$updates[] = "game=".db_quote($_REQUEST['game']);
		$updates[] = "status=".db_quote($_REQUEST['status']);
		if ($tournament_info['multi_round']=='Y') {
		$updates[] = "round=".db_quote($_REQUEST['round']);
		}
		$updates[] = "scenario=".db_quote($_REQUEST['scenario']);
		$updates[] = "notes=".db_quote($_REQUEST['notes']);
		$updates[] = "starts=".db_quote($_REQUEST['starts']);
		$updates[] = "started=".db_quote($_REQUEST['started']);
		$updates[] = "finished=".db_quote($_REQUEST['finished']);
		if (isset($_REQUEST['venue'])) {
		$updates[] = "venue=".db_quote($_REQUEST['venue']);
		}

		$sql = "UPDATE contest
		SET ".implode(',',$updates)."
		WHERE id=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		update_contest_participants($_GET['id']);
		mysqli_commit($database);

		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:delete_contest'])) {

		mysqli_autocommit($database, FALSE);

		$sql = "DELETE FROM contest_participant
			WHERE contest=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$sql = "DELETE FROM contest WHERE id=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		mysqli_commit($database);

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

split_datetime($_REQUEST['starts'], $_REQUEST['starts_date'], $_REQUEST['starts_time']);
split_datetime($_REQUEST['started'], $_REQUEST['started_date'], $_REQUEST['started_time']);
split_datetime($_REQUEST['finished'], $_REQUEST['finished_date'], $_REQUEST['finished_time']);

$game_definition = array();
if ($_REQUEST['game']) {
	$sql = "SELECT seat_names,use_scenario FROM game_definition
	WHERE id=".db_quote($_REQUEST['game'])."
	AND tournament=".db_quote($tournament_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Game definition $_REQUEST[game] not found.");
	$game_definition['seat_names'] = $row[0];
	$game_definition['use_scenario'] = $row[1]=='Y';
	$game_definition['can_add_seats'] = !$row[0];
	$game_definition['can_remove_seats'] = !$row[0];
}
else {
	// if no game is specified, then user is allowed all features
	$game_definition['use_scenario'] = TRUE;
	$game_definition['can_add_seats'] = TRUE;
	$game_definition['can_remove_seats'] = TRUE;
}

begin_page(isset($_GET['id']) ? "Edit Game" : "New Game");

?>
<form name="edit_contest_form" method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
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
<?php if ($tournament_info['multi_venue']) {?>
<tr>
<td><label for="venue_cb">Venue:</label></td>
<td><?php
	select_venue_widget(array(
		'name' => 'venue',
		'id' => 'venue_cb',
		'value' => $_REQUEST['venue']
		));
		?></td>
</tr>
<?php }//endif multi_venue ?>
<tr>
<td><label for="starts_date_entry">Scheduled Start:</label></td>
<td>
<label>Date: <input type="date" id="starts_date_entry" name="starts_date" value="<?php h($_REQUEST['starts_date'])?>"></label>
<label>Time: <input type="time" name="starts_time" value="<?php h($_REQUEST['starts_time'])?>"></label>
</td>
</tr>
<tr>
<td><label for="started_date_entry">Actual Start:</label></td>
<td>
<label>Date: <input type="date" id="started_date_entry" name="started_date" value="<?php h($_REQUEST['started_date'])?>"></label>
<label>Time: <input type="time" name="started_time" value="<?php h($_REQUEST['started_time'])?>"></label>
</td>
</tr>
<tr>
<td><label for="finished_date_entry">Finished:</label></td>
<td>
<label>Date: <input type="date" id="finished_date_entry" name="finished_date" value="<?php h($_REQUEST['finished_date'])?>"></label>
<label>Time: <input type="time" name="finished_time" value="<?php h($_REQUEST['finished_time'])?>"></label>
</td>
</tr>
<tr>
<td><label for="game_cb">Game:</label></td>
<td><select id="game_cb" name="game">
<option value=""<?php echo(!$_REQUEST['game']?' selected="selected"':'')?>>--unspecified--</option>
<?php
$sql = "SELECT id,name FROM game_definition g WHERE g.tournament=".db_quote($tournament_id)."
	ORDER BY name";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {
	$game_id = $row[0];
	$game_name = $row[1];
	?><option value="<?php h($game_id)?>"<?php echo($_REQUEST['game']==$game_id?' selected="selected"':'')?>><?php h($game_name)?></option>
<?php
}
?>
</select>
</td>
</tr>
<?php if ($game_definition['use_scenario']) { ?>
<tr>
<td><label for="scenario_entry">Scenario:</label></td>
<td><input type="text" id="scenario_entry" name="scenario" value="<?php h($_REQUEST['scenario'])?>"></td>
</tr>
<?php } //endif use_scenario ?>
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
<?php
	$participant_columns = array('seat','player','participant_status','placement');

if ($can_edit) { ?>
<table id="participants_table" class="tabular_form">
<tr>
<th class="seat_col">Seat</th>
<th class="player_col"><?php h($tournament_info['use_teams']?'Team':'Player')?></th>
<th class="participant_status_col">Status</th>
<th class="placement_col">Placement</th>
</tr>
<?php
function participant_row($pre, $pdata)
{
	global $game_definition;
	global $participant_columns;

	$tr_class = 'participant_row' . ($pre?'':' template');
?>
<tr class="<?php h($tr_class)?>" <?php if (isset($pdata['id'])) { ?> data-rowid="<?php h($pdata['id'])?>"<?php } ?>>
<?php
foreach ($participant_columns as $col) {
	if ($col == 'commit') { ?>
<td class="commit_col">
<input type="checkbox" name="<?php h($pre."_commit")?>"<?php echo($pdata['commit'] ? ' checked="checked"':'')?>>
</td>
<?php } else if ($col == 'player') { ?>
<td class="player_col"><?php
	select_person_widget(array(
	'name' => $pre.'_player',
	'value' => $pdata['player_id']
	))?></td>
<?php } else if ($col == 'seat') { ?>
<td class="seat_col">
<?php if ($game_definition['can_add_seats']) {
	// seat name is editable ?>
<input type="text" size="4" name="<?php h($pre.'_seat')?>" value="<?php h($pdata['seat'])?>">
<?php } else {
	// seat name is NOT editable ?>
<input type="hidden" name="<?php h($pre.'_seat')?>" value="<?php h($pdata['seat'])?>"><?php format_seat_name($pdata['seat'])?>
<?php } ?>
</td>
<?php } else if ($col == 'participant_status') { ?>
<td class="participant_status_col">
<?php select_widget(array(
	'name' => $pre.'_participant_status',
	'value' => $pdata['participant_status'],
	'options' => array(
		'' => '--',
		'C' => 'Confirmed',
		'P' => 'Proposed',
		'M' => 'Mulligan'
		)
	)); ?>
</td>
<?php } else { ?>
<td class="<?php h($col)?>_col"><input type="text" size="4" name="<?php h($pre.'_'.$col)?>" value="<?php h($pdata[$col])?>"></td>
<?php } // end col switch
} //end each column
?>
<td class="actions_col">
<button type="button" class="mark_winner_btn" title="Mark this participant a winner">WIN</button>
<?php if ($game_definition['can_remove_seats']) { ?>
<button type="button" class="delete_row_btn" title="Delete this participant"><img src="images/red_cross.png" alt="Delete"></button>
<?php } else { ?>
<button type="button" class="clear_row_btn" title="Clear this seat"><img src="images/red_cross.png" alt="Clear"></button>
<?php } //end if can_remove_seats ?>
</td>
</tr>
<?php
} //end participant_row()

$seen_seats = array();
if (isset($_GET['id'])) {
	$sql = "SELECT cp.id AS id,
		p.id AS player_id,
		p.name AS player_name,
		r.prior_rating AS prior_rating,
		cp.seat,cp.turn_order,cp.score,cp.placement,
		cp.participant_status
		FROM contest_participant cp
		JOIN contest c ON c.id=cp.contest
		LEFT JOIN person p ON p.id=cp.player
		LEFT JOIN player_rating r ON r.id=p.id
			AND r.session_num=c.session_num
		WHERE contest=".db_quote($_GET['id'])."
		ORDER BY turn_order,cp.id";
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
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

		$seen_seats[$seat] = TRUE;
		participant_row('participant_'.$cpid,
			array(
			'id' => $cpid,
			'commit' => $commit,
			'player_id' => $player_id,
			'player_name' => $player_name,
			'seat' => $seat,
			'turn_order' => $turn_order,
			'score' => $score,
			'placement' => $placement,
			'participant_status' => $status
			)
			);
	} // end foreach participant
} //end if existing contest

if ($can_edit && $game_definition['seat_names']) {
	$mandatory_seats = explode(',', $game_definition['seat_names']);
	$mcount = 0;
	foreach ($mandatory_seats as $seat) {
		if (isset($seen_seats[$seat])) { continue; }
		participant_row('participant_m'.(++$mcount),
			array(
			'seat' => $seat,
			'player_id' => NULL,
			'player_name' => NULL,
			'turn_order' => '',
			'score' => '',
			'placement' => '',
			'status' => ''
			)
			);
	}
}

participant_row('', array(
			'seat' => '',
			'player_id' => NULL,
			'player_name' => NULL,
			'turn_order' => '',
			'score' => '',
			'placement' => '',
			'status' => ''
			));
	?>
</table>
<?php
	if ($can_edit && $game_definition['can_add_seats']) {
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
