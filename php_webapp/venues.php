<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

$tournament_info = array();
if (isset($_GET['tournament'])) {

	$tournament_id = $_GET['tournament'];
	is_director($tournament_id)
		or die("Not authorized");
}
else {
	die("Invalid request");
}

// find out whether this tournament is multi-venue...
//TODO

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	die("Invalid request.");
}

begin_page("Tournament Venues");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table border="1">
<tr>
<th>Venue Name</th>
<th>Status</th>
</tr>
<?php

$sql = "SELECT id,venue_name,venue_status
	FROM venue
	WHERE tournament=".db_quote($tournament_id);
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {
	$edit_url = "venue.php?id=".urlencode($row[0]).'&next_url='.urlencode($_SERVER['REQUEST_URI']);
	$d = array(
	'venue_name' => $row[1],
	'venue_status' => $row[2]
	);

	?>
<tr>
<td class="venue_name_col"><a href="<?php h($edit_url)?>"><?php h($d['venue_name'])?></a></td>
<td class="venue_status_col"><?php h($d['venue_status'])?></td>
</tr>
<?php
}
?>
</table>

<?php
$new_venue_url = "venue.php?tournament=".urlencode($tournament_id)."&next_url=".urlencode($_SERVER['REQUEST_URI']);
$next_url = $_REQUEST['next_url'] ?: "tournament_dashboard.php?tournament=".urlencode($tournament_id);

?>
<p>
<a href="<?php h($new_venue_url)?>">New Venue</a> |
<a href="<?php h($next_url)?>">Go Back</a>
</p>

<?php
end_page();
