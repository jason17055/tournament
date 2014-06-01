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
<th><?php h($tournament_info['use_teams'] ? 'Team' : 'Player')?></th>
<th>Status</th>
<th>Record</th>
<th>Next Game</th>
</tr>
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
