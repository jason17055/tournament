<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

if (isset($_GET['id'])) {
	$tournament_id = $_GET['id'];

	$sql = "SELECT
		name,location,start_time,multi_game
		FROM tournament
		WHERE id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = $row[0];
		$_REQUEST['location'] = $row[1];
		$_REQUEST['start_time'] = $row[2];
		$_REQUEST['multi_game'] = ($row[3]=='Y')?'1':null;
	}
}
else {
	die("Invalid query string");
}

is_director($tournament_id)
	or die("Not authorized");


if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = $_REQUEST['next_url'] ?: '.';

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	if (isset($_REQUEST['action:update_tournament'])) {
		$sql = "UPDATE tournament
		SET name=".db_quote($_REQUEST['name']).",
		location=".db_quote($_REQUEST['location']).",
		start_time=".db_quote($_REQUEST['start_time']).",
		multi_game=".db_quote($_REQUEST['multi_game']?'Y':'N')."
		WHERE id=".db_quote($tournament_id);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

begin_page("Edit Tournament");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="name_entry">Name:</label></td>
<td><input type="text" id="name_entry" name="name" value="<?php h($_REQUEST['name'])?>"></td>
</tr>
<tr>
<td><label for="location_entry">Location:</label></td>
<td><input type="text" id="location_entry" name="location" value="<?php h($_REQUEST['location'])?>"></td>
</tr>
<tr>
<td><label for="start_time_entry">Start Time:</label></td>
<td><input type="text" id="start_time_entry" name="start_time" value="<?php h($_REQUEST['start_time'])?>"></td>
</tr>
<tr>
<td valign="top">Options:</td>
<td>
<div><label><input type="checkbox" name="multi_game"<?php echo($_REQUEST['multi_game']?' checked="checked"':'')?>>Multi Game Tournament</label></div>
</td>
</tr>
</table>

<div class="form_buttons_bar">
<?php if ($_GET['id']) { ?>
<button type="submit" name="action:update_tournament">Apply Changes</button>
<button type="submit" name="action:delete_tournament">Delete Tournament</button>
<?php } else { ?>
<button type="submit" name="action:create_tournament">Create Tournament</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
