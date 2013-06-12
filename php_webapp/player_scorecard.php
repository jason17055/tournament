<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

$sql = "SELECT t.id, t.multi_game, t.multi_session, p.name
	FROM person p
	JOIN tournament t ON t.id=p.tournament
	WHERE p.id=".db_quote($_GET['id']);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query)
	or die("Not Found");
$tournament_id = $row[0];
$tournament_info = array(
	id => $tournament_id,
	multi_game => $row[1],
	multi_session => $row[2]
	);
$person_id = $_GET['id'];
$person_info = array(
	id => $person_id,
	name => $row[3]
	);

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	die("Not implemented");
}

begin_page("$person_info[name] - Scorecard");

$go_back_url = 'person.php?id='.urlencode($_GET['id']);
?>
<p>
<a href="<?php h($go_back_url)?>">Go Back</a>
</p>
<?php

include('rating_chart.inc.php');

$sql = "SELECT c.id,
	session_num,
	started,
	CONCAT(c.round,'-',c.board) AS contest_name,
	scenario,
	(SELECT GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ')
		FROM contest_participant cp
			JOIN person p ON p.id=cp.player
		WHERE cp.contest=c.id
		AND cp.player<>cp1.player
		) AS opponents,
	cp1.placement,
	cp1.w_points,
	cp1.performance,
	(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id) AS nplayers
	FROM contest_participant cp1
		JOIN contest c ON c.id=cp1.contest
	WHERE cp1.player=".db_quote($person_id)."
	ORDER BY c.session_num,c.round,c.started,c.board,c.id
	";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

?>
<table border="1">
<tr>
<?php if ($tournament_info['multi_session']=='Y'){?>
<th>Session</th>
<?php }?>
<th>Date</th>
<th>Round-Board</th>
<th>Scenario</th>
<th>Against</th>
<th>Placement</th>
<th>W Points</th>
<th>P Points</th>
</tr>
<?php

while ($row = mysqli_fetch_row($query)) {
	$url = "contest.php?id=".urlencode($row[0])
		.'&next_url='.urlencode($_SERVER['REQUEST_URI']);
	$session_num = $row[1];
	$started_date = $row[2];
	$contest_name = $row[3];
	$scenario = $row[4];
	$opponents = $row[5];
	$placement = $row[6];
	$w_points = $row[7];
	$performance = $row[8];
	$nplayers = $row[9];
	if ($placement == 1) {
		$placement = "1st";
	}else if ($placement == 2) {
		$placement = '2nd';
	}else if ($placement == 3) {
		$placement = "3rd";
	}else if ($placement >= 4 && $placement <= 20) {
		$placement = $placement .= "th";
	}
	$placement .= " / $nplayers";
?>
<tr>
<?php if ($tournament_info['multi_session']=='Y'){?>
<td class="session_num_col"><?php h($session_num)?></td>
<?php }?>
<td class="started_date_col"><?php h($started_date)?></td>
<td class="contest_name_col"><a href="<?php h($url)?>"><?php h($contest_name)?></a></td>
<td class="scenario_col"><?php format_scenario($scenario)?></td>
<td class="opponents_col"><?php h($opponents)?></td>
<td class="placement_col"><?php h($placement)?></td>
<td class="w_points_col"><?php h($w_points)?></td>
<td class="performance_col"><?php h(sprintf('%.3f', $performance))?></td>
</tr>
<?php
} // end foreach contest

?>
</table>

<p>
<a href="<?php h($go_back_url)?>">Go Back</a>
</p>

<?php
end_page();
