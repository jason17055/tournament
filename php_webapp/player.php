<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];
}
else if (isset($_GET['id'])) {
	$sql = "SELECT tournament,
		name,member_number,home_location
		FROM player WHERE id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Invalid player id");
	$tournament_id = $row[0];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = $row[1];
		$_REQUEST['member_number'] = $row[2];
		$_REQUEST['home_location'] = $row[3];
	}
}
else {
	die("Invalid query string");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:create_player'])) {

		$sql = "INSERT INTO player (tournament,name,member_number,home_location)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['name']).",
			".db_quote($_REQUEST['member_number']).",
			".db_quote($_REQUEST['home_location'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

begin_page($_GET['id'] ? "Edit Player" : "New Player");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="name_entry">Name:</label></td>
<td><input type="text" id="name_entry" name="name" value="<?php h($_REQUEST['name'])?>"></td>
</tr>
<tr>
<td><label for="member_number_entry">Member Number:</label></td>
<td><input type="text" id="member_number_entry" name="member_number" value="<?php h($_REQUEST['member_number'])?>"></td>
</tr>
<tr>
<td><label for="home_location_entry">Home Location:</label></td>
<td><input type="text" id="home_location_entry" name="home_location" value="<?php h($_REQUEST['home_location'])?>"></td>
</tr>
</table>

<div class="form_buttons_bar">
<?php if ($_GET['id']) { ?>
<button type="submit" name="action:update_player">Update Player</button>
<button type="submit" name="action:delete_player">Delete Player</button>
<?php } else { ?>
<button type="submit" name="action:create_player">Create Player</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
