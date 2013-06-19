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
	ORDER BY entry_rank DESC, name ASC";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

echo '{"players":[';
$count = 0;
while ($row = mysqli_fetch_row($query)) {
	if ($count++) { echo ",\n"; };
	$p = array(
		'pid' => $row[0],
		'name' => $row[1],
		'entryRank' => $row[2]
		);
	echo json_encode($p);
}
echo "],\n";
echo '"games":[';

echo "]\n";
echo "}\n";
