<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');
require_once('includes/format.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];
	$sql = "SELECT use_teams
		FROM tournament t
		WHERE id=".db_quote($tournament_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");
	$tournament_info = array(
		'use_teams' => $row[0]=='Y'
		);
}
else {
	die("Invalid request");
}

if (!isset($_GET['players'])) {
	die("Invalid request");
}

if (!is_director($tournament_id)) {
	die("Unauthorized.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = isset($_REQUEST['next_url']) ? $_REQUEST['next_url'] : 'tournament_dashboard.php?tournament='.urlencode($tournament_id);

	die("Not implemented.");
}

begin_page("Check assignments");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table border="1">
<tr>
<th class="ordinal_col"><?php h($tournament_info['use_teams'] ? 'Team Number' : 'Ordinal')?></th>
<th class="name_col"><?php h($tournament_info['use_teams'] ? 'Team Name' : 'Player')?></th>
<th class="status_col">Status</th>
<th class="record_col">Record</th>
<th class="next_assignment_col">Next Game</th>
<th class="actions_col">Actions</th>
</tr>
<?php

$sql = "SELECT p.is_team,p.ordinal,p.name,p.status,
		(SELECT value FROM person_attrib_value WHERE person=p.id AND attrib='wins_losses') AS record,
		next_c.id AS next_c,
		next_c.starts AS next_start,
		next_c_v.venue_name AS next_venue,
		p.id,
		(SELECT MAX(round) FROM contest_participant cp JOIN contest c ON c.id=cp.contest
			WHERE cp.player=p.id
			AND c.status='completed'
			AND IFNULL(cp.participant_status,'C') NOT IN ('M')) AS last_round
	FROM person p
	LEFT JOIN contest next_c
		ON next_c.id=(
			SELECT c1.id FROM contest c1
			JOIN contest_participant cp
				ON cp.contest=c1.id
			WHERE cp.player=p.id
			AND IFNULL(c1.status,'unknown') NOT IN ('completed')
			ORDER BY starts
			)
	LEFT JOIN venue next_c_v
		ON next_c_v.id=next_c.venue
	WHERE p.tournament=".db_quote($tournament_id)."
	AND p.id IN (".db_quote_list(explode(',',$_REQUEST['players'])).")
	ORDER BY ordinal,name
	";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {
	$is_team = $row[0]=='Y';
	$ordinal = $row[1];
	$name = $row[2];
	$status = $row[3];
	$record = $row[4];
	$next_c = $row[5];
	$next_time = $row[6];
	$next_venue = $row[7];
	$pid = $row[8];
	$last_round = $row[9];

	$edit_person_url = "person.php?id=".urlencode($pid).
		"&next_url=".urlencode($_SERVER['REQUEST_URI']);
	?><tr>
<td class="ordinal_col"><a href="<?php h($edit_person_url)?>"><?php h($ordinal)?></a></td>
<td class="name_col"><?php h($name)?></td>
<td class="status_col"><?php format_person_status($status)?></td>
<td class="record_col"><?php h($record)?></td>
<td class="next_assignment_col"><?php
	if ($next_c) {
		$url = "contest.php?id=".urlencode($next_c)."&next_url=".urlencode($_SERVER['REQUEST_URI']);
		h(format_time_s($next_time) . " at $next_venue");
		?><a href="<?php h($url)?>"><img src="images/edit.gif" width="18" height="18" border="0"></a>
		<?php
	} else {
		h("<none>");
	}?></td>
<td>
	<?php
	if ($status == 'ready' && !$next_c) {
	$next_round = $last_round ? ($last_round+1) : 1;
	$drop_out_url = "person.php?id=".urlencode($pid)."&status=absent".
		"&next_url=".urlencode($_SERVER['REQUEST_URI']);
	$schedule_url = "scheduler.php?tournament=".urlencode($tournament_id).
		"&add_player=".urlencode($pid).
		"&new_contest_round=".urlencode($next_round).
		"&new_contest_label=".urlencode($record).
		"&next_url=".urlencode($_SERVER['REQUEST_URI']);
?><a href="<?php h($schedule_url)?>">Schedule Next Match</a>
|
<a href="<?php h($drop_out_url)?>">Drop Out</a>
<?php	} ?>
</td>
</tr>
<?php
}

?>
</table>

<?php 
	$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);
?>
<p>
<a href="<?php h($next_url)?>">Continue</a>
</p>
</form>

<?php
end_page();
