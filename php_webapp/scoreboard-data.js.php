<?php

require_once('config.php');
require_once('includes/db.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,location,start_time FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
$row = mysqli_fetch_row($query);
if (!$row) {
	header("HTTP/1.0 404 Not Found");
	exit();
}
$tournament_info = array(
	'name' => $row[0],
	'location' => $row[1],
	'start_time' => $row[2]
	);

header("Content-Type: text/json");

echo "{\n";
echo '"tournament":'.json_encode($tournament_info).",\n";

$sql = "SELECT id,name,entry_rank,member_number,ordinal
	FROM person
	WHERE tournament=".db_quote($tournament_id)."
	AND status IS NOT NULL
	AND status NOT IN ('prereg')
	ORDER BY entry_rank DESC, name ASC";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

echo '"players":[';
$count = 0;
while ($row = mysqli_fetch_row($query)) {
	if ($count++) { echo ",\n"; }
	$p = array(
		'pid' => $row[0],
		'name' => $row[1],
		'entryRank' => $row[2],
		'member_number' => $row[3],
		'ordinal' => $row[4]
		);
	echo json_encode($p);
}
echo "],\n";
echo '"games":[';

$sql = "SELECT c.id,c.status,c.scenario
	FROM contest c
	JOIN tournament t ON t.id=c.tournament
	WHERE t.id=".db_quote($tournament_id)."
	AND c.status IN ('completed','started')
	AND (c.session_num IS NULL OR c.session_num=t.current_session)
	ORDER BY c.id";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

$count = 0;
while ($row=mysqli_fetch_row($query)) {
	$contest_id = $row[0];
	$game_status = $row[1];
	$scenario = $row[2];

	$g = array();
	if ($game_status != 'completed') {
		$g['in_progress'] = true;
	}
	if ($row[2]) {
		$g['scenario'] = $row[2];
	}

	$sql = "SELECT a.player,a.placement,a.seat
		FROM contest_participant a
		WHERE a.contest=".db_quote($contest_id);
	$query1 = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$seat_count=0;
	$all_seats = array();
	while ($row1 = mysqli_fetch_row($query1))
	{
		$a_seat = $row1[2] ?: (++$seat_count);
		$g['player.'.$a_seat] = $row1[0];
		$all_seats[] = $a_seat;

		if ($row1[1] == 1) {
			$g['winner'] = $a_seat;
		}
	}

	$g['seats'] = implode(',', $all_seats);
	if ($count++) { echo ",\n"; }
	echo json_encode($g);
}
echo "]\n";
echo "}\n";
