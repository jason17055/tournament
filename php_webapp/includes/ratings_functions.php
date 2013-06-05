<?php

function do_ratings($tournament_id)
{
	global $database;

	// get a ratings batch number
	$sql = "INSERT INTO rating_batch (created) VALUES (NOW())";
	mysqli_query($database, $sql)
		or die("SQL error 401: ".db_error($database));
	$batch_num = mysqli_insert_id($database);

	// each player has n+1 ratings...
	//   (n is number of ratings cycles)
	// 0th rating is the pre-tournament rating (fixed at 0)
	// ith rating is rating after cycle #i (determined by this algorithm)

	for ($i = 0; $i <= 2; $i++)
	{
	$sql = "INSERT INTO rating_identity (batch,player,rating_cycle,rating)
		SELECT ".db_quote($batch_num).",
			p.id,
			".db_quote($i).",
			0
			FROM person p
			WHERE tournament=".db_quote($tournament_id);
	mysqli_query($database, $sql)
		or die("SQL error 402: ".db_error($database));
	}

	// generate dummy games to connect each player's rating identity
	// to the previous cycle's rating identity of that player

	$sql = "SELECT p.id,
		a.id,
		b.id
		FROM person p
		JOIN rating_identity a
			ON a.player=p.id
			AND a.batch=".db_quote($batch_num)."
		JOIN rating_identity b
			ON b.player=p.id
			AND b.rating_cycle=a.rating_cycle+1
			AND b.batch=".db_quote($batch_num)."
		WHERE p.tournament=".db_quote($tournament_id);

	$query = mysqli_query($database, $sql);
	while ($row = mysqli_fetch_row($query)) {
		
		$pre_tourn_id = $row[1];
		$post_tourn_id = $row[2];

		$weight = 9; // equivalent to nine one-vs-one games
		$perf = 5.0/9.0; //winning 5 of them.

		$sql = "INSERT INTO rating_data (batch,player_a,player_b,actual_performance,weight)
			VALUES (
			".db_quote($batch_num).",
			".db_quote($pre_tourn_id).",
			".db_quote($post_tourn_id).",
			".db_quote(1.0-$perf).",
			".db_quote($weight)."
			)";
		mysqli_query($database, $sql)
			or die("SQL error 404: ".db_error($database));

		$sql = "INSERT INTO rating_data (batch,player_a,player_b,actual_performance,weight)
			VALUES (
			".db_quote($batch_num).",
			".db_quote($post_tourn_id).",
			".db_quote($pre_tourn_id).",
			".db_quote($perf).",
			".db_quote($weight)."
			)";
		mysqli_query($database, $sql)
			or die("SQL error 405: ".db_error($database));
	}

	// now record the actual game data

	$sql = "SELECT id,session_num FROM contest
		WHERE tournament=".db_quote($tournament_id)."
		AND status='completed'
		AND session_num IS NOT NULL
		AND session_num >= 1";
	$c_query = mysqli_query($database, $sql)
		or die("SQL error 406: ".db_error($database));

	while ($c_row = mysqli_fetch_row($c_query)) {
		$contest_id = $c_row[0];
		$session_num = $c_row[1];

		$sql = "SELECT AVG(score),COUNT(*)
			FROM contest_participant
			WHERE score IS NOT NULL
			AND contest=".db_quote($contest_id);
		$query = mysqli_query($database, $sql)
			or die("SQL error 407: ".db_error($database));
		$row = mysqli_fetch_row($query);
		$average_score = $row[0];
		$num_players = $row[1];

		$average_score = max($average_score, 10);
		$weight = $num_players / 2.0;

		$sql = "SELECT a.player,a.score,a.placement,b.player,b.score,b.placement
			FROM contest_participant a
			CROSS JOIN contest_participant b
				ON b.contest = a.contest
				AND b.player <> a.player
			WHERE a.contest = ".db_quote($contest_id);
		$query = mysqli_query($database, $sql)
			or die("SQL error 408: ".db_error($database));
		while ($row = mysqli_fetch_row($query)) {
			$a_pid = $row[0];
			$a_score = $row[1];
			$a_place = $row[2];
			$b_pid = $row[3];
			$b_score = $row[4];
			$b_place = $row[5];

			$a_effscore = adj_score($a_score, $a_place, $average_score);
			$b_effscore = adj_score($b_score, $b_place, $average_score);

			$perf = 1.0 / (1.0 + exp($b_effscore - $a_effscore));

			$sql = "INSERT INTO rating_data (batch,player_a,player_b,actual_performance,weight)
				SELECT
				".db_quote($batch_num).",
				(SELECT id FROM rating_identity
					WHERE batch=".db_quote($batch_num)."
					AND player=".db_quote($a_pid)."
					AND rating_cycle=".db_quote($session_num)."),
				(SELECT id FROM rating_identity
					WHERE batch=".db_quote($batch_num)."
					AND player=".db_quote($b_pid)."
					AND rating_cycle=".db_quote($session_num)."),
				".db_quote($perf).",
				".db_quote($weight)."
				FROM dual";
			mysqli_query($database, $sql)
				or die("SQL error 409: ".db_error($database));
		}
	}

	do_ratings_pass($batch_num);
}

function adj_score($real_score, $place, $avg_score)
{
	return $real_score * 8.0 / $avg_score + ($place == 1 ? 1.5 : 0.0);
}

function do_ratings_pass($batch_num)
{
	global $database;

	$sql = "
	SELECT a.id,a.rating,
		b.id,b.rating,
		actual_performance,
		weight
	FROM rating_data d
	JOIN rating_identity a
		ON a.id=d.player_a
	JOIN rating_identity b
		ON b.id=d.player_b
	WHERE d.batch=".db_quote($batch_num)."
	ORDER BY player_a,player_b
	";

	$query = mysqli_query($database, $sql)
		or die("SQL error 455: ".db_error($database));

	$adjustments = array();

	$sum_errors = 0;
	while ($row = mysqli_fetch_row($query))
	{
		$a_pid = $row[0];
		$a_rating = $row[1];
		$b_pid = $row[2];
		$b_rating = $row[3];
		$act_perf = $row[4];
		$weight = $row[5];

		$exp_perf = 1.0 / (1.0 + pow(10, ($b_rating-$a_rating)/400));

		$adj = ($act_perf - $exp_perf) * $weight;

		if (!isset($adjustments[$a_pid])) {
			$adjustments[$a_pid] = 0;
		}
		$adjustments[$a_pid] += $adj;

		$sum_errors += pow($adj, 2);
	}

	$max_abs_adj = 0;
	foreach ($adjustments as $pid => $adj) {
		if (abs($adj) > $max_abs_adj) {
			$max_abs_adj = abs($adj);
		}
	}
	$k = max(1, 20/$max_abs_adj);

	foreach ($adjustments as $pid => $adj) {
		$sql = "UPDATE rating_identity
			SET rating=rating+".db_quote($k*$adj)."
			WHERE id=".db_quote($pid)."
			AND rating_cycle <> 0";
		mysqli_query($database, $sql)
			or die("SQL error 457: ".db_error($database));
	}

	?><!DOCTYPE HTML>
<html>
<body>
<p>Batch number: <?php h($batch_num)?></p>
<p>Current error: <?php h($sum_errors)?></p>
<form method="post" action="<?php h($_SERVER['REQUEST_URI'])?>">
<input type="hidden" name="batch" value="<?php h($batch_num)?>">
<button type="submit" name="action:run_ratings">Another Pass</button>
<button type="submit" name="action:cancel">Cancel</button>
</form>

<table border="1">
<tr>
<th>Week</th>
<th>Player</th>
<th>Rating</th>
</tr>
<?php

	$sql = "SELECT r.id,p.name,r.rating_cycle,r.rating
		FROM rating_identity r
		JOIN person p
			ON p.id=r.player
		WHERE batch=".db_quote($batch_num)."
		ORDER BY r.rating_cycle DESC, r.rating DESC, p.name";
	$query = mysqli_query($database, $sql);
	while ($row = mysqli_fetch_row($query))
	{
?><tr>
<td align="center"><?php h($row[2])?></td>
<td align="left"><?php h($row[1])?></td>
<td align="right"><?php h(round($row[3]))?></td>
</tr>
<?php
	} //end foreach rating
	?>
</table>
</body>
</html>
<?php
}
