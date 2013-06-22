<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

$tournament_id = $_GET['tournament'];
if (!$tournament_id) {
	header("HTTP/1.0 404 Not Found");
	die("Invalid tournament number");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = isset($_REQUEST['next_url']) ? $_REQUEST['next_url'] :
		'tournament_dashboard.php?tournament='.urlencode($tournament_id);

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	if (!is_director($tournament_id)) {
		die("Not authorized.");
	}

	if (isset($_REQUEST['action:import_tdlist'])) {

		$lines = explode("\n", file_get_contents('http://www.usgo.org/ratings/TDListA.txt'));
		$count = 0;
		foreach ($lines as $l) {
			$count++;
			$l = chop($l);
			$parts = explode("\t", $l);
			$name = trim($parts[0], " ,");
			$member_number = $parts[1];
			$rating = $parts[3];
			$home = strlen($parts[5]) ? "$parts[5], $parts[6]" : $parts[6];

			$sql = "INSERT INTO person (tournament,name,member_number,status,home_location,rating)
				SELECT ".db_quote($tournament_id).",
				".db_quote($name).",
				".db_quote($member_number).",
				NULL,
				".db_quote($home).",
				".db_quote($rating)."
				FROM dual
				WHERE ".db_quote($member_number)." NOT IN (SELECT member_number FROM person WHERE tournament=".db_quote($tournament_id).")";
			mysqli_query($database, $sql)
				or die("SQL error: ".db_error($database));
			$id = mysqli_insert_id($database);

			if ($id == 0) { //already exists
			$sql = "UPDATE person
				SET name=".db_quote($name).",
				home_location=".db_quote($home).",
				rating=".db_quote($rating)."
				WHERE tournament=".db_quote($tournament_id)."
				AND member_number=".db_quote($member_number);
			mysqli_query($database, $sql)
				or die("SQL error: ".db_error($database));
			} //endif already exists
		}

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

begin_page("Import Players");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<p>
<button type="submit" name="action:import_tdlist">Import TDList</button>
</p>

<div class="form_buttons_bar">
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
