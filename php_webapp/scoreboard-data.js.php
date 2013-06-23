<?php

require_once('config.php');
require_once('includes/db.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT 1 FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
$row = mysqli_fetch_row($query);
if (!$row) {
	header("HTTP/1.0 404 Not Found");
	exit();
}

header("Content-Type: text/json");

$sql = "SELECT id,name,entry_rank
	FROM person
	WHERE tournament=".db_quote($tournament_id)."
	AND status IS NOT NULL
	AND status NOT IN ('prereg')
	ORDER BY entry_rank DESC, name ASC";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

echo '{"players":[';
$count = 0;
while ($row = mysqli_fetch_row($query)) {
	if ($count++) { echo ",\n"; }
	$p = array(
		'pid' => $row[0],
		'name' => $row[1],
		'entryRank' => $row[2]
		);
	echo json_encode($p);
}
echo "],\n";
echo '"games":[';

$sql = "SELECT a.player,b.player,a.placement,b.placement,c.status
	FROM contest_participant a
	CROSS JOIN contest_participant b
		ON b.contest=a.contest
		AND b.player<>a.player
	JOIN contest c ON c.id = a.contest
	WHERE c.tournament=".db_quote($tournament_id)."
	AND c.status IN ('completed','started')
	AND a.player < b.player
	ORDER BY c.id,a.player,b.player";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

$count = 0;
while ($row=mysqli_fetch_row($query)) {
	$g = array(
		'player1' => $row[0],
		'player2' => $row[1]
		);
	if ($row[4] != 'completed') {
		$g['in_progress'] = true;
	}
	else if (($row[2]?:9999) < ($row[3]?:9999)) {
		$g['winner']='b';
	}
	else if (($row[2]?:9999) > ($row[3]?:9999)) {
		$g['winner'] = 'w';
	}
	else {
		// no winner
		continue;
	}
	if ($count++) { echo ",\n"; }
	echo json_encode($g);
}
echo "]\n";
echo "}\n";
