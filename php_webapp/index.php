<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

begin_page('Tournament Director');
?>
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
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

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
<?php

if (is_sysadmin()) {
$new_tournament_url = 'tournament.php';
?>
<p>
<a href="<?php h($new_tournament_url)?>">New Tournament</a>
</p>
<?php
} //endif is_sysadmin

end_page();
