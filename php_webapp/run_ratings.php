<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/ratings_functions.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	id => $tournament_id,
	name => $row[0]
	);

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}
	else if (isset($_REQUEST['action:run_ratings'])) {

		if ($_REQUEST['batch']) {
			do_ratings_pass($_REQUEST['batch']);
		}
		else {
			do_ratings($tournament_id);
		}
		exit();
	}
	else {
		die("Not implemented");
	}
}

$page_title = "$tournament_info[name] - Run Ratings";
begin_page($page_title);

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">

<button type="submit" name="action:run_ratings">Run Ratings</button>
<button type="submit" name="action:cancel">Cancel</button>

</form>
<?php
end_page();
