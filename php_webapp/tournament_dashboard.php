<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,multi_game,multi_session FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	id => $tournament_id,
	name => $row[0],
	multi_game => $row[1],
	multi_session => $row[2]
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
</tr>
<?php
$sql = "SELECT id,name,mail,status FROM person
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY name";
$query = mysqli_query($database, $sql);
while ($row = mysqli_fetch_row($query)) {

	$person_id = $row[0];
	$name = $row[1];
	$mail = $row[2];
	$status = $row[3];

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
</tr>
<?php
} //end foreach person
?>
</table>

<?php
if (is_director($tournament_id)) {
$new_person_url = "person.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($new_person_url)?>">New Player</a>
</p>
<?php } ?>

<table border="1">
<caption>Games</caption>
<tr>
<?php if ($tournament_info['multi_session']=='Y') { ?>
<th>Session</th>
<?php } ?>
<th>Round-Board</th>
<?php if ($tournament_info['multi_game']=='Y') { ?>
<th>Game</th>
<?php } ?>
<th>Participants</th>
<th>Winner</th>
</tr>
<?php
$sql = "SELECT id,
	session_num,
	CONCAT(round,'-',board) AS contest_name,
	game,
	(SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ', ')
		FROM person p
		WHERE p.id IN (
			SELECT player FROM contest_participant cp
			WHERE cp.contest=c.id)) AS participants,
	(SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ', ')
		FROM person p
		WHERE p.id IN (
			SELECT player FROM contest_participant cp
			WHERE cp.contest=c.id
			AND cp.placement=1
			)) AS winner
	FROM contest c
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY session_num,round,board,id";
$query = mysqli_query($database, $sql);

while ($row = mysqli_fetch_row($query)) {

	$contest_id = $row[0];
	$session_num = $row[1];
	$contest_name = $row[2];
	$game = $row[3];
	$participants = $row[4];
	$winner = $row[5];

	$url = "contest.php?id=".urlencode($contest_id);
	?>
<tr>
<?php if ($tournament_info['multi_session']=='Y') { ?>
<td class="session_num_col"><?php h($session_num)?></td>
<?php } ?>
<td class="contest_name_col"><a href="<?php h($url)?>"><?php h($contest_name)?></a></td>
<?php if ($tournament_info['multi_game'] == 'Y') { ?>
<td class="game_col"><?php h($game)?></td>
<?php } ?>
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
