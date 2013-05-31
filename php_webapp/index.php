<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

?><!DOCTYPE HTML>
<html>
<head>
<title>Tournament Director</title>
</head>
<body>
<h1>Tournament Director</h1>
<p>
Welcome to Tournament Director.
Please select a tournament.
</p>

<table border="1">
<tr>
<th>Tournament Name</th>
<th>Location</th>
<th>Starts</th>
</tr>
<?php
$sql = "SELECT id,name,location,start_time FROM tournament
	ORDER BY id";
$query = mysqli_query($database, $sql);

while ($row = mysqli_fetch_row($query)) {

	$name = $row[1];
	$location = $row[2];
	$start_time = $row[3];

	$url = "tournament_dashboard.php?tournament=".urlencode($row[0]);
	?>
	<tr>
	<td class="name_col"><a href="<?php h($url)?>"><?php h($name)?></a></td>
	<td class="location_col"><?php h($location)?></td>
	<td class="start_time_col"><?php h($start_time)?></td>
	</tr>
<?php
}

?>
</table>
</body>
</html>
