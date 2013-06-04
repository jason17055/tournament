<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/ratings_functions.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	id => $tournament_id,
	name => $row[0]
	);

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}
	else if (isset($_REQUEST['action:run_ratings'])) {

		if ($_REQUEST['batch']) {
			do_ratings_pass($_REQUEST['batch']);
		}
		else {
			do_ratings($tournament_id);
		}
		exit();
	}
	else {
		die("Not implemented");
	}
}

$page_title = "$tournament_info[name] - Run Ratings";
begin_page($page_title);

$sql = "SELECT rating_cycle,
	(SELECT COUNT(*) FROM contest WHERE rating_cycle=t.rating_cycle AND tournament=t.tournament) AS contest_count,
	(SELECT COUNT(DISTINCT player) FROM contest_participant cp
			JOIN contest c ON c.id=cp.contest
			WHERE c.tournament=t.tournament) AS player_count
	FROM (
		SELECT DISTINCT tournament,rating_cycle
		FROM contest
		WHERE tournament=".db_quote($tournament_id)."
		AND rating_cycle IS NOT NULL
		) t
	ORDER BY rating_cycle";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

?>
<table border="1">
<tr>
<th>Rating Cycle</th>
<th>Game Count</th>
<th>Player Count</th>
</tr>
<?php
while ($row = mysqli_fetch_row($query)) {
	$rating_cycle = $row[0];
	$contest_count = $row[1];
	$player_count = $row[2];
	?>
<tr>
<td class="rating_cycle_col"><?php h($rating_cycle)?></td>
<td class="contest_count_col"><?php h($contest_count)?></td>
<td class="player_count_col"><?php h($player_count)?></td>
</tr>
<?php
} //end foreach rating cycle
?>
</table>

<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">

<button type="submit" name="action:run_ratings">Run Ratings</button>
<button type="submit" name="action:cancel">Cancel</button>

</form>
<?php
end_page();
