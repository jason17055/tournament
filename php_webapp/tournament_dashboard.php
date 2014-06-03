<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/format.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,multi_game,multi_session,current_session,vocab_table,
	ratings,use_person_member_number,use_person_entry_rank,
	use_person_home_location,use_person_mail,use_person_phone,
	(SELECT MIN(id) FROM game_definition WHERE tournament=t.id) AS game0,
	use_teams,
	(SELECT GROUP_CONCAT(DISTINCT round ORDER BY round) FROM contest WHERE round IS NOT NULL AND tournament=t.id) rounds
	FROM tournament t
	WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
$row = mysqli_fetch_row($query);
$tournament_info = array(
	'id' => $tournament_id,
	'name' => $row[0],
	'multi_game' => $row[1]=='Y',
	'multi_session' => $row[2]=='Y',
	'current_session' => $row[3],
	'vocab_table' => $row[4],
	'ratings' => $row[5]=='Y',
	'use_person_member_number' => $row[6]=='Y',
	'use_person_entry_rank' => $row[7]=='Y',
	'use_person_home_location' => $row[8]=='Y',
	'use_person_mail' => $row[9]=='Y',
	'use_person_phone' => $row[10]=='Y',
	'game0' => $row[11],
	'use_teams' => $row[12]=='Y',
	'rounds' => $row[13] ?: '1'
	);

$game_definition = array();
if (!$tournament_info['multi_game'] && $tournament_info['game0']) {
	$sql = "SELECT use_scenario
		FROM game_definition
		WHERE id=".db_quote($tournament_info['game0']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query);
	$game_definition['use_scenario'] = $row[0] == 'Y';
	$tournament_info['use_scenario'] = $game_definition['use_scenario'];
}

$page_title = "$tournament_info[name] - Dashboard";
begin_page($page_title);

$can_edit_players = is_director($tournament_id);

$person_column_names = array(
	'ordinal' => ($tournament_info['use_teams'] ? 'Team Number' : 'Player Number'),
	'name' => ($tournament_info['use_teams'] ? 'Team Name' : 'Player Name'),
	'member_number' => 'Member Number',
	'entry_rank' => 'Entry Rank',
	'home_location' => 'Home Location',
	'mail' => 'Email Address',
	'phone' => 'Telephone',
	'member_phones' => 'Phone(s)',
	'raw_score' => 'Score',
	'games_played' => 'Games Played',
	'games_won' => 'Games Won',
	'status' => 'Status',
	'current_rating' => 'Current Rating'
	);
$person_column_sortable = array(
	'ordinal' => TRUE,
	'raw_score' => TRUE
	);

$person_columns = array('ordinal','name');
if ($tournament_info['use_person_member_number']) { $person_columns[]='member_number';}
if ($tournament_info['use_person_entry_rank'])    { $person_columns[]='entry_rank';}
if ($tournament_info['use_person_home_location']) { $person_columns[]='home_location';}
if ($tournament_info['use_person_mail'])          { $person_columns[]='mail';}
if ($tournament_info['use_person_phone']) {
	if ($tournament_info['use_teams']) { $person_columns[]='member_phones';}
	else                               { $person_columns[]='phone';}
}
$person_columns[]='status';

foreach (explode(',',$tournament_info['rounds']) as $round) {
	$person_columns[]='round.'.$round;
	$person_column_names['round.'.$round] = "R$round";
}

//$person_columns[]='games_played';
//$person_columns[]='games_won';
$person_columns[]='raw_score';
if ($tournament_info['ratings']) {
	$person_columns[] = 'current_rating';
}

?>
<table class="tournament_roster_table" border="1">
<caption><?php h($tournament_info['use_teams'] ? 'Teams' : 'Players')?></caption>
<tr>
<?php if ($can_edit_players) { ?>
<th></th>
<?php } ?>
<?php
foreach ($person_columns as $col) { ?>
<th class="<?php h($col.'_col')?>"><?php
	if (isset($person_column_sortable[$col])) {
		$sort_url = 'tournament_dashboard.php?tournament='.urlencode($tournament_id)
				. '&order_by='.urlencode($col);
		?><a class="column_sort_link" href="<?php h($sort_url)?>"><?php h($person_column_names[$col])?></a>
<?php } else {
		h($person_column_names[$col]);
} ?></th>
<?php }//end foreach column ?>
</tr>
<?php
if (isset($_REQUEST['order_by']) && $_REQUEST['order_by']=='raw_score') {
	$order_by_sql = 'raw_score DESC';
} else {
	$order_by_sql = 'ordinal ASC, name ASC, id ASC';
}
$sql = "SELECT p.id,p.name,p.status,
	(SELECT COUNT(DISTINCT contest) FROM contest_participant
			WHERE player=p.id) AS games_played,
	(SELECT COUNT(*) FROM contest c
		WHERE p.id IN (SELECT player FROM contest_participant
				WHERE contest=c.id
				AND placement=1)
		AND EXISTS (SELECT 1 FROM contest_participant
				WHERE contest=c.id
				AND NOT (placement=1))
		) AS games_won,
	(SELECT COUNT(*) FROM contest c
		WHERE p.id IN (SELECT player FROM contest_participant
				WHERE contest=c.id
				AND placement=1)
		AND EXISTS (SELECT 1 FROM contest_participant
				WHERE contest=c.id
				AND NOT (placement=1))
		AND c.session_num=".db_quote($tournament_info['current_session'])."
		) AS games_won_this_session,
	(SELECT SUM(w_points) FROM contest_participant
		WHERE player=p.id
		) AS w_points,
	(SELECT SUM(w_points) FROM contest_participant
		WHERE player=p.id
		AND contest IN (SELECT id FROM contest WHERE session_num=".db_quote($tournament_info['current_session']).")
		) AS w_points_this_session,
	IFNULL(r.post_rating,r.prior_rating) AS rating,
	p.member_number,p.entry_rank,p.home_location,
	p.mail,p.phone,p.ordinal,
	p.is_team,
	IFNULL((SELECT GROUP_CONCAT(phone SEPARATOR ', ') FROM person pp
		WHERE pp.member_of=p.id AND phone IS NOT NULL), p.phone) AS member_phones,
	(SELECT value FROM person_attrib_float WHERE person=p.id AND attrib='raw_score') AS raw_score
	FROM person p
	JOIN tournament t
		ON t.id=p.tournament
	LEFT JOIN player_rating r
		ON r.id=p.id
		AND r.session_num=t.current_session
	WHERE tournament=".db_quote($tournament_id)."
	AND p.status IS NOT NULL
	ORDER BY $order_by_sql";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {

	$person_id = $row[0];
	$name = $row[1];
	$w_points = $row[6] ?: 0;
	$w_points_this_session = $row[7] ?: 0;
	$cur_rating = $row[8];

	$d = array(
	'status' => $row[2],
	'games_played' => $row[3],
	'games_won' => $row[4],
	'games_won_this_session' => $row[5],
	'current_rating' => $row[8],
	'member_number' => $row[9],
	'entry_rank' => $row[10],
	'home_location' => $row[11],
	'mail' => $row[12],
	'phone' => $row[13],
	'ordinal' => $row[14],
	'is_team' => ($row[15]=='Y'),
	'member_phones' => $row[16],
	'raw_score' => $row[17]
	);

	$edit_url = $d['is_team'] ? 
		"team.php?id=".urlencode($person_id) :
		"person.php?id=".urlencode($person_id);
	$url = 'player_scorecard.php?id='.urlencode($person_id);

	?><tr>
<?php if ($can_edit_players) { ?>
<td class="link_col"><a href="<?php h($edit_url)?>"><img src="images/edit.gif" width="18" height="18" alt="Edit" border="0"></a></td>
<?php }
	foreach ($person_columns as $col) {
		if ($col == 'name') { ?>
<td class="name_col"><img src="<?php
	h($d['is_team']?'images/team_icon.png':'images/person_icon.png')?>">
	<?php h($name)?></td>
<?php } else if ($col == 'status') { ?>
<td class="status_col"><?php format_person_status($d['status'])?></td>
<?php } else if ($col == 'games_played') { ?>
<td class="game_count_col"><?php h($d['games_played'])?></td>
<?php } else if ($col == 'games_won') { ?>
<td class="game_count_col"><?php h($tournament_info['multi_session']?"$d[games_won] (+$d[games_won_this_session])":"$d[games_won]")?></td>
<?php } else if ($col == 'current_rating') { ?>
<td class="rating_col"><?php
	if (!is_null($cur_rating)) { h(sprintf('%.0f', $cur_rating));
		}?></td>
<?php } else if (preg_match('/^round\.(.*)$/', $col, $m)) { ?>
<td class="round_result_col"><?php
		$sql = "
			SELECT SUM(w_points)
			FROM contest_participant cp
			JOIN contest c ON c.id=cp.contest
			WHERE cp.player=".db_quote($person_id)."
			AND c.status='completed'
			AND IFNULL(cp.participant_status,'C') NOT IN ('M')
			AND c.round=".db_quote($m[1])."
			";
		$q2 = mysqli_query($database, $sql);
		$r2 = mysqli_fetch_row($q2);
		h($r2[0]);
?>
</td>
<?php } else { ?>
<td class="<?php h($col)?>_col"><?php h($d[$col])?></td>
<?php } //end switch $col ?>
<?php } //end each $col ?>

</tr>
<?php
} //end foreach person
?>
</table>

<?php
$sql = "SELECT COUNT(*) FROM person
	WHERE tournament=".db_quote($tournament_id)."
	AND member_of IS NULL
	AND status IS NULL";
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
if ($row[0]) {
	?>
<div>Not shown: <?php h(number_format($row[0]))?> unregistered players</div>
<?php
}

if ($can_edit_players) {
$new_person_url = "person.php?tournament=".urlencode($tournament_id);
$new_team_url = "team.php?tournament=".urlencode($tournament_id);
$import_persons_url = "import_person.php?tournament=".urlencode($tournament_id);
$pairings_url = "pairings.php?tournament=".urlencode($tournament_id);
?>
<p>
<?php if ($tournament_info['use_teams']) { ?>
<a href="<?php h($new_person_url)?>">New Individual</a>
| <a href="<?php h($new_team_url)?>">New Team</a>
<?php } else { ?>
<a href="<?php h($new_person_url)?>">New Player</a>
<?php } ?>
| <a href="<?php h($import_persons_url)?>">Import Players</a>
| <a href="<?php h($pairings_url)?>">Generate Pairings</a>
</p>
<?php }


 ?>

<table border="1">
<caption>Games</caption>
<tr>
<th></th>
<?php if ($tournament_info['multi_session']) { ?>
<th class="session_col">Session</th>
<?php } ?>
<th class="round_col">Round</th>
<th class="starts_col">Starts</th>
<th class="venue_col"><?php
	echo($tournament_info['vocab_table']=='court'?'Court':'Table')?></th>
<?php if ($tournament_info['multi_game']) { ?>
<th class="game_col">Game</th>
<?php } ?>
<?php if ($tournament_info['use_scenario']) { ?>
<th class="scenario_col">Scenario</th>
<?php } ?>
<th class="contest_status_col">Status</th>
<th>Competitors</th>
<th>Winner</th>
</tr>
<?php
$sql = "SELECT c.id,
	session_num,
	starts,
	round AS round,
	c.game,c.scenario,c.status,
	(SELECT GROUP_CONCAT(
		CONCAT(p.name,IF(IFNULL(cp.participant_status,'C')='C','',CONCAT('(',cp.participant_status,')')))
		ORDER BY name SEPARATOR ', '
		)
		FROM contest_participant cp
			JOIN person p ON p.id=cp.player
		WHERE cp.contest=c.id
	) AS participants,
	(SELECT GROUP_CONCAT(
		CONCAT(p.name, IF(cp.score IS NOT NULL,
				CONCAT(' (',cp.score,')'),
				'')) ORDER BY name SEPARATOR ', '
		)
		FROM contest_participant cp
			JOIN person p ON p.id=cp.player
		WHERE cp.contest=c.id
		AND cp.placement=1
	) AS winner,
	venue_name
	FROM contest c
	LEFT JOIN venue v ON v.id=c.venue
	WHERE c.tournament=".db_quote($tournament_id)."
	ORDER BY session_num,round,starts,venue_name,c.id";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

while ($row = mysqli_fetch_row($query)) {

	$contest_id = $row[0];
	$session_num = $row[1];
	$starts = $row[2];
	$round = $row[3];
	$game = $row[4];
	$scenario = $row[5];
	$status = $row[6];
	$participants = $row[7];
	$winner = $row[8];
	$venue_name = $row[9];

	$edit_url = "contest.php?id=".urlencode($contest_id);
	?>
<tr>
<td class="link_col"><a href="<?php h($edit_url)?>"><img src="images/edit.gif" width="18" height="18" alt="Edit" border="0"></a></td>
<?php if ($tournament_info['multi_session']) { ?>
<td class="session_col"><?php h($session_num)?></td>
<?php } ?>
<td class="round_col"><?php h($round)?></td>
<td class="starts_col"><?php h(format_time_s($starts))?></td>
<td class="venue_col"><?php h($venue_name)?></td>
<?php if ($tournament_info['multi_game']) { ?>
<td class="game_col"><?php h($game)?></td>
<?php } ?>
<?php if ($tournament_info['use_scenario']) { ?>
<td class="scenario_col"><?php format_scenario($scenario)?></td>
<?php } ?>
<td class="contest_status_col"><?php format_contest_status($status)?></td>
<td class="participants_col"><?php h($participants)?></td>
<td class="winner_col"><?php h($winner)?></td>
</tr>
<?php
} //end foreach contest

?>
</table>

<?php
if (is_director($tournament_id)) {
$new_contest_url = "contest.php?tournament=".urlencode($tournament_id);
$scheduler_url = "scheduler.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($new_contest_url)?>">New Game</a>
|
<a href="<?php h($scheduler_url)?>">Scheduler</a>
</p>

<?php
}//endif director


if (is_director($tournament_id)) {
$edit_tourney_url = "tournament.php?id=".urlencode($tournament_id).'&next_url='.urlencode($_SERVER['REQUEST_URI']);
$edit_game_url = "game_definition.php?tournament=".urlencode($tournament_id).'&next_url='.urlencode($_SERVER['REQUEST_URI']);
$edit_venues_url = "venues.php?tournament=".urlencode($tournament_id)."&next_url=".urlencode($_SERVER['REQUEST_URI']);
$run_ratings_url = "run_ratings.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($edit_tourney_url)?>">Tournament Definition</a> |
<a href="<?php h($edit_game_url)?>">Game Definition</a> |
<a href="<?php h($edit_venues_url)?>">Venue Definition</a>
<?php if ($tournament_info['ratings']) { ?>
| <a href="<?php h($run_ratings_url)?>">Run Ratings</a>
<?php }//endif tournament using ratings ?>
</p>

<?php
}//endif director

$scoreboard_url = 'scoreboard.html?tournament='.urlencode($tournament_id);
?>
<p>
<a href="<?php h($scoreboard_url)?>">Scoreboard</a>
| <a href="#" id="make_game_results_link">AGA Results File</a>
| <a href="<?php h('card_stats.php?tournament='.urlencode($tournament_id))?>">Dominion Card Stats</a>
</p>
<?php

end_page();
