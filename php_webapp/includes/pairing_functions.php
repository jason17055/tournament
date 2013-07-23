<?php

function check_common_surname($a_name, $b_name)
{
	$a_names = explode(' ', $a_name);
	$a_surname = array_pop($a_names);

	$b_names = explode(' ', $b_name);
	$b_surname = array_pop($b_names);

	return strtolower($a_surname) == strtolower($b_surname);
}

function load_matching($tournament_id, $current_session)
{
	global $database;

$players = array();

$sql = "SELECT id,name,status,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($current_session)."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 2=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count2p,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($current_session)."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 3=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count3p,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($current_session)."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 4=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count4p,
	(SELECT COUNT(*) FROM contest c
		WHERE tournament=p.tournament
		AND session_num<".db_quote($current_session)."
		AND status='completed'
		AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
		AND 5<=(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id)
		) AS count5p
	FROM person p
	WHERE tournament=".db_quote($tournament_id);
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
		'status' => $row[2],
		'count2p' => $row[3],
		'count3p' => $row[4],
		'count4p' => $row[5],
		'count5p' => $row[6]
		);
	$p['ready'] = ($row[2] == 'ready');
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
		AND c.session_num<".db_quote($current_session)."
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
	AND session_num=".db_quote($current_session)."
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
		'players' => $m_players,
		'locked' => TRUE
		);
	$games[] = $game;
}

	$m = array(
		'players' => &$players,
		'history' => &$weights,
		'assignments' => &$assignments
		);
	$m['fitness'] = sum_fitness($m);
	return $m;
}

function generate_random_matching(&$games, &$players, &$weights)
{
	$players_list = array();
	foreach ($players as $k=>&$v) {
		if ($v['ready']) {
			$players_list[] = $k;
		}
	}

	$nplayers = count($players_list);
	$ntables = ceil($nplayers/$_REQUEST['max_game_size']);

	// copy the existing games in
	$assignments = $games;
	for ($round = $_REQUEST['first_round']; $round <= $_REQUEST['last_round']; $round++) {

		$tables = array();
		for ($i = 0; $i < $ntables; $i++) {
			$tables[] = array(
				'board' => ($i+1),
				'round' => $round,
				'players' => array()
				);
		}

		shuffle($players_list);
		for ($i = 0; $i < $nplayers; $i++) {
			$tableno = ($i % $ntables);
			$tables[$tableno]['players'][] = $players_list[$i];
		}

		foreach ($tables as $tab) {
			$assignments[] = $tab;
		}
	}

	$m = array(
		'players' => &$players,
		'history' => &$weights,
		'assignments' => &$assignments
		);
	$m['fitness'] = sum_fitness($m);
	return $m;
}

function sum_fitness(&$matching)
{
	$history = $matching['history'];

	$penalties = array(
		'consecutives' => 0,
		'repeats' => 0
		);

	// this keeps track of how many times particular pairings play
	// each other
	$encounters = array();
	// keep track of how many rounds a player can play in
	$player_game_counts = array();
	// keep track of various other things per player
	$hits_by_player = array();

	foreach ($matching['players'] as $pid => $dummy1)
	{
		$player_game_counts[$pid] = 0;
		$hits_by_player[$pid] = array();

		foreach ($matching['players'] as $opp => $dummy2)
		{
			if ($pid < $opp) {
				$pair_key = "$pid,$opp";
				$h = $history[$pair_key];
				$prior_plays = $h['nplays'] / 5;
				if ($h['same_family']) {
					$prior_plays += 1.5;
				} else if ($h['same_home']) {
					$prior_plays += 0.65;
				}
				$encounters[$pair_key] = $prior_plays;
			}
		}
	}

	// this keeps track of what round number a pair last played each
	// other
	$last_seen = array();

	$tables = $matching['assignments'];
	usort($tables, 'order_by_round_and_board');

	$sum_game_sizes = 0;
	foreach ($tables as &$game) {
		$sum_game_sizes += count($game['players']);
	}
	$avg_game_size = $sum_game_sizes / count($tables);
	$game_size_variation = 0;
	foreach ($tables as &$game) {
		$this_game_size = count($game['players']);
		$game_size_variation += pow($this_game_size-$avg_game_size,2)/count($tables);
	}
	if ($game_size_variation != 0.0) {
		$penalties['game:size'] = 50*exp(3*$game_size_variation);
	}

	$player_game_sizes = array();
	foreach ($tables as &$game) {
		$this_game_size = count($game['players']);

		$seat_no = 0;
		foreach ($game['players'] as $pid) {
			$player_game_counts[$pid]++;
			$player_game_sizes[$pid] = ($player_game_sizes[$pid] ?: 0) + count($game['players']);

			$add_hit = function($tag) use (&$hits_by_player,$pid) {
				$hits_by_player[$pid][$tag] = ($hits_by_player[$pid][$tag] ?: 0) + 1;
				};
			$add_hit('game:'.$this_game_size.'player');
			$seat_no++;
			$add_hit('seat:'.$seat_no);

			foreach ($game['players'] as $opp) {
				if ($pid < $opp) {
					$k = "$pid,$opp";

					if (isset($last_seen[$k])) {
						$elapsed = $game['round'] - $last_seen[$k];
						$p = $elapsed < 2 ? 150 : 0;
						$penalties['consecutives'] += $p;
					}
					$last_seen[$k] = $game['round'];

					$encounters[$k] = ($encounters[$k] ?: 0) + 1;
				}
			}
		}
	}

	// determine average and variance for each type of hit
	$nplayers = count($matching['players']);
	$hit_average = array();
	foreach ($hits_by_player as $pid => $dummy) {
		foreach ($hits_by_player[$pid] as $tag => $count) {
			$hit_average[$tag] = ($hit_average[$tag] ?: 0) + $count/$nplayers;
		}
	}
	$hit_variance = array();
	foreach ($hits_by_player as $pid => $dummy) {
		foreach ($hits_by_player[$pid] as $tag => $count) {
			$hit_variance[$tag] = ($hit_variance[$tag] ?: 0)
				+ pow($count-$hit_average[$tag],2)/$nplayers;
		}
	}
	echo 'Hit variations:<ul>';
	foreach ($hit_variance as $tag => $amt) {
		if ($amt >= 0.1) {
			$penalties[$tag] = 30 * exp(3*$amt);
		}
		?><li>Variation of <?php h($tag)?> : <?php h(sprintf('%.3f',$amt))?></li>
		<?php
	}
	echo '</ul>';

	// determine average number of encounters
	$sum_encounters = 0;
	$count_encounters = 0;
	foreach ($encounters as $pair_key => $nplays) {
		$sum_encounters += $nplays;
		$count_encounters += 1;
	}
	$avg_encounters = $sum_encounters / $count_encounters;

	// add penalties for each pairing where the encounters is not
	// near the average

	foreach ($encounters as $pair_key => $nplays) {
		$deviation = abs($nplays - $avg_encounters);
		$p = pow($deviation, 2) * 100;
		echo "$pair_key - played $nplays ($avg_encounters)<br>\n";
		$penalties['repeats'] += $p;
	}

	$total_penalty = 0;
	foreach ($penalties as $pen_key => $pen_val) {
		$total_penalty += $pen_val;
	}
	$matching['penalties'] = &$penalties;

echo "got penalty of $total_penalty<br>\n";
	return 1000/(1+$total_penalty/1000);
}

function mutate_matching_by_swapping(&$parent_matching)
{
	$assignments = $parent_matching['assignments'];

	// pick someone to move
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		// don't remove from a "locked" table
		if (isset($assignments[$i]['locked'])) { continue; }

		$f = 1;
		$R[] = array(
			'v' => $i,
			'f' => $f
			);
	}

	$a_table_idx = roulette($R);
	$a_table = $assignments[$a_table_idx];
	$a_seat = rand(1, count($a_table['players'])) - 1;
	$a_round = $a_table['round'];

	// pick someone to swap with; must be same round
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		// don't remove from a "locked" table
		if (isset($assignments[$i]['locked'])) { continue; }

		// must be same round
		if ($assignments[$i]['round'] != $a_round) { continue; }

		$f = 1;
		$R[] = array(
			'v' => $i,
			'f' => $f
			);
	}
		
	$b_table_idx = roulette($R);
	$b_table = $assignments[$b_table_idx];
	if ($b_table_idx == $a_table_idx) {
		// pick a different seat than first player
		$n = count($b_table['players'])-1;
		if ($n > 0) {
			$b_seat = rand(1, $n) - 1;
			if ($b_seat >= $a_seat) { $b_seat++; }
		}
		else {
			// unable to do a matching
			return NULL;
		}
	}
	else {
		$b_seat = rand(1, count($b_table['players'])) - 1;
	}

	// make the swap
	$removed_player = $a_table['players'][$a_seat];

	$a_players = $a_table['players'];
	$a_players[$a_seat] = $b_table['players'][$b_seat];
	$a_table['players'] = $a_players;
	$assignments[$a_table_idx] = $a_table;

	$b_players = $b_table['players'];
	$b_players[$b_seat] = $removed_player;
	$b_table['players'] = $b_players;
	$assignments[$b_table_idx] = $b_table;

	$m = array(
		'players' => &$parent_matching['players'],
		'history' => &$parent_matching['weights'],
		'assignments' => &$assignments
		);
	$m['fitness'] = sum_fitness($m);
	return $m;
}

function mutate_matching(&$parent_matching)
{
	$new_matching = mutate_matching_by_moving($parent_matching);
	if ($new_matching) {
		return $new_matching;
	}

	$new_matching = mutate_matching_by_swapping($parent_matching);
	return $new_matching;
}

function mutate_matching_by_moving(&$parent_matching)
{
	$assignments = $parent_matching['assignments'];
	$min_game_size = $_REQUEST['min_game_size'];
	$max_game_size = $_REQUEST['max_game_size'];

	// pick someone to move
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		// don't remove from a "locked" table
		if (isset($assignments[$i]['locked'])) { continue; }

		$table_size = count($assignments[$i]['players']);
		if ($table_size > $min_game_size) {
			$f = pow($table_size-$min_game_size,2);
			$R[] = array(
				'v' => $i,
				'f' => $f
				);
		}
	}

	$rmtable_idx = roulette($R);
	if (is_null($rmtable_idx)) {
		return NULL; //unsuccessful mutation
	}

	$table = $assignments[$rmtable_idx];
	$rmtable_round = $table['round'];

	$player_idx = rand(1, count($table['players'])) - 1;
	$new_players = $table['players'];
	$removed_player = $table['players'][$player_idx];

	array_splice($new_players, $player_idx, 1);
	$table['players'] = $new_players;
	$assignments[$rmtable_idx] = $table;

	// find a place to insert this player
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		// don't insert into a "locked" table
		if (isset($assignments[$i]['locked'])) { continue; }
		// don't re-insert in same table
		if ($i == $rmtable_idx) { continue; }
		// don't insert to table in a different round
		if ($assignments[$i]['round'] != $rmtable_round) { continue; }

		$table_size = count($assignments[$i]['players']);
		if ($table_size < $max_game_size) {
			$f = pow($max_game_size-$table_size,2);
			$R[] = array(
				'v' => $i,
				'f' => $f
				);
		}
	}

	$intable_idx = roulette($R);
	if (is_null($intable_idx)) {
		return NULL; //unsuccessful mutation
	}

	$table = $assignments[$intable_idx];

	// insert at random place in turn order
	$player_idx = rand(0, count($table['players']));
	$new_players = $table['players'];

	array_splice($new_players, $player_idx, 0, $removed_player);
	$table['players'] = $new_players;
	$assignments[$intable_idx] = $table;

	$m = array(
		'players' => &$parent_matching['players'],
		'history' => &$parent_matching['weights'],
		'assignments' => &$assignments
		);
	$m['fitness'] = sum_fitness($m);
	return $m;
}

function roulette(&$R)
{
	$len = count($R);
	if ($len == 0) {
		return NULL;
	}

	$sum = 0;
	for ($i = 0; $i < $len; $i++) {
		$sum += $R[$i]['f'];
	}
	$r = $sum*rand()/(getrandmax()+1);

	$i = 0;
	while ($i + 1 < $len && $r >= $R[$i]['f']) {
		$r -= $R[$i]['f'];
		$i++;
	}

	return $R[$i]['v'];
}

function optimize_matching(&$original_matching)
{
	$games = $original_matching['assignments'];
	$players = $original_matching['players'];
	$weights = $original_matching['history'];

	$POOL_SIZE = 15;
	$GENERATIONS = 40;

	$sum_fitness = 0;
	$pool = array();
	for ($i = 0; $i < $POOL_SIZE; $i++) {
		?><div class="driller_container" style="display:none">
		<h2 class="driller_heading">Random Matching <?php echo($i+1)?></h2>
		<div class="driller_content"><?php
		$m = generate_random_matching($games, $players, $weights);
		$sum_fitness += $m['fitness'];
		$pool[] = $m;
		show_matching($m);
		?></div></div>
		<?php
	}

	for ($i = 0; $i < $GENERATIONS; $i++) {
		?><div class="driller_container" style="display:none">
		<h2 class="driller_heading">Mutation <?php echo($i+1)?></h2>
		<div class="driller_content"><?php
		// first, pick a random solution from pool
		$r = rand(1, $POOL_SIZE)-1;

		// clone it with mutations
		$m = mutate_matching($pool[$r]);
		?></div></div><?php

		if (!$m) { continue; }

		// substitute the new entry for the weakest in pool
		$worst_i = -1;
		$worst_f = $m['fitness'];
		for ($j = 0; $j < $POOL_SIZE; $j++) {
			if ($pool[$j]['fitness'] < $worst_f) {
				$worst_i = $j;
				$worst_f = $pool[$j]['fitness'];
			}
		}
		if ($worst_i != -1) {
			$pool[$worst_i] = $m;
		}
	}

	//find the best
	$best_i = 0;
	$best_f = $pool[0]['fitness'];
	for ($i = 1; $i < $POOL_SIZE; $i++) {
		if ($pool[$i]['fitness'] > $best_f) {
			$best_i = $i;
			$best_f = $pool[$i]['fitness'];
		}
	}
	return $pool[$best_i];
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

function propose_matching(&$matching)
{
	$assignments = $matching['assignments'];

	global $database;
	global $tournament_id;

	$sql = "SELECT current_session FROM tournament
		WHERE id=".db_quote($tournament_id);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query);
	$tournament_info = array(
		'id' => $tournament_id,
		'current_session' => $row[0]
		);

	$contests_sql = "
		tournament=".db_quote($tournament_id)."
		AND status='proposed'
		AND session_num=".db_quote($tournament_info['current_session']);

	$sql = "DELETE FROM contest_participant
		WHERE contest IN (SELECT id FROM contest WHERE $contests_sql)";
	mysqli_query($database, $sql)
		or die("SQL error:".db_error($sql));

	$sql = "DELETE FROM contest
		WHERE $contests_sql";
	mysqli_query($database, $sql)
		or die("SQL error:".db_error($sql));

	$gcount = 0;
	foreach ($assignments as $game) {
		// skip over the "locked" tables
		if ($game['locked']) { continue; }

		$gcount++;
		$sql = "INSERT INTO contest (tournament,session_num,round,board,status) VALUES (
			".db_quote($tournament_id).",
			".db_quote($tournament_info['current_session']).",
			".db_quote($game['round']).",
			".db_quote($game['board']).",
			'proposed')";
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));

		$contest_id = mysqli_insert_id($database);

		$pcount=0;
		foreach ($game['players'] as $pid) {
			$pcount++;
			$sql = "INSERT INTO contest_participant (
					contest,player,turn_order)
				VALUES (
				".db_quote($contest_id).",
				".db_quote($pid).",
				".db_quote($pcount).")";
			mysqli_query($database, $sql)
				or die("SQL error: ".db_error($database));
		}
	}
	die("got here! $gcount");
}

