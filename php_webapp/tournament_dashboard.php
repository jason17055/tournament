<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,multi_game,multi_session,current_session FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	id => $tournament_id,
	name => $row[0],
	multi_game => $row[1],
	multi_session => $row[2],
	current_session => $row[3]
	);

$page_title = "$tournament_info[name] - Dashboard";
begin_page($page_title);

?>
<table border="1">
<caption>Players</caption>
<tr>
<th>Player Name</th>
<th>Email Address</th>
<th>Status</th>
<th>Games Played</th>
<th>Games Won</th>
<th>Points</th>
</tr>
<?php
$sql = "SELECT id,name,mail,status,
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
		) AS w_points_this_session
	FROM person p
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY name";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {

	$person_id = $row[0];
	$name = $row[1];
	$mail = $row[2];
	$status = $row[3];
	$games_played = $row[4];
	$games_won = $row[5];
	$games_won_this_session = $row[6];
	$w_points = $row[7] ?: 0;
	$w_points_this_session = $row[8] ?: 0;

	$url = "person.php?id=".urlencode($person_id);

	?><tr>
<td class="name_col"><a href="<?php h($url)?>"><?php h($name)?></a></td>
<td class="mail_col"><?php h($mail)?></td>
<td class="status_col"><?php
	if ($status == 'ready') {
		?><img src="images/plus.png" width="14" height="14" alt=""><?php
	} else if ($status == 'absent') {
		?><img src="images/minus.png" width="14" height="14" alt=""><?php
	}
	h($status)?></td>
<td class="game_count_col"><?php h($games_played)?></td>
<td class="game_count_col"><?php h("$games_won (+$games_won_this_session)")?></td>
<td class="w_points_col"><?php h("$w_points (+$w_points_this_session)")?></td>
</tr>
<?php
} //end foreach person
?>
</table>

<?php
if (is_director($tournament_id)) {
$new_person_url = "person.php?tournament=".urlencode($tournament_id);
$pairings_url = "pairings.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($new_person_url)?>">New Player</a>
|
<a href="<?php h($pairings_url)?>">Generate Pairings</a>
</p>
<?php } ?>

<table border="1">
<caption>Games</caption>
<tr>
<?php if ($tournament_info['multi_session']=='Y') { ?>
<th>Session</th>
<?php } ?>
<th>Started</th>
<th>Round-Board</th>
<?php if ($tournament_info['multi_game']=='Y') { ?>
<th>Game</th>
<?php } ?>
<th>Scenario</th>
<th>Participants</th>
<th>Winner</th>
</tr>
<?php
$sql = "SELECT id,
	session_num,
	IFNULL(started,'(unknown)') AS started,
	CONCAT(round,'-',board) AS contest_name,
	game,scenario,
	(SELECT GROUP_CONCAT(
		p.name ORDER BY name SEPARATOR ', '
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
	) AS winner
	FROM contest c
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY session_num,round,started,board,id";
$query = mysqli_query($database, $sql);

while ($row = mysqli_fetch_row($query)) {

	$contest_id = $row[0];
	$session_num = $row[1];
	$started_date = $row[2];
	$contest_name = $row[3];
	$game = $row[4];
	$scenario = $row[5];
	$participants = $row[6];
	$winner = $row[7];

	$url = "contest.php?id=".urlencode($contest_id);
	?>
<tr>
<?php if ($tournament_info['multi_session']=='Y') { ?>
<td class="session_num_col"><?php h($session_num)?></td>
<?php } ?>
<td class="started_date_col"><a href="<?php h($url)?>"><?php h($started_date)?></a></td>
<td class="contest_name_col"><a href="<?php h($url)?>"><?php h($contest_name)?></a></td>
<?php if ($tournament_info['multi_game'] == 'Y') { ?>
<td class="game_col"><?php h($game)?></td>
<?php } ?>
<td class="scenario_col"><?php format_scenario($scenario)?></td>
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
?>
<p>
<a href="<?php h($new_contest_url)?>">New Game</a>
</p>

<?php
}//endif director


if (is_director($tournament_id)) {
$edit_tourney_url = "tournament.php?id=".urlencode($tournament_id).'&next_url='.urlencode($_SERVER['REQUEST_URI']);
$run_ratings_url = "run_ratings.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($edit_tourney_url)?>">Tournament Settings</a> |
<a href="<?php h($run_ratings_url)?>">Run Ratings</a>
</p>

<?php
}//endif director
end_page();
