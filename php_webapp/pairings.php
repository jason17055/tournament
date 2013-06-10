<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

function check_common_surname($a_name, $b_name)
{
	$a_names = explode(' ', $a_name);
	$a_surname = array_pop($a_names);

	$b_names = explode(' ', $b_name);
	$b_surname = array_pop($b_names);

	return strtolower($a_surname) == strtolower($b_surname);
}

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
		name => $row[1],
		count2p => $row[2],
		count3p => $row[3],
		count4p => $row[4],
		count5p => $row[5]
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
<th>Played (Prior Sessions)</th>
<th>Played (This Session)</th>
<th>Fitness</th>
</tr>
<?php

$sql = "SELECT p.id, q.id,
	p.name, q.name,
	(SELECT COUNT(*) FROM contest c
		WHERE c.tournament=p.tournament
		AND c.session_num<".db_quote($tournament_info['current_session'])."
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND q.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		) AS common_plays_total,
	(SELECT COUNT(*) FROM contest c
		WHERE c.tournament=p.tournament
		AND c.session_num=".db_quote($tournament_info['current_session'])."
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND q.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		) AS common_plays_this_session
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
	$nplays = $row[4]+5*$row[5];
	$common_last_name = check_common_surname($p_name,$q_name);

	$fitness = 1.0/($nplays+1)
		* ($common_last_name ? 0.3 : 1);
	$weights["$row[0],$row[1]"] = $fitness;
	$weights["$row[1],$row[0]"] = $fitness;

	?><tr><td><?php h("$p_name vs $q_name")?></td>
<td><?php h($row[4])?></td>
<td><?php h($row[5])?></td>
<td><?php h(sprintf('%.4f', $fitness))?></td>
</tr>
<?php
}

?></table>
</div><!--/.driller_content-->
</div><!--/.driller_container-->
<?php

function generate_matching($vertices, $weights)
{
	$vertices_a = array();
	foreach ($vertices as $k=>$v) {
		$vertices_a[] = $k;
	}

	$n = count($vertices_a);

	if ($n <= 4) {
		// single table of four
		return array(
			$vertices_a[0] => 1,
			$vertices_a[1] => 1,
			$vertices_a[2] => 1,
			$vertices_a[3] => 1
			);
	}

	shuffle($vertices_a);

	$ntables = ceil($n/4.0);
	$assignments = array();
	for ($i = 0; $i < $n; $i++) {
		$assignments[$vertices_a[$i]] = ($i % $ntables) + 1;
	}
	return $assignments;
}

function assignments_fitness($assignments, $weights)
{
	$total_fitness = 0;
	foreach ($assignments as $pid => $table_number) {
		$sum_fitness = 0;
		$count = 0;
		foreach ($assignments as $opp => $opp_table) {
			if ($opp != $pid && $opp_table == $table_number) {
				$count++;
				$f = $weights["$pid,$opp"];
				$sum_fitness += $f;
			}
		}
		$avg_fitness = $count ? $sum_fitness / $count : 0;
		$total_fitness += $avg_fitness;
	}
	return $total_fitness;
}

$assignments = generate_matching($players, $weights);
$tables = array();
foreach ($assignments as $pid => $table) {
	if (!isset($tables[$table])) {
		$tables[$table] = array();
	}
	$tables[$table][] = $pid;
}

?>
<table border="1">
<caption>Fitness : <?php h(sprintf('%.4f',assignments_fitness($assignments, $weights)))?></caption>
<tr>
<th>Table</th>
<th>Players</th>
</tr>
<?php
foreach ($tables as $table_name => $table_assign) {
	?><tr>
<td><?php h("Table $table_name")?></td>
<td><ul class="player_inline_list"><?php
	foreach ($table_assign as $pid) {
		$p = $players[$pid];
		?><li><span class="player_name" data-player-id="<?php h($pid)?>"><?php h($p['name'])?></span></li>
<?php
	}
	?></ul></td>
</tr>
<?php
}//end foreach table
?>
</table>
<?php
} //endif action:generate_pairings

end_page();
