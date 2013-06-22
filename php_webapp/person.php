<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = "";
		$_REQUEST['member_number'] = "";
		$_REQUEST['entry_rank'] = "";
		$_REQUEST['home_location'] = "";
		$_REQUEST['mail'] = "";
		$_REQUEST['status'] = "";
	}
}
else if (isset($_GET['id'])) {
	$sql = "SELECT tournament,
		name,member_number,entry_rank,home_location,mail,status
		FROM person WHERE id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Invalid person id");
	$tournament_id = $row[0];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = $row[1];
		$_REQUEST['member_number'] = $row[2];
		$_REQUEST['entry_rank'] = $row[3];
		$_REQUEST['home_location'] = $row[4];
		$_REQUEST['mail'] = $row[5];
		$_REQUEST['status'] = $row[6];
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

	if (!is_director($tournament_id)) {
		die("Not authorized.");
	}

	if (isset($_REQUEST['action:create_person'])) {

		$sql = "INSERT INTO person (tournament,name,member_number,entry_rank,home_location,mail,status)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['name']).",
			".db_quote($_REQUEST['member_number']).",
			".db_quote($_REQUEST['entry_rank']).",
			".db_quote($_REQUEST['home_location']).",
			".db_quote($_REQUEST['mail']).",
			".db_quote($_REQUEST['status'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));

		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:update_person'])) {

		$sql = "UPDATE person
			SET name=".db_quote($_REQUEST['name']).",
			member_number=".db_quote($_REQUEST['member_number']).",
			entry_rank=".db_quote($_REQUEST['entry_rank']).",
			home_location=".db_quote($_REQUEST['home_location']).",
			mail=".db_quote($_REQUEST['mail']).",
			status=".db_quote($_REQUEST['status'])."
			WHERE id=".db_quote($_REQUEST['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		header("Location: $next_url");
		exit();
	}

	else {
		die("Invalid POST");
	}
}

begin_page(isset($_GET['id']) ? "Edit Player" : "New Player");

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
<td><label for="entry_rank_entry">Entry Rank:</label></td>
<td><input type="text" id="entry_rank_entry" name="entry_rank" value="<?php h($_REQUEST['entry_rank'])?>"></td>
</tr>
<tr>
<td><label for="home_location_entry">Home Location:</label></td>
<td><input type="text" id="home_location_entry" name="home_location" value="<?php h($_REQUEST['home_location'])?>"></td>
</tr>
<tr>
<td><label for="mail_entry">Email Address:</label></td>
<td><input type="text" id="mail_entry" name="mail" value="<?php h($_REQUEST['mail'])?>"></td>
</tr>
<tr>
<td><label for="status_cb">Status:</label></td>
<td><?php select_widget(array(
	'name' => 'status',
	'value' => $_REQUEST['status'],
	'options' => array(""=>"--select--",
		"ready"=>"Ready",
		"not_ready"=>"Not Ready",
		"absent" => "Absent"
		)
	))?></td>
</tr>
</table>

<div class="form_buttons_bar">
<?php if (isset($_GET['id'])) { ?>
<button type="submit" name="action:update_person">Update Player</button>
<button type="submit" name="action:delete_person">Delete Player</button>
<?php } else { ?>
<button type="submit" name="action:create_person">Create Player</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
if (isset($_GET['id'])) {
$scorecard_url = "player_scorecard.php?id=".urlencode($_REQUEST['id']).
	'&next_url='.urlencode($_SERVER['REQUEST_URI']);
?>
<p>
<a href="<?php h($scorecard_url)?>">Scorecard</a>
</p>

<?php
} //endif existing player

end_page();
