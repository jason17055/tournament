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
	else if (isset($_REQUEST['action:commit_ratings'])) {
		do_ratings_commit($_REQUEST['batch']);
	}
	else {
		die("Not implemented");
	}
}

$page_title = "$tournament_info[name] - Run Ratings";
begin_page($page_title);

$sql = "SELECT session_num,
	(SELECT COUNT(*) FROM contest WHERE session_num=t.session_num AND tournament=t.tournament) AS contest_count,
	(SELECT COUNT(DISTINCT player) FROM contest_participant cp
			JOIN contest c ON c.id=cp.contest
			WHERE c.tournament=t.tournament) AS player_count
	FROM (
		SELECT DISTINCT tournament,session_num
		FROM contest
		WHERE tournament=".db_quote($tournament_id)."
		AND session_num IS NOT NULL
		) t
	ORDER BY session_num";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

?>
<table border="1">
<tr>
<th>Session</th>
<th>Game Count</th>
<th>Player Count</th>
</tr>
<?php
while ($row = mysqli_fetch_row($query)) {
	$session_num = $row[0];
	$contest_count = $row[1];
	$player_count = $row[2];
	?>
<tr>
<td class="session_num_col"><?php h($session_num)?></td>
<td class="contest_count_col"><?php h($contest_count)?></td>
<td class="player_count_col"><?php h($player_count)?></td>
</tr>
<?php
} //end foreach rating cycle
?>
</table>

<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">

<div>
Start at session:
<input type="text" name="first_session" size="4" value="1">
</div>
<div>
Default initial rating:
<input type="text" name="initial_rating" size="4" value="1500">
</div>
<div>
Weight of initial rating:
<input type="text" name="initial_weight" size="4" value="10">
</div>
<div>
Weight of inter-session ratings link:
<input type="text" name="inter_session_weight" size="4" value="30">
</div>


<div class="form_buttons_bar">
<button type="submit" name="action:run_ratings">Run Ratings</button>
<button type="submit" name="action:cancel">Cancel</button>
</div>

</form>
<?php
end_page();
