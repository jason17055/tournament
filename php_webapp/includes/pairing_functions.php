<?php

class Webtd_Seat
{
}
class Webtd_Contest
{
}

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
	}

	$weights = array();

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
			'nplays' => $nplays,
			'same_home' => $same_home,
			'same_family' => $same_family
			);
	}

	$games = array();
	$sql = "SELECT id,round,board,status
		FROM contest c
		WHERE tournament=".db_quote($tournament_id)."
		AND session_num=".db_quote($current_session);
	$query = mysqli_query($database, $sql);
	while ($row = mysqli_fetch_row($query))
	{
		$contest_id = $row[0];
		$round = $row[1];
		$board = $row[2];
		$game_status = $row[3];

		$game = new Webtd_Contest;
		$game->id = $contest_id;
		$game->round = $round;
		$game->board = $board;
		$game->status = $game_status;
		$game->locked = ($game_status != 'proposed');

		$seats = array();
		$sql = "SELECT id,player
			FROM contest_participant
			WHERE contest=".db_quote($contest_id)."
			ORDER BY turn_order,id";
		$query2 = mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		while ($row2 = mysqli_fetch_row($query2)) {
			$s = new Webtd_Seat;
			$s->id = $row2[0];
			$s->player = $row2[1];
			$seats[] = $s;
		}
		$game->seats = $seats;

		$games[] = $game;
	}

	$m = array(
		'players' => &$players,
		'history' => &$weights,
		'assignments' => &$games
		);
	$m['fitness'] = sum_fitness($m);
	return $m;
}

function initialize_matching(&$original_matching)
{
	$games = $original_matching['assignments'];
	$players = &$original_matching['players'];

	// generate a list of all rounds that have unlocked games
	$seen_round = array();
	foreach ($games as $g) {
		if ($g->locked) { continue; }
		$seen_round[$g->round] = $g->round;
	}

	foreach ($seen_round as $round_no)
	{
		// empty any seats not at a locked table
		$empty_seat_count = 0;
		for ($i = 0; $i < count($games); $i++) {
			if ($games[$i]->round != $round_no) { continue; }
			if ($games[$i]->locked) { continue; }

			// copy the game struct so our changes do
			// not modify the original
			$g = clone $games[$i];
			$g->dirty = TRUE;
			$size = count($g->seats);
			for ($j = 0; $j < $size; $j++) {
				$g->seats[$j] = clone $g->seats[$j];
				$g->seats[$j]->dirty = TRUE;
				$g->seats[$j]->player = NULL;
				$empty_seat_count++;
			}
			$games[$i] = $g;
		}

		// generate a set available players
		$avail = array();
		foreach ($players as $k=>&$v) {
			if ($v['ready']) {
				$avail[$k] = $k;
			}
		}

		// filter out players assigned to locked games this round
		foreach ($games as $g) {
			if ($g->round != $round_no) { continue; }
			if ($g->locked) {
				foreach ($g->seats as $seat) {
					if ($seat->player) {
						unset($avail[$seat->player]);
					}
				}
			}
		}

		// make a randomly-sorted list of players
		$players_list = array_keys($avail);
		while (count($players_list) < $empty_seat_count) {
			// add null to players list so they get
			// randomly distributed
			$players_list[] = NULL;
		}
		shuffle($players_list);

		// assign players to empty seats
		foreach ($games as $g) {
			if ($g->round != $round_no) { continue; }
			if ($g->locked) { continue; }

			foreach ($g->seats as $seat) {
				if (!$seat->player && count($players_list)) {
					$next_pid = array_shift($players_list);
					$seat->player = $next_pid;
				}
			}
		}
	}

	$m = array(
		'players' => &$original_matching['players'],
		'history' => &$original_matching['history'],
		'assignments' => $games
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
	foreach ($tables as $game) {
		$sum_game_sizes += count($game->seats);
	}
	$avg_game_size = $sum_game_sizes / count($tables);
	$game_size_variation = 0;
	foreach ($tables as $game) {
		$this_game_size = count($game->seats);
		$game_size_variation += pow($this_game_size-$avg_game_size,2)/count($tables);
	}
	if ($game_size_variation != 0.0) {
		$penalties['game:size'] = 50*exp(3*$game_size_variation);
	}

	$player_game_sizes = array();
	foreach ($tables as $game) {
		$this_game_size = count($game->seats);

		$seat_no = 0;
		foreach ($game->seats as $seat) {
			if (!$seat->player) { continue; }
			$pid = $seat->player;
			$player_game_counts[$pid]++;
			$player_game_sizes[$pid] = ($player_game_sizes[$pid] ?: 0) + $this_game_size;

			$add_hit = function($tag) use (&$hits_by_player,$pid) {
				$hits_by_player[$pid][$tag] = ($hits_by_player[$pid][$tag] ?: 0) + 1;
				};
			$add_hit('game:'.$this_game_size.'player');
			$seat_no++;
			$add_hit('seat:'.$seat_no);

			foreach ($game->seats as $opp_seat) {
				if (!$opp_seat->player) { continue; }
				$opp = $opp_seat->player;
				if ($pid < $opp) {
					$k = "$pid,$opp";

					if (isset($last_seen[$k])) {
						$elapsed = $game->round - $last_seen[$k];
						$p = $elapsed < 2 ? 150 : 0;
						$penalties['consecutives'] += $p;
					}
					$last_seen[$k] = $game->round;

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

function mutate_matching_by_swapping($parent_matching)
{
	$assignments = $parent_matching['assignments'];

	// pick someone to move
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		// don't remove from a "locked" table
		if ($assignments[$i]->locked) { continue; }

		$f = 1;
		$R[] = array(
			'v' => $i,
			'f' => $f
			);
	}

	$a_table_idx = roulette($R);
	if (is_null($a_table_idx)) { return NULL; }

	$a_table = $assignments[$a_table_idx];
	$a_seat = rand(1, count($a_table->seats)) - 1;
	$a_round = $a_table->round;

echo "<div>Picked seat #$a_seat from table $a_table_idx (round $a_round)</div>\n";

	// pick someone to swap with; must be same round
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		// don't remove from a "locked" table
		if ($assignments[$i]->locked) { continue; }

		// must be same round
		if ($assignments[$i]->round != $a_round) { continue; }

		$f = 1;
		$R[] = array(
			'v' => $i,
			'f' => $f
			);
	}
		
	$b_table_idx = roulette($R);
	if (is_null($b_table_idx)) {
die("died here");
		return NULL;
	}

	$b_table = $assignments[$b_table_idx];
	if ($b_table_idx == $a_table_idx) {
		// pick a different seat than first player
		$n = count($b_table->seats)-1;
		if ($n > 0) {
			$b_seat = rand(1, $n) - 1;
			if ($b_seat >= $a_seat) { $b_seat++; }
			echo "<div>Other is seat #$b_seat at same table.</div>\n";
		}
		else {
			// unable to do a matching
			die("picked a table with only one seat");
			return NULL;
		}
	}
	else {
		$b_seat = rand(1, count($b_table->seats)) - 1;
		echo "<div>Other is seat #$b_seat from table $b_table_idx</div>\n";
	}

	// make the swap
	$vacated_seat = $a_table->seats[$a_seat];

	$a_table = clone $a_table;
	$a_table->dirty = TRUE;
	$a_table->seats[$a_seat] = clone $vacated_seat;
	$a_table->seats[$a_seat]->dirty = TRUE;
	$a_table->seats[$a_seat]->player = $b_table->seats[$b_seat]->player;
	$assignments[$a_table_idx] = $a_table;

	$b_table = clone $assignments[$b_table_idx];
	$b_table->dirty = TRUE;
	$b_table->seats[$b_seat] = clone $b_table->seats[$b_seat];
	$b_table->seats[$b_seat]->dirty = TRUE;
	$b_table->seats[$b_seat]->player = $vacated_seat->player;
	$assignments[$b_table_idx] = $b_table;

	$m = array(
		'players' => &$parent_matching['players'],
		'history' => &$parent_matching['history'],
		'assignments' => $assignments
		);
	$m['fitness'] = sum_fitness($m);

	return $m;
}

function mutate_matching(&$parent_matching)
{
//	$new_matching = mutate_matching_by_moving($parent_matching);
//	if ($new_matching) {
//		return $new_matching;
//	}

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

		// determine number of players here
		$pcount = 0;
		foreach ($assignments[$i]['seats'] as $seat) {
			if ($seat->player) {
				$pcount++;
			}
		}

		if ($pcount > $min_game_size) {
			$f = pow($pcount-$min_game_size,2);
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

	$a_table = $assignments[$rmtable_idx];
	$rmtable_round = $a_table['round'];

	$R = array();
	for ($i = 0; $i < count($a_table['seats']); $i++) {
		if ($a_table['seats'][$i]->player) {
			$R[] = array(
				'v' => $i,
				'f' => 1.0
				);
		}
	}
	$player_idx = roulette($R);
	if (is_null($player_idx)) {
		return NULL;
	}

	$a_seats = $a_table['seats'];
	$vacated_seat = $a_table['seats'][$player_idx];

	$a_seats[$player_idx] = clone $a_seats[$player_idx];
	$a_seats[$player_idx]->dirty = TRUE;
	$a_seats[$player_idx]->player = NULL;
	$a_table['players'] = $a_seats;
	$assignments[$rmtable_idx] = $a_table;

	// find a place to insert this player
	$R = array();
	for ($i = 0; $i < count($assignments); $i++) {
		// don't insert into a "locked" table
		if (isset($assignments[$i]['locked'])) { continue; }
		// don't re-insert in same table
		if ($i == $rmtable_idx) { continue; }
		// don't insert to table in a different round
		if ($assignments[$i]['round'] != $rmtable_round) { continue; }

		$pcount = 0;
		foreach ($assignments[$i]['seats'] as $seat) {
			if ($seat->player) {
				$pcount++;
			}
		}

		if ($pcount < $max_game_size) {
			$f = pow($max_game_size-$pcount,2);
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

	$b_table = $assignments[$intable_idx];
	$b_seats = $b_table['seats'];

	// insert at random place in turn order
	$R = array();
	for ($i = 0; $i < count($b_seats); $i++) {
		if (is_null($b_seats[$i]->player)) {
			$R[] = array(
				'v' => $i,
				'f' => 1.0
				);
		}
	}

	$player_idx = roulette($R);
	if (is_null($player_idx)) {
		return NULL;
	}

	$b_seats[$player_idx] = clone $b_seats[$player_idx];
	$b_seats[$player_idx]->dirty = TRUE;
	$b_seats[$player_idx]->player = $vacated_seat->player;
	$b_table['players'] = $b_seats;
	$assignments[$intable_idx] = $b_table;

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
		?><div class="driller_container">
		<h2 class="driller_heading">Original Matching</h2>
		<div class="driller_content"><?php
		show_matching($original_matching);
		?></div></div>

		<?php
	$INITIAL_GEN_SIZE = 15;
	$POOL_SIZE = 20;
	$GENERATIONS = 50;

	$sum_fitness = 0;
	$pool = array();
	$pool[] = $original_matching;
	$sum_fitness += $original_matching['fitness'];

	for ($i = 1; $i < $INITIAL_GEN_SIZE; $i++) {
		$m = initialize_matching($original_matching);
		$sum_fitness += $m['fitness'];
		$pool[] = $m;
	}

	for ($i = 0; $i < $GENERATIONS; $i++) {
		?><div class="driller_container" style="display:none">
		<h2 class="driller_heading">Mutation <?php echo($i+1)?></h2>
		<div class="driller_content"><?php
		// first, pick a random solution from pool
		$r = rand(1, count($pool))-1;

		// clone it with mutations
		$m = mutate_matching($pool[$r]);
		?></div></div><?php

		if (!$m) { continue; }
		if (count($pool) < $POOL_SIZE) {
			// just add it to the pool
			$pool[] = $m;
			continue;
		}

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
	if ($a->round != $b->round) {
		return $a->round > $b->round ? 1 : -1;
	}
	else if ($a->board != $b->board) {
		return $a->board > $b->board ? 1 : -1;
	}
	else {
		return 0;
	}
}

function save_matching($matching)
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

	foreach ($assignments as $game) {
		// skip over the "locked" tables
		if ($game->locked) { continue; }

		$contest_id = $game->id;

		//TODO - update contest properties?

		// update seat assignments
		foreach ($game->seats as $seat) {
			if ($seat->dirty) {
				$sql = "UPDATE contest_participant
					SET player=".db_quote($seat->player)."
					WHERE id=".db_quote($seat->id);
				mysqli_query($database, $sql)
					or die("SQL error: ".db_error($database));
			}
		}
	}
}
