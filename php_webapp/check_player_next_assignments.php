<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');
require_once('includes/form.php');

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

begin_page("Check players assignments");

?>
<table border="1">
<tr>
<th class="ordinal_col"><?php h($tournament_info['use_teams'] ? 'Team Number' : 'Ordinal')?></th>
<th class="name_col"><?php h($tournament_info['use_teams'] ? 'Team Name' : 'Player')?></th>
<th class="status_col">Status</th>
<th class="record_col">Record</th>
<th class="next_assignment_col">Next Game</th>
</tr>
<?php

$sql = "SELECT is_team,ordinal,name,status,
		(SELECT value FROM person_attrib_value WHERE person=p.id AND attrib='wins_losses') AS record
	FROM person p
	WHERE tournament=".db_quote($tournament_id)."
	AND id IN (".db_quote_list(explode(',',$_REQUEST['players'])).")
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

	?><tr>
<td class="ordinal_col"><?php h($ordinal)?></td>
<td class="name_col"><?php h($name)?></td>
<td class="status_col"><?php format_person_status($status)?></td>
<td class="record_col"><?php h($record)?></td>
</tr>
<?php
}

?>
</table>

<?php 
	$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);
?>
<p>
<a href="<?php h($next_url)?>">Skip</a>
</p>
</form>

<?php
end_page();
