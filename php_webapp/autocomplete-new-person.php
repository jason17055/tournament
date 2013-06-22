<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/auth.php');

$tournament_id = $_GET['tournament'];
is_director($tournament_id)
	or die("Not authorized");

$field = isset($_GET['field']) ? $_GET['field'] : 'name';

if ($field == 'name' && strlen($_GET['term']) >= 2) {
	$filt_sql = "name LIKE ".db_quote("%".$_GET['term']."%");
}
else if ($field == 'member_number') {
	$filt_sql = "member_number=".db_quote($_GET['term']);
}
else {
	die("Invalid query string");
}

$sql = "SELECT p.id,p.name,p.member_number,p.home_location,p.rating
	FROM person p
	WHERE tournament=".db_quote($tournament_id)."
	AND $filt_sql
	ORDER BY name ASC";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

header("Content-Type: text/json");
echo "[\n";

$count = 0;
while ($row = mysqli_fetch_row($query)) {
	if ($count++) { echo ",\n"; }

	$tmp = array(
		'pid' => $row[0],
		'name' => $row[1],
		'member_number' => $row[2],
		'home_location' => $row[3],
		'rating' => $row[4]
		);
	$tmp['value'] = $tmp[$field];
	echo json_encode($tmp);
}
echo "\n]\n";
