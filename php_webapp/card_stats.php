<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,multi_game,multi_session,current_session FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	'id' => $tournament_id,
	'name' => $row[0],
	'multi_game' => $row[1],
	'multi_session' => $row[2],
	'current_session' => $row[3]
	);

$page_title = "$tournament_info[name] - Dashboard";
begin_page($page_title);

$can_edit_players = is_director($tournament_id);

$sql = "SELECT scenario
	FROM contest c
	WHERE tournament=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);

$card_counts = array();
while ($row = mysqli_fetch_row($query)) {

	$scenario = $row[0];
	$parsed = json_decode($scenario);
	if (!$parsed) {
		continue;
	}

	$kingdom_cards = $parsed->kingdom;
	foreach ($kingdom_cards as $card_name) {
		$card_counts[$card_name]++;
	}
}

arsort($card_counts);
	?>
<table border="1">
<tr>
<th>Card</th>
<th>Count</th>
</tr>
<?php

foreach ($card_counts as $card_name => $count) {
?>
<tr>
<td><?php h($card_name)?></td>
<td><?php h($count)?></td>
</tr>
<?php
}


end_page();
