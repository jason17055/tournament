<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

$sql = "SELECT tournament, name
	FROM person WHERE id=".db_quote($_GET['id']);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query)
	or die("Not Found");
$tournament_id = $row[0];
$person_id = $_GET['id'];
$person_info = array(
	id => $person_id,
	tournament => $tournament_id,
	name => $row[1]
	);

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	die("Not implemented");
}

begin_page("$person_info[name] - Scorecard");

$sql = "SELECT c.id,
	session_num,
	CONCAT(c.round,'-',c.board) AS contest_name,
	(SELECT GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ')
		FROM contest_participant cp
			JOIN person p ON p.id=cp.player
		WHERE cp.contest=c.id
		AND cp.player<>cp1.player
		) AS opponents,
	cp1.placement
	FROM contest_participant cp1
		JOIN contest c ON c.id=cp1.contest
	WHERE cp1.player=".db_quote($person_id)."
	ORDER BY c.session_num,c.round,c.board,c.id
	";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

?>
<table border="1">
<tr>
<th>Session</th>
<th>Round-Board</th>
<th>Against</th>
<th>Placement</th>
</tr>
<?php

while ($row = mysqli_fetch_row($query)) {
	$url = "contest.php?id=".urlencode($row[0]);
	$session_num = $row[1];
	$contest_name = $row[2];
	$opponents = $row[3];
	$placement = $row[4];
	if ($placement == 1) {
		$placement = "1st";
	}else if ($placement == 2) {
		$placement = '2nd';
	}else if ($placement == 3) {
		$placement = "3rd";
	}else if ($placement >= 4 && $placement <= 20) {
		$placement = $placement .= "th";
	}
?>
<tr>
<td class="session_num_col"><?php h($session_num)?></td>
<td class="contest_name_col"><a href="<?php h($url)?>"><?php h($contest_name)?></a></td>
<td class="opponents_col"><?php h($opponents)?></td>
<td class="placement_col"><?php h($placement)?></td>
</tr>
<?php
} // end foreach contest

?>
</table>
<?php

$go_back_url = 'person.php?id='.urlencode($_GET['id']);
?>
<p>
<a href="<?php h($go_back_url)?>">Go Back</a>
</p>

<?php
end_page();
