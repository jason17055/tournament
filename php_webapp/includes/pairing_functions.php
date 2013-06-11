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
	$ntables = ceil($nplayers/4.0);

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

	$encounters = array();

	$total_fitness = 0;
	foreach ($matching['assignments'] as &$game) {

		$sum_fitness = 0;
		$count = 0;

	foreach ($game['players'] as $pid) {
		foreach ($game['players'] as $opp) {
			if ($pid < $opp) {
				$count++;
				$k = "$pid,$opp";
				$h = $history[$k];
				$encounters[$k] = ($encounters[$k] ?: 0) + 1;
				$f = 1/($encounters[$k]+$h['nplays']/5);
				if ($h['same_family']) {
					$f *= 0.6;
				} else if ($h['same_home']) {
					$f *= 0.9;
				}
				$sum_fitness += $f;
			}
		}
	}
		$avg_fitness = $count ? $sum_fitness / $count : 0;
		$game['this_fitness'] = $avg_fitness;
		$total_fitness += $avg_fitness;
	}

	return $total_fitness;
}

function mutate_matching(&$parent_matching)
{
	$assignments = $parent_matching['assignments'];

	// pick someone to move
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		$table_size = count($assignments[$i]['players']);
		$f = pow($table_size,2);
		$R[] = array(
			'v' => $i,
			'f' => $f
			);
	}

	$rmtable_idx = roulette($R);
	$table = $assignments[$rmtable_idx];

	$player_idx = rand(1, count($table['players'])) - 1;
	$new_players = $table['players'];
	$removed_player = $table['players'][$player_idx];

	array_splice($new_players, $player_idx, 1);
	$table['players'] = $new_players;
	$assignments[$rmtable_idx] = $table;

	// find a place to insert this player
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		if ($i == $rmtable_idx) { continue; }
		$table_size = count($assignments[$i]['players']);
		$f = 1.0/pow($table_size,2);
		$R[] = array(
			'v' => $i,
			'f' => $f
			);
	}

	$intable_idx = roulette($R);
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
	$POOL_SIZE = 10;

	$sum_fitness = 0;
	$pool = array();
	for ($i = 0; $i < $POOL_SIZE; $i++) {
		$m = generate_random_matching($games, $players, $weights);
		$sum_fitness += $m['fitness'];
		$pool[] = $m;
	}

	for ($i = 0; $i < 20; $i++) {
		// first, pick a random solution from pool
		$r = rand(1, $POOL_SIZE)-1;

		// clone it with mutations
		$m = mutate_matching($pool[$i]);

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
