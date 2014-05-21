<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,multi_game,multi_session,current_session,vocab_table,
	ratings,use_person_member_number,use_person_entry_rank,
	use_person_home_location,use_person_mail,use_person_phone
	FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	'id' => $tournament_id,
	'name' => $row[0],
	'multi_game' => $row[1],
	'multi_session' => $row[2],
	'current_session' => $row[3],
	'vocab_table' => $row[4],
	'ratings' => $row[5],
	'use_person_member_number' => $row[6],
	'use_person_entry_rank' => $row[7],
	'use_person_home_location' => $row[8],
	'use_person_mail' => $row[9],
	'use_person_phone' => $row[10],
	);

$page_title = "$tournament_info[name] - Dashboard";
begin_page($page_title);

$can_edit_players = is_director($tournament_id);

function make_popup_list($popup_id, $column_name)
{
	global $database;

?>
	<div class="popup_menu" id="<?php h($popup_id)?>">
	<ul><?php
		$sql = "SELECT type_data FROM column_type
			WHERE name=".db_quote($column_name);
		$query = mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$r1 = mysqli_fetch_row($query)
			or die("Error: Type data for $column_name not found");
		$tmp = preg_replace('/^enum:/', '', $r1[0]);
		$tmpa = explode(',', $tmp);
		foreach ($tmpa as $status_val) {
			?><li><a href="#"><?php h($status_val)?></a></li>
		<?php
		}
?></ul>
	</div>
<?php
}

make_popup_list('status_popup_menu', 'PERSON.STATUS');

$person_columns = array('ordinal','name','member_number','entry_rank',
	'home_location','mail','phone');
$person_column_names = array(
	'ordinal' => 'Ordinal',
	'name' => 'Player Name',
	'member_number' => 'Member Number',
	'entry_rank' => 'Entry Rank',
	'home_location' => 'Home Location',
	'mail' => 'Email Address',
	'phone' => 'Telephone'
	);

?>
<table border="1">
<caption>Players</caption>
<tr>
<?php if ($can_edit_players) { ?>
<th></th>
<?php } ?>
<?php
foreach ($person_columns as $col) { ?>
<th><?php h($person_column_names[$col])?></th>
<?php }//end foreach column ?>
<th>Status</th>
<th>Games Played</th>
<th>Games Won</th>
<th>Points</th>
<?php if ($tournament_info['ratings']) { ?>
<th>Current Rating</th>
<?php } ?>
</tr>
<?php
$sql = "SELECT p.id,p.name,p.status,
	(SELECT COUNT(DISTINCT contest) FROM contest_participant
			WHERE player=p.id) AS games_played,
	(SELECT COUNT(*) FROM contest c
		WHERE p.id IN (SELECT player FROM contest_participant
				WHERE contest=c.id
				AND placement=1)
		AND EXISTS (SELECT 1 FROM contest_participant
				WHERE contest=c.id
				AND NOT (placement=1))
		) AS games_won,
	(SELECT COUNT(*) FROM contest c
		WHERE p.id IN (SELECT player FROM contest_participant
				WHERE contest=c.id
				AND placement=1)
		AND EXISTS (SELECT 1 FROM contest_participant
				WHERE contest=c.id
				AND NOT (placement=1))
		AND c.session_num=".db_quote($tournament_info['current_session'])."
		) AS games_won_this_session,
	(SELECT SUM(w_points) FROM contest_participant
		WHERE player=p.id
		) AS w_points,
	(SELECT SUM(w_points) FROM contest_participant
		WHERE player=p.id
		AND contest IN (SELECT id FROM contest WHERE session_num=".db_quote($tournament_info['current_session']).")
		) AS w_points_this_session,
	IFNULL(r.post_rating,r.prior_rating) AS rating,
	p.member_number,p.entry_rank,p.home_location,
	p.mail,p.phone,p.ordinal
	FROM person p
	JOIN tournament t
		ON t.id=p.tournament
	LEFT JOIN player_rating r
		ON r.id=p.id
		AND r.session_num=t.current_session
	WHERE tournament=".db_quote($tournament_id)."
	AND p.status IS NOT NULL
	ORDER BY rating DESC, name ASC";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {

	$person_id = $row[0];
	$name = $row[1];
	$status = $row[2];
	$games_played = $row[3];
	$games_won = $row[4];
	$games_won_this_session = $row[5];
	$w_points = $row[6] ?: 0;
	$w_points_this_session = $row[7] ?: 0;
	$cur_rating = $row[8];

	$edit_url = "person.php?id=".urlencode($person_id);
	$url = 'player_scorecard.php?id='.urlencode($person_id);

	$d = array(
	'member_number' => $row[9],
	'entry_rank' => $row[10],
	'home_location' => $row[11],
	'mail' => $row[12],
	'phone' => $row[13],
	'ordinal' => $row[14]
	);

	?><tr>
<?php if ($can_edit_players) { ?>
<td class="link_col"><a href="<?php h($edit_url)?>"><img src="images/edit.gif" width="18" height="18" alt="Edit" border="0"></a></td>
<?php }
	foreach ($person_columns as $col) {
		if ($col == 'name') { ?>
<td class="name_col"><a href="<?php h($url)?>"><?php h($name)?></a></td>
<?php } else { ?>
<td class="<?php h($col)?>_col"><?php h($d[$col])?></td>
<?php } //end switch $col ?>
<?php } //end each $col ?>

<td class="status_col"><?php
	if ($status == 'ready') {
		?><img src="images/plus.png" width="14" height="14" alt=""><?php
	} else if ($status == 'absent') {
		?><img src="images/minus.png" width="14" height="14" alt=""><?php
	}
	h($status)?>
	<button type="button" class="popup_menu_btn" data-for="status_popup_menu">...</button>
	</td>
<td class="game_count_col"><?php h($games_played)?></td>
<td class="game_count_col"><?php h("$games_won (+$games_won_this_session)")?></td>
<td class="w_points_col"><?php h("$w_points (+$w_points_this_session)")?></td>
<?php if ($tournament_info['ratings']) { ?>
<td class="rating_col"><?php
	if (!is_null($cur_rating)) { h(sprintf('%.0f', $cur_rating));
		}?></td>
<?php } ?>
</tr>
<?php
} //end foreach person
?>
</table>

<?php
$sql = "SELECT COUNT(*) FROM person
	WHERE tournament=".db_quote($tournament_id)."
	AND status IS NULL";
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
if ($row[0]) {
	?>
<div>Not shown: <?php h(number_format($row[0]))?> unregistered players</div>
<?php
}

if ($can_edit_players) {
$new_person_url = "person.php?tournament=".urlencode($tournament_id);
$import_persons_url = "import_person.php?tournament=".urlencode($tournament_id);
$pairings_url = "pairings.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($new_person_url)?>">New Player</a>
| <a href="<?php h($import_persons_url)?>">Import Players</a>
| <a href="<?php h($pairings_url)?>">Generate Pairings</a>
</p>
<?php }


make_popup_list('contest_status_popup_menu', 'PLAY.STATUS');
 ?>

<table border="1">
<caption>Games</caption>
<tr>
<?php if ($tournament_info['multi_session']=='Y') { ?>
<th>Session</th>
<?php } ?>
<th>Started</th>
<th>Round-<?php
	echo($tournament_info['vocab_table']=='court'?'Court':'Table')?></th>
<?php if ($tournament_info['multi_game']=='Y') { ?>
<th>Game</th>
<?php } ?>
<th>Scenario</th>
<th>Status</th>
<th>Participants</th>
<th>Winner</th>
</tr>
<?php
$sql = "SELECT id,
	session_num,
	IFNULL(started,'(unknown)') AS started,
	CONCAT(round,'-',board) AS contest_name,
	game,scenario,status,
	(SELECT GROUP_CONCAT(
		p.name ORDER BY name SEPARATOR ', '
		)
		FROM contest_participant cp
			JOIN person p ON p.id=cp.player
		WHERE cp.contest=c.id
	) AS participants,
	(SELECT GROUP_CONCAT(
		CONCAT(p.name, IF(cp.score IS NOT NULL,
				CONCAT(' (',cp.score,')'),
				'')) ORDER BY name SEPARATOR ', '
		)
		FROM contest_participant cp
			JOIN person p ON p.id=cp.player
		WHERE cp.contest=c.id
		AND cp.placement=1
	) AS winner
	FROM contest c
	WHERE tournament=".db_quote($tournament_id)."
	ORDER BY session_num,round,started,board,id";
$query = mysqli_query($database, $sql);

while ($row = mysqli_fetch_row($query)) {

	$contest_id = $row[0];
	$session_num = $row[1];
	$started_date = $row[2];
	$contest_name = $row[3];
	$game = $row[4];
	$scenario = $row[5];
	$status = $row[6];
	$participants = $row[7];
	$winner = $row[8];

	$url = "contest.php?id=".urlencode($contest_id);
	?>
<tr>
<?php if ($tournament_info['multi_session']=='Y') { ?>
<td class="session_num_col"><?php h($session_num)?></td>
<?php } ?>
<td class="started_date_col"><a href="<?php h($url)?>"><?php h($started_date)?></a></td>
<td class="contest_name_col"><a href="<?php h($url)?>"><?php h($contest_name)?></a></td>
<?php if ($tournament_info['multi_game'] == 'Y') { ?>
<td class="game_col"><?php h($game)?></td>
<?php } ?>
<td class="scenario_col"><?php format_scenario($scenario)?></td>
<td class="status_col"><?php format_contest_status($status)?>
<button type="button" class="popup_menu_btn" data-for="contest_status_popup_menu">...</button>
</td>
<td class="participants_col"><?php h($participants)?></td>
<td class="winner_col"><?php h($winner)?></td>
</tr>
<?php
} //end foreach contest

?>
</table>

<?php
if (is_director($tournament_id)) {
$new_contest_url = "contest.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($new_contest_url)?>">New Game</a>
</p>

<?php
}//endif director


if (is_director($tournament_id)) {
$edit_tourney_url = "tournament.php?id=".urlencode($tournament_id).'&next_url='.urlencode($_SERVER['REQUEST_URI']);
$edit_game_url = "game_definition.php?tournament=".urlencode($tournament_id).'&next_url='.urlencode($_SERVER['REQUEST_URI']);
$run_ratings_url = "run_ratings.php?tournament=".urlencode($tournament_id);
?>
<p>
<a href="<?php h($edit_tourney_url)?>">Tournament Definition</a> |
<a href="<?php h($edit_game_url)?>">Game Definition</a> |
<a href="<?php h($run_ratings_url)?>">Run Ratings</a>
</p>

<?php
}//endif director

$scoreboard_url = 'scoreboard.html?tournament='.urlencode($tournament_id);
?>
<p>
<a href="<?php h($scoreboard_url)?>">Scoreboard</a>
| <a href="#" id="make_game_results_link">AGA Results File</a>
| <a href="<?php h('card_stats.php?tournament='.urlencode($tournament_id))?>">Dominion Card Stats</a>
</p>
<?php

end_page();
