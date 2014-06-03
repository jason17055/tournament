<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');
require_once('includes/form.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = "";
		$_REQUEST['ordinal'] = "";
		$_REQUEST['member_number'] = "";
		$_REQUEST['entry_rank'] = "";
		$_REQUEST['rating'] = "";
		$_REQUEST['home_location'] = "";
		$_REQUEST['mail'] = "";
		$_REQUEST['phone'] = "";
		$_REQUEST['status'] = "ready";
	}
}
else if (isset($_GET['id'])) {
	$sql = "SELECT tournament,
		name,member_number,entry_rank,rating,home_location,mail,phone,status,
		ordinal
		FROM person WHERE id=".db_quote($_GET['id']);
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$row = mysqli_fetch_row($query)
		or die("Invalid person id");
	$tournament_id = $row[0];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = $row[1];
		$_REQUEST['member_number'] = $row[2];
		$_REQUEST['entry_rank'] = $row[3];
		$_REQUEST['rating'] = $row[4];
		$_REQUEST['home_location'] = $row[5];
		$_REQUEST['mail'] = $row[6];
		$_REQUEST['phone'] = $row[7];
		default_form_property('status', $row[8]);
		$_REQUEST['ordinal'] = $row[9];
	}
}
else {
	die("Invalid query string");
}

$sql = "SELECT ratings,use_person_member_number,use_person_entry_rank,
	use_person_home_location,use_person_mail,use_person_phone
	FROM tournament t
	WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query)
	or die("Tournament $tournament_id Not Found");
$tournament_info = array(
	'ratings' => $row[0]=='Y',
	'use_person_member_number' => $row[1]=='Y',
	'use_person_entry_rank' => $row[2]=='Y',
	'use_person_home_location' => $row[3]=='Y',
	'use_person_mail' => $row[4]=='Y',
	'use_person_phone' => $row[5]=='Y'
	);

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

		if (!$_REQUEST['status']) {
			die("Status must be set.");
		}

		$sql = "INSERT INTO person (tournament,name,member_number,entry_rank,rating,home_location,mail,phone,status,ordinal)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['name']).",
			".db_quote($_REQUEST['member_number']).",
			".db_quote($_REQUEST['entry_rank']).",
			".db_quote($_REQUEST['rating']).",
			".db_quote($_REQUEST['home_location']).",
			".db_quote($_REQUEST['mail']).",
			".db_quote($_REQUEST['phone']).",
			".db_quote($_REQUEST['status']).",
			".db_quote($_REQUEST['ordinal'])."
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
			rating=".db_quote($_REQUEST['rating']).",
			home_location=".db_quote($_REQUEST['home_location']).",
			mail=".db_quote($_REQUEST['mail']).",
			phone=".db_quote($_REQUEST['phone']).",
			status=".db_quote($_REQUEST['status']).",
			ordinal=".db_quote($_REQUEST['ordinal'])."
			WHERE id=".db_quote($_REQUEST['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		header("Location: $next_url");
		exit();
	}

	else if (isset($_REQUEST['action:delete_person'])) {

		mysqli_autocommit($database, FALSE);

		$sql = "DELETE FROM contest_participant
			WHERE player=".db_quote($_GET['id']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		$sql = "DELETE FROM person WHERE id=".db_quote($_GET['id'])."
			AND tournament=".db_quote($tournament_id);
		mysqli_query($database, $sql);

		mysqli_commit($database);

		header("Location: $next_url");
		exit();
	}
	else {
		die("Invalid POST");
	}
}

begin_page(isset($_GET['id']) ? "Edit Player" : "New Player");

$form_id = isset($_GET['id']) ? 'edit_person_form' : 'new_person_form';

?>
<form id="<?php h($form_id)?>"
	method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="name_entry">Name:</label></td>
<td><input type="text" id="name_entry" name="name" value="<?php h($_REQUEST['name'])?>"></td>
</tr>
<tr>
<td><label for="ordinal_entry">Ordinal:</label></td>
<td><input type="text" id="ordinal_entry" name="ordinal" value="<?php h($_REQUEST['ordinal'])?>"></td>
</tr>
<?php if ($tournament_info['use_person_member_number']) { ?>
<tr>
<td><label for="member_number_entry">Member Number:</label></td>
<td><input type="text" id="member_number_entry" name="member_number" value="<?php h($_REQUEST['member_number'])?>"></td>
</tr>
<?php } ?>
<?php if ($tournament_info['use_person_entry_rank']) { ?>
<tr>
<td><label for="entry_rank_entry">Entry Rank:</label></td>
<td><input type="text" id="entry_rank_entry" name="entry_rank" value="<?php h($_REQUEST['entry_rank'])?>"></td>
</tr>
<?php } ?>
<?php if ($tournament_info['ratings']) { ?>
<tr>
<td><label for="rating_entry">Current Rating:</label></td>
<td><input type="text" id="rating_entry" name="rating" value="<?php h($_REQUEST['rating'])?>"></td>
</tr>
<?php } ?>
<?php if ($tournament_info['use_person_home_location']) { ?>
<tr>
<td><label for="home_location_entry">Home Location:</label></td>
<td><input type="text" id="home_location_entry" name="home_location" value="<?php h($_REQUEST['home_location'])?>"></td>
</tr>
<?php } ?>
<?php if ($tournament_info['use_person_mail']) { ?>
<tr>
<td><label for="mail_entry">Email Address:</label></td>
<td><input type="text" id="mail_entry" name="mail" value="<?php h($_REQUEST['mail'])?>"></td>
</tr>
<?php } ?>
<?php if ($tournament_info['use_person_phone']) { ?>
<tr>
<td><label for="phone_entry">Telephone Number:</label></td>
<td><input type="text" id="phone_entry" name="phone" value="<?php h($_REQUEST['phone'])?>"></td>
</tr>
<?php } ?>
<tr>
<td><label for="status_cb">Status:</label></td>
<td><?php select_widget(array(
	'name' => 'status',
	'value' => $_REQUEST['status'],
	'options' => array(
		"ready"=>"Ready",
		"prereg"=>"Pre-Registered",
		""=>"Unregistered",
		"absent" => "Absent"
		)
	))?></td>
</tr>
</table>

<div class="form_buttons_bar">
<?php if (isset($_GET['id'])) { ?>
<button type="submit" name="action:update_person">Update Player</button>
<button type="submit" name="action:delete_person" onclick="return confirm('Really delete this person?')">Delete Player</button>
<?php } else { ?>
<button type="submit" name="action:create_person">Create Player</button>
<?php } ?>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php
if (isset($_GET['id'])) {

$sql = "SELECT attrib,value FROM person_attrib_float
	WHERE person=".db_quote($_GET['id'])."
	ORDER BY attrib";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
if (mysqli_num_rows($query) != 0) {
?>
<div>Other Attributes:</div>
<table border="1">
<?php

while ($row = mysqli_fetch_row($query)) {
	$attrib_name = $row[0];
	$value = $row[1];
	?><tr>
<td><?php h($attrib_name)?></td>
<td><?php h($value)?></td>
</tr>
<?php
}//end each attribute
?>
</table>
<?php
} //end if any attributes

$scorecard_url = "player_scorecard.php?id=".urlencode($_REQUEST['id']).
	'&next_url='.urlencode($_SERVER['REQUEST_URI']);
?>
<p>
<a href="<?php h($scorecard_url)?>">Scorecard</a>
</p>

<?php
} //endif existing player

end_page();
