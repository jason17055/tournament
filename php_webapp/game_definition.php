<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

if (isset($_GET['id'])) {

	$sql = "SELECT
		tournament,name,seat_names
		FROM game_definition
		WHERE id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Not Found");

	$tournament_id = $row[0];
	is_director($tournament_id)
		or die("Not authorized");

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = $row[1];
		$_REQUEST['seat_names'] = $row[2];
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


if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$next_url = isset($_REQUEST['next_url']) ? $_REQUEST['next_url'] : '.';

	if (isset($_REQUEST['action:cancel'])) {
		header("Location: $next_url");
		exit();
	}

	if (isset($_REQUEST['action:create_game_definition'])) {

		is_director($tournament_id)
			or die("Not authorized");

		$sql = "INSERT INTO game_definition (tournament,name,seat_names)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['name']).",
			".db_quote($_REQUEST['seat_names'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));
		$game_id = mysqli_insert_id($database);

		header("Location: $next_url");
		exit();
	}
	else if (isset($_REQUEST['action:update_game_definition'])) {

		is_director($tournament_id)
			or die("Not authorized");

		$sql = "UPDATE game_definition
			SET name=".db_quote($_REQUEST['name']).",
			seat_names=".db_quote($_REQUEST['seat_names'])."
			WHERE id=".db_quote($_GET['id'])."
			AND tournament=".db_quote($tournament_id);
		mysqli_query($database, $sql)
			or die(db_error($database));
		$game_id = mysqli_insert_id($database);

		header("Location: $next_url");
		exit();
	}
	else {
		die("Invalid POST");
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['id']))
{
	// find out whether this tournament is multi-game,
	//   and whether any games have been defined for it yet
	$sql = "SELECT multi_game,
		(SELECT MIN(id) FROM game_definition g WHERE g.tournament=t.id) AS game_id
		FROM tournament t
		WHERE id=".db_quote($tournament_id);
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$row = mysqli_fetch_row($query)
		or die("Not Found");

	if ($row[0] == 'N' && $row[1]) {

		// let user edit the existing game definition
		$new_url = $_SERVER['REQUEST_URI'] . '&id=' . urlencode($row[1]);
		header("Location: $new_url");
		exit();
	}

	// defaults for new game definition
	$_REQUEST['name'] = '';
	$_REQUEST['seat_names'] = '';
}

begin_page(isset($_GET['id']) ? "Edit Game Definition" : "New Game Definition");

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="name_entry">Name of Game:</label></td>
<td><input type="text" id="name_entry" name="name" value="<?php h($_REQUEST['name'])?>"></td>
</tr>
<tr>
<td><label for="seat_names_entry">Seat names:</label></td>
<td><input type="text" id="seat_names_entry" name="seat_names" value="<?php h($_REQUEST['seat_names'])?>"></td>
</tr>
</table>

<div class="form_buttons_bar">
<?php if (isset($_GET['id'])) { ?>
<button type="submit" name="action:update_game_definition">Apply Changes</button>
<button type="submit" name="action:delete_game_definition" onclick="return confirm('Really delete this game definition?')">Delete Game Definition</button>
<?php } else { ?>
<button type="submit" name="action:create_game_definition">Create Game Definition</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
end_page();
