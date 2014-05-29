<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

if (isset($_GET['tournament'])) {
	$tournament_id = $_GET['tournament'];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = "";
		$_REQUEST['ordinal'] = "";
		$_REQUEST['status'] = "ready";
	}
}
else if (isset($_GET['id'])) {
	$sql = "SELECT tournament,
		name,ordinal,status
		FROM person WHERE id=".db_quote($_GET['id'])."
		AND is_team='Y'";
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$row = mysqli_fetch_row($query)
		or die("Invalid person id");
	$tournament_id = $row[0];

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		$_REQUEST['name'] = $row[1];
		$_REQUEST['ordinal'] = $row[2];
		$_REQUEST['status'] = $row[3];

		$sql = "SELECT ordinal,name,phone FROM person
			WHERE is_team='N'
			AND member_of=".db_quote($_GET['id'])."
			AND tournament=".db_quote($tournament_id)."
			ORDER BY ordinal ASC";
		$query = mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		while ($row = mysqli_fetch_row($query)) {
			$ordinal = $row[0];
			$mem_name = $row[1];
			$mem_phone = $row[2];
			$_REQUEST['p'.$ordinal.'_name']=$mem_name;
			$_REQUEST['p'.$ordinal.'_phone']=$mem_phone;
		}
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

	if (isset($_REQUEST['action:create_team'])) {

		if (!$_REQUEST['status']) {
			die("Status must be set.");
		}

		mysqli_autocommit($database, FALSE);

		if (!$_REQUEST['ordinal']) {
			$sql = "SELECT MAX(ordinal) FROM person
				WHERE tournament=".db_quote($tournament_id)."
				AND member_of IS NULL";
			$query = mysqli_query($database, $sql);
			$row = mysqli_fetch_row($query);
			$_REQUEST['ordinal'] = 1+($row[0] ?: 0);
		}

		$sql = "INSERT INTO person (tournament,name,is_team,status,ordinal)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($_REQUEST['name']).",
			'Y',
			".db_quote($_REQUEST['status']).",
			".db_quote($_REQUEST['ordinal'])."
			)";
		mysqli_query($database, $sql)
			or die(db_error($database));

		$new_team_id = mysqli_insert_id($database);

		for ($member_num = 1; $member_num <= 2; $member_num++) {
			$sql = "INSERT INTO person (tournament,ordinal,name,phone,member_of)
			VALUES (
			".db_quote($tournament_id).",
			".db_quote($member_num).",
			".db_quote($_REQUEST['p'.$member_num.'_name']).",
			".db_quote($_REQUEST['p'.$member_num.'_phone']).",
			".db_quote($new_team_id)."
			)";
			mysqli_query($database, $sql)
				or die("SQL error: ".db_error($database));
		}

		mysqli_commit($database);

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

	else if (isset($_REQUEST['action:delete_team'])) {

		mysqli_autocommit($database, FALSE);

		$sql = "DELETE FROM person WHERE member_of=".db_quote($_GET['id'])."
			AND tournament=".db_quote($tournament_id);
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

begin_page(isset($_GET['id']) ? "Edit Team" : "New Team");

$form_id = isset($_GET['id']) ? 'edit_person_form' : 'new_person_form';

?>
<form id="<?php h($form_id)?>"
	method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<table>
<tr>
<td><label for="name_entry">Team Name:</label></td>
<td><input type="text" id="name_entry" name="name" value="<?php h($_REQUEST['name'])?>"></td>
</tr>
<tr>
<td><label for="ordinal_entry">Ordinal:</label></td>
<td><input type="text" id="ordinal_entry" name="ordinal" value="<?php h($_REQUEST['ordinal'])?>">
<small>(leave blank to auto-assign)</small>
</td>
</tr>
<?php
	for ($member_number = 1; $member_number <= 2; $member_number++) {
		$cap = 'Player '.$member_number;
		$pre = 'p'.$member_number.'_';

		?>
<tr>
<td><label for="<?php h($pre.'name_entry')?>"><?php h($cap)?> Name:</label></td>
<td><input type="text" id="<?php h($pre.'name_entry')?>" name="<?php h($pre.'name')?>" value="<?php h($_REQUEST[$pre.'name'])?>"></td>
</tr>
<?php if ($tournament_info['use_person_phone']) { ?>
<tr>
<td><label for="<?php h($pre.'phone_entry')?>"><?php h($cap)?> Phone:</label></td>
<td><input type="text" id="<?php h($pre.'phone_entry')?>" name="<?php h($pre.'phone')?>" value="<?php h($_REQUEST[$pre.'phone'])?>"></td>
</tr>
<?php } ?>
<?php }//end each member ?>
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
<button type="submit" name="action:update_team">Update Team</button>
<button type="submit" name="action:delete_team">Delete Team</button>
<?php } else { ?>
<button type="submit" name="action:create_team">Create Team</button>
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
