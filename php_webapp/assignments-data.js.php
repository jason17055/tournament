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

$sql = "SELECT id,name
	FROM person
	WHERE tournament=".db_quote($tournament_id)."
	AND status IS NOT NULL
	AND status NOT IN ('prereg')
	ORDER BY entry_rank DESC, name ASC";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

echo '{"players":['."\n";
$count = 0;
while ($row = mysqli_fetch_row($query)) {
	if ($count++) { echo ",\n"; }
	$p = array(
		'pid' => $row[0],
		'name' => $row[1]
		);
	echo json_encode($p);
}
echo "],\n";
echo '"contests":['."\n";

$sql = "SELECT c.id,c.round,c.board,c.status
	FROM contest c
	JOIN tournament t ON t.id = c.tournament
	WHERE t.id=".db_quote($tournament_id)."
	AND c.status IN ('completed','started','proposed')
	AND (c.session_num IS NULL OR c.session_num=t.current_session)
	ORDER BY c.id";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

$count = 0;
while ($row=mysqli_fetch_row($query)) {
	$c = array(
		'id' => $row[0],
		'round' => $row[1],
		'table' => $row[2],
		'status' => $row[3]
		);

	$sql = "SELECT IFNULL(seat,turn_order),player
		FROM contest_participant cp
		WHERE contest=".db_quote($c['id'])."
		ORDER BY IFNULL(seat,turn_order)";
	$query2 = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));

	$pp = array();
	while ($row2 = mysqli_fetch_row($query2)) {
		$p = array(
			'seat' => $row2[0],
			);
		if (!is_null($row2[1])) {
			$p['pid'] = $row2[1];
		}
		$pp[] = $p;
	}

	$c['players'] = $pp;

	if ($count++) { echo ",\n"; }
	echo json_encode($c);
}
echo "]\n";
echo "}\n";
