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
<tr>
<th>ID</th>
<th>Round</th>
<th>Board</th>
<th>Game</th>
<th>Participants</th>
<th>Results</th>
</tr>
<?php
$sql = "SELECT id,round,board,game
	FROM contest
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY id";
$query = mysqli_query($database, $sql);

while ($row = mysqli_fetch_row($query)) {

	$contest_id = $row[0];
	$round = $row[1];
	$board = $row[2];
	$game = $row[3];

	$url = "#";
	?>
<tr>
<td class="id_col"><a href="<?php h($url)?>"><?php h($contest_id)?></a></td>
<td class="round_col"><?php h($round)?></td>
<td class="board_col"><?php h($board)?></td>
<td class="game_col"><?php h($game)?></td>
<td class="participants_col"><?php h("")?></td>
<td class="results_col"><?php h("")?></td>
</tr>
<?php
}

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
