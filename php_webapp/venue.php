<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

$tournament_info = array();
if (isset($_GET['id'])) {

	$sql = "SELECT
		tournament,venue_name,venue_status
		FROM venue
		WHERE id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");

	$tournament_id = $row[0];
	is_director($tournament_id)
		or die("Not authorized");

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['venue_name'] = $row[1];
		$_REQUEST['venue_status'] = $row[2];
	}
}
else if (isset($_GET['tournament'])) {

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
	$next_url = isset($_REQUEST['next_url']) ? $_REQUEST['next_url'] : '.';

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	if (isset($_REQUEST['action:create_venue'])) {

		is_director($tournament_id)
			or die("Not authorized");

		$sql = "INSERT INTO venue (tournament,venue_name,venue_status)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['venue_name']).",
			".db_quote($_REQUEST['venue_status'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));
		$venue_id = mysqli_insert_id($database);

		header("Location: $next_url");
		exit();
	}
	else if (isset($_REQUEST['action:update_venue'])) {

		is_director($tournament_id)
			or die("Not authorized");

		if (!$_REQUEST['venue_name']) {
			die("Invalid venue name");
		}

		$sql = "UPDATE venue
			SET venue_name=".db_quote($_REQUEST['venue_name']).",
			venue_status=".db_quote($_REQUEST['venue_status'])."
			WHERE id=".db_quote($_GET['id'])."
			AND tournament=".db_quote($tournament_id);
		mysqli_query($database, $sql)
			or die(db_error($database));

		header("Location: $next_url");
		exit();
	}
	else if (isset($_REQUEST['action:delete_venue'])) {

		is_director($tournament_id)
			or die("Not authorized");

		$sql = "DELETE FROM reservation
			WHERE venue=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		$sql = "DELETE FROM venue
			WHERE id=".db_quote($_GET['id'])."
			AND tournament=".db_quote($tournament_id);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		header("Location: $next_url");
		exit();
	}
	else {
		die("Invalid POST");
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['id']))
{

	// defaults for new venue
	$_REQUEST['venue_name'] = '';
	$_REQUEST['venue_status'] = 'enabled';
}

begin_page(isset($_GET['id']) ? "Edit Venue" : "New Venue");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="venue_name_entry">Venue Name:</label></td>
<td><input type="text" id="venue_name_entry" name="venue_name" value="<?php h($_REQUEST['venue_name'])?>"></td>
</tr>
<tr>
<td><label for="venue_status_cb">Venue Status:</label></td>
<td><?php
	select_widget(array(
	'name' => 'venue_status',
	'id' => 'venue_status_cb',
	'value' => $_REQUEST['venue_status'],
	'options' => array('enabled' => 'Enabled', 'disabled' => 'Disabled')
	))?></td>
</tr>
</table>

<div class="form_buttons_bar">
<?php if (isset($_GET['id'])) { ?>
<button type="submit" name="action:update_venue">Apply Changes</button>
<button type="submit" name="action:delete_venue" onclick="return confirm('Really delete this venue?')">Delete Venue</button>
<?php } else { ?>
<button type="submit" name="action:create_venue">Create Venue</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
