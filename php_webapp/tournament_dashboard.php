<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	id => $tournament_id,
	name => $row[0]
	);

$page_title = "$tournament_info[name] - Dashboard";
begin_page($page_title);

?>
<table border="1">
<caption>Players</caption>
<tr>
<th>Player Name</th>
<th>Number</th>
<th>Home City</th>
</tr>
<?php
$sql = "SELECT id,name,member_number,home_location FROM player
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY name";
$query = mysqli_query($database, $sql);
while ($row = mysqli_fetch_row($query)) {

	$player_id = $row[0];
	$name = $row[1];
	$member_number = $row[2];
	$home_location = $row[3];

	$url = "player.php?id=".urlencode($player_id);

	?><tr>
<td class="name_col"><a href="<?php h($url)?>"><?php h($name)?></a></td>
<td class="member_number_col"><?php h($member_number)?></td>
<td class="home_location_col"><?php h($home_location)?></td>
</tr>
<?php
} //end foreach player
?>
</table>

<?php
$new_player_url = "player.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($new_player_url)?>">New Player</a>
</p>

<table border="1">
<caption>Contests</caption>
<tr>
<th>ID</th>
<th>Round</th>
<th>Board</th>
<th>Game</th>
<th>Participants</th>
<th>Winner</th>
</tr>
<?php
$sql = "SELECT id,round,board,game,
	(SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ', ')
		FROM player p
		WHERE p.id IN (
			SELECT player FROM contest_participant cp
			WHERE cp.contest=c.id)) AS participants,
	(SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ', ')
		FROM player p
		WHERE p.id IN (
			SELECT player FROM contest_participant cp
			WHERE cp.contest=c.id
			AND cp.placement=1
			)) AS winner
	FROM contest c
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY id";
$query = mysqli_query($database, $sql);

while ($row = mysqli_fetch_row($query)) {

	$contest_id = $row[0];
	$round = $row[1];
	$board = $row[2];
	$game = $row[3];
	$participants = $row[4];
	$winner = $row[5];

	$url = "contest.php?id=".urlencode($contest_id);
	?>
<tr>
<td class="id_col"><a href="<?php h($url)?>"><?php h($contest_id)?></a></td>
<td class="round_col"><?php h($round)?></td>
<td class="board_col"><?php h($board)?></td>
<td class="game_col"><?php h($game)?></td>
<td class="participants_col"><?php h($participants)?></td>
<td class="winner_col"><?php h($winner)?></td>
</tr>
<?php
} //end foreach contest

?>
</table>

<?php
$new_contest_url = "contest.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($new_contest_url)?>">New Contest</a>
</p>

<?php
end_page();
