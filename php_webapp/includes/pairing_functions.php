<?php

function check_common_surname($a_name, $b_name)
{
	$a_names = explode(' ', $a_name);
	$a_surname = array_pop($a_names);

	$b_names = explode(' ', $b_name);
	$b_surname = array_pop($b_names);

	return strtolower($a_surname) == strtolower($b_surname);
}

function generate_random_matching(&$games, &$players, &$weights)
{
	$players_list = array();
	foreach ($players as $k=>$v) {
		$players_list[] = $k;
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

function mutate_matching(&$parent_matching)
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

function generate_optimal_matching(&$games, &$players, &$weights)
{
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
