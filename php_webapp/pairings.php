<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/pairing_functions.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,current_session,
	(SELECT MAX(round) FROM contest
		WHERE tournament=t.id
		AND session_num=t.current_session)
	FROM tournament t WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql);
$row = mysqli_fetch_row($query);
$tournament_info = array(
	id => $tournament_id,
	name => $row[0],
	current_session => $row[1],
	last_round => ($row[2] ?: 0)
	);

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if (isset($_REQUEST['action:cancel'])) {
		$next_url = $_REQUEST['next_url'] ?: 'tournament_dashboard.php?tournament='.urlencode($tournament_id);
		header("Location: $next_url");
		exit();
	}
}

$page_title = "$tournament_info[name] - Generate Pairings";
begin_page($page_title);

$_REQUEST['first_round'] = $_REQUEST['first_round'] ?: ($tournament_info['last_round']+1);
$_REQUEST['last_round'] = $_REQUEST['last_round'] ?: $_REQUEST['first_round'];

?>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<div>First round to pair:
<input type="text" size="4" name="first_round" value="<?php h($_REQUEST['first_round'])?>">
Last round to pair:
<input type="text" size="4" name="last_round" value="<?php h($_REQUEST['last_round'])?>">
<button type="submit" name="action:generate_pairings">GO</button>
<button type="submit" name="action:cancel">Cancel</button>
</div>
</form>

<?php

if (isset($_REQUEST['action:generate_pairings'])) {

$players = array();

$sql = "SELECT id,name,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($tournament_info['current_session'])."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 2=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count2p,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($tournament_info['current_session'])."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 3=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count3p,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($tournament_info['current_session'])."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 4=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count4p,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($tournament_info['current_session'])."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 5<=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count5p
	FROM person p
	WHERE tournament=".db_quote($tournament_id)."
	AND status='ready'";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

?>
<div class="driller_container">
<h2 class="driller_heading">Player Registration Data</h2>
<div class="driller_content">
<table border="1">
<caption>As of the start of this tournament session</caption>
<tr>
<th>Player</th>
<th>Rating</th>
<th>2p Games</th>
<th>3p Games</th>
<th>4p Games</th>
<th>5p+ Games</th>
</tr>
<?php

while ($row = mysqli_fetch_row($query))
{
	$pid = $row[0];
	$p = array(
		'name' => $row[1],
		'count2p' => $row[2],
		'count3p' => $row[3],
		'count4p' => $row[4],
		'count5p' => $row[5]
		);
	$players[$pid] = $p;

	?><tr>
<td><?php h($p['name'])?></td>
<td><?php h($p['rating'])?></td>
<td><?php h($p['count2p'])?></td>
<td><?php h($p['count3p'])?></td>
<td><?php h($p['count4p'])?></td>
<td><?php h($p['count5p'])?></td>
</tr>
<?php
}

?></table>
</div><!--/.driller_content-->
</div><!--/.driller_container-->

<?php

$weights = array();

?>
<div class="driller_container">
<h2 class="driller_heading">Previous Player Encounters</h2>
<div class="driller_content">
<table border="1">
<tr>
<th>Pairing</th>
<th>Played Before</th>
<th>Same Home Town?</th>
<th>Same Family?</th>
</tr>
<?php

$sql = "SELECT p.id, q.id,
	p.name, q.name,
	p.home_location, q.home_location,
	(SELECT COUNT(*) FROM contest c
		WHERE c.tournament=p.tournament
		AND c.session_num<".db_quote($tournament_info['current_session'])."
		AND c.status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND q.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		) AS nplays
	FROM person p
	CROSS JOIN person q ON q.tournament = p.tournament
		AND q.id>p.id
	WHERE p.tournament=".db_quote($tournament_id)."
	AND p.status='ready'
	AND q.status='ready'";
$query = mysqli_query($database, $sql);
while ($row = mysqli_fetch_row($query)) {
	$key = "$row[0],$row[1]";
	$p_name = $row[2];
	$q_name = $row[3];
	$p_home = $row[4];
	$q_home = $row[5];
	$nplays = $row[6];

	$same_home = $row[4] == $row[5];
	$same_family = $same_home && check_common_surname($p_name, $q_name);

	$weights[$key] = array(
		nplays => $nplays,
		same_home => $same_home,
		same_family => $same_family
		);

	?><tr><td><?php h("$p_name vs $q_name")?></td>
<td align="center"><?php h($nplays)?></td>
<td align="center"><?php h($same_home ? 'YES' : 'NO')?></td>
<td align="center"><?php h($same_family ? 'YES' : 'NO')?></td>
</tr>
<?php
}

?></table>
</div><!--/.driller_content-->
</div><!--/.driller_container-->
<?php

$games = array();
$sql = "SELECT id,round,board,
		(SELECT GROUP_CONCAT(
			player ORDER BY player SEPARATOR ','
			)
		FROM contest_participant
		WHERE contest=c.id
		) AS players
	FROM contest c
	WHERE tournament=".db_quote($tournament_id)."
	AND session_num=".db_quote($tournament_info['current_session'])."
	AND status<>'proposed'";
$query = mysqli_query($database, $sql);
while ($row = mysqli_fetch_row($query))
{
	$round = $row[1];
	$board = $row[2];
	$m_players = explode(',',$row[3]);
	$game = array(
		'round' => $round,
		'board' => $board,
		'players' => $m_players
		);
	$games[] = $game;
}

function order_by_round_and_board($a, $b)
{
	if ($a['round'] != $b['round']) {
		return $a['round'] > $b['round'] ? 1 : -1;
	}
	else if ($a['board'] != $b['board']) {
		return $a['board'] > $b['board'] ? 1 : -1;
	}
	else {
		return 0;
	}
}


function show_matching(&$matching)
{
$players = &$matching['players'];

usort($matching['assignments'], 'order_by_round_and_board');
?>
<table border="1">
<caption>Fitness : <?php h(sprintf('%.4f',$matching['fitness']))?></caption>
<tr>
<th>Table</th>
<th>Players</th>
<th>Fitness</th>
</tr>
<?php
foreach ($matching['assignments'] as $game) {
	?><tr>
<td><?php h("Table $game[round]-$game[board]")?></td>
<td><ul class="player_inline_list"><?php
	foreach ($game['players'] as $pid) {
		$p = $players[$pid];
		?><li><span class="player_name" data-player-id="<?php h($pid)?>"><?php h($p['name'])?></span></li>
<?php
	}
	?></ul></td>
<td><?php h($game['this_fitness'])?></td>
</tr>
<?php
}//end foreach table
?>
</table>
<?php
} //end show_matching()

} //endif action:generate_pairings

$matching = generate_optimal_matching($games, $players, $weights);
show_matching($matching);

end_page();
