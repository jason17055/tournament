<?php

function do_all_scores($tournament_id)
{
	global $database;

	$sql = "SELECT id
		FROM contest
		WHERE tournament=".db_quote($tournament_id)."
		AND status='completed'";
	$c_query = mysqli_query($database, $sql)
		or die("SQL error 406: ".db_error($database));

	while ($c_row = mysqli_fetch_row($c_query)) {
		$contest_id = $c_row[0];

		$sql = "SELECT
				AVG(IFNULL(score,0)) AS average_score,
				COUNT(*) AS num_players,
				SUM(CASE WHEN placement=1 THEN 1 ELSE 0 END) AS num_winners
			FROM contest_participant
			WHERE contest=".db_quote($contest_id);
		$query = mysqli_query($database, $sql)
			or die("SQL error 407: ".db_error($database));
		$row = mysqli_fetch_row($query);
		$average_score = $row[0];
		$num_players = $row[1];
		$num_winners = $row[2];

		$average_score = max($average_score, 10);
		$weight = $num_players / 2.0;
		$win_val = (1 + $num_players - $num_winners) / 2;

		// sum of "actual" performance calculations
		$cp_performances = array();
		// sum of "expected" performance calculations
		$cp_exp_perf = array();

		$sql = "SELECT a.id,a.score,a.placement,b.score,b.placement,
			ar.prior_rating, br.prior_rating
			FROM contest_participant a
			CROSS JOIN contest_participant b
				ON b.contest = a.contest
				AND NOT (b.player = a.player)
			JOIN contest c ON c.id=a.contest
			LEFT JOIN player_rating ar
				ON ar.id=a.player AND ar.session_num=c.session_num
			LEFT JOIN player_rating br
				ON br.id=b.player AND br.session_num=c.session_num
			WHERE c.id = ".db_quote($contest_id);
		$query = mysqli_query($database, $sql)
			or die("SQL error 408: ".db_error($database));
		while ($row = mysqli_fetch_row($query)) {
			$cp_id = $row[0];
			$a_score = $row[1];
			$a_place = $row[2];
			$b_score = $row[3];
			$b_place = $row[4];
			$a_rating = $row[5] ?: 0;
			$b_rating = $row[6] ?: 0;

			$a_effscore = adj_score($a_score, $a_place, $average_score);
			$b_effscore = adj_score($b_score, $b_place, $average_score);

			$perf = 1.0 / (1.0 + exp($b_effscore - $a_effscore));
			$cp_performances[$cp_id] = ($cp_performances[$cp_id] ?: 0) + $perf;

			$exp_perf = 1.0 / (1.0 + pow(10, ($b_rating - $a_rating) / 400));
			$cp_exp_perf[$cp_id] = ($cp_exp_perf[$cp_id] ?: 0) + $exp_perf;
		}

		foreach ($cp_performances as $cp_id => $perf)
		{
			$avg_perf = $perf / ($num_players-1);
			$avg_eperf = $cp_exp_perf[$cp_id] / ($num_players-1);

			$sql = "UPDATE contest_participant
				SET performance=".db_quote($avg_perf).",
				expected_performance=".db_quote($avg_eperf).",
				w_points=CASE WHEN placement=1 THEN ".db_quote($win_val)." ELSE 0 END
				WHERE id=".db_quote($cp_id);
			mysqli_query($database, $sql)
				or die("SQL error: ".db_error($database));
		} //end foreach contest participant
	} //end foreach contest
}

function do_ratings($tournament_id)
{
	global $database;

	do_all_scores($tournament_id);

	// get a ratings batch number
	$sql = "INSERT INTO rating_batch (tournament,target_session,created) VALUES (
		".db_quote($tournament_id).",
		".db_quote($_REQUEST['target_session']).",
		NOW())";
	mysqli_query($database, $sql)
		or die("SQL error 401: ".db_error($database));
	$batch_num = mysqli_insert_id($database);

	// each player has a "prior" rating (rating_cycle=0),
	// fixed at 100 (or whatever "initial rating" user declared)
	$sql = "INSERT INTO rating_identity (batch,player,rating_cycle,rating)
		SELECT ".db_quote($batch_num).",
			p.id,
			".db_quote($multi_session_ratings ? 0 : 1).",
			".db_quote($_REQUEST['initial_rating']?:0)."
			FROM person p
			WHERE p.id IN (
				SELECT player FROM contest_participant cp
				JOIN contest c ON c.id=cp.contest
				WHERE c.tournament=".db_quote($tournament_id)."
				AND c.session_num>=".db_quote($_REQUEST['first_session']?:0)."
				AND c.session_num<=".db_quote($_REQUEST['target_session']?:1)."
				AND c.status='completed')";
	mysqli_query($database, $sql)
		or die("SQL error 402: ".db_error($database));

	// and each player has a "post" rating for EACH session
	// that they participated in...
	// the "post" ratings are determined by this algorithm

	if ($multi_session_ratings) {
	$sql = "INSERT INTO rating_identity (batch,player,rating_cycle,rating)
		SELECT DISTINCT ".db_quote($batch_num).",
			p.id,
			c.session_num,
			".db_quote($_REQUEST['initial_rating']?:0)."
			FROM person p
			CROSS JOIN contest c ON c.tournament=p.tournament
			AND c.id IN (
				SELECT contest FROM contest_participant
				WHERE player=p.id
				)
			WHERE p.tournament=".db_quote($tournament_id);
	mysqli_query($database, $sql)
		or die("SQL error 402: ".db_error($database));

	// generate dummy games to connect each player's rating identity
	// to that player's rating identity of the previous session

	$sql = "SELECT person_id,post_rating_id,
			pre_rating_id,
			(SELECT rating_cycle FROM rating_identity WHERE id=pre_rating_id) AS pre_rating_cycle
		FROM (
		SELECT p.id AS person_id,
		a.id AS post_rating_id,
		(SELECT b.id FROM rating_identity b
			WHERE b.player=p.id
			AND b.rating_cycle<a.rating_cycle
			AND b.batch=a.batch
			ORDER BY b.rating_cycle DESC LIMIT 1) AS pre_rating_id
		FROM person p
		JOIN rating_identity a
			ON a.player=p.id
			AND a.batch=".db_quote($batch_num)."
		WHERE p.tournament=".db_quote($tournament_id)."
		AND a.rating_cycle<>0) t1";

	$query = mysqli_query($database, $sql);
	while ($row = mysqli_fetch_row($query)) {
		
		$post_tourn_id = $row[1];
		$pre_tourn_id = $row[2];
		$pre_rating_cycle = $row[3];

		$weight = $pre_rating_cycle == 0 ?
				$_REQUEST['initial_weight'] :
				$_REQUEST['inter_session_weight'];
		$perf = 0.53; //and winning a 53% rate.

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
	} //end if multi_session_ratings

	// now record the actual game data

	$sql = "SELECT id,session_num FROM contest
		WHERE tournament=".db_quote($tournament_id)."
		AND status='completed'
		AND session_num IS NOT NULL
		AND session_num >= ".db_quote($_REQUEST['first_session'] ?: 1)."
		AND session_num <= ".db_quote($_REQUEST['target_session']);
	$c_query = mysqli_query($database, $sql)
		or die("SQL error 406: ".db_error($database));

	while ($c_row = mysqli_fetch_row($c_query)) {
		$contest_id = $c_row[0];
		$session_num = $multi_session_ratings ? $c_row[1] : 1;

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
		$weight2 = $weight / ($num_players-1);

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
				".db_quote($weight2)."
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

	$strongest_rating = -INF;
	$strongest_player = null;
	$weakest_rating = INF;
	$weakest_player = null;
	$sum_errors = 0;
	$count_errors = 0;
	while ($row = mysqli_fetch_row($query))
	{
		$a_pid = $row[0];
		$a_rating = $row[1];
		$b_pid = $row[2];
		$b_rating = $row[3];
		$act_perf = $row[4];
		$weight = $row[5];

		if ($a_rating < $weakest_rating) {
			$weakest_rating = $a_rating;
			$weakest_player = $a_pid;
		}
		if ($a_rating > $strongest_rating) {
			$strongest_rating = $a_rating;
			$strongest_player = $a_pid;
		}

		$exp_perf = 1.0 / (1.0 + pow(10, ($b_rating-$a_rating)/400));

		$adj = ($act_perf - $exp_perf) * $weight;

		if (!isset($adjustments[$a_pid])) {
			$adjustments[$a_pid] = 0;
		}
		$adjustments[$a_pid] += $adj;

		$sum_errors += pow($adj, 2);
		$count_errors++;
	}

	$iteration_count = isset($_REQUEST['iterations']) ? +$_REQUEST['iterations'] : 0;
	$iteration_count++;

	$max_abs_adj = 0;
	$strongest_adj = 0;
	$weakest_adj = 0;
	foreach ($adjustments as $pid => $adj) {
		if (abs($adj) > $max_abs_adj) {
			$max_abs_adj = abs($adj);
		}
		if ($pid == $strongest_player) {
			$strongest_adj = $adj;
		}
		if ($pid == $weakest_player) {
			$weakest_adj = $adj;
		}
	}

	# start with a K value of 8, gradually decay
	$k = 8/(1+exp(($iteration_count-80)/30.0));

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
<p>Iterations: <?php h($iteration_count)?></p>
<p>Current error: <?php h(sprintf('%.1f',$sum_errors/$count_errors))?></p>
<p>Current k value: <?php h(sprintf('%.3f',$k))?></p>
<p>Current max adj: <?php h(sprintf('%.3f',$max_abs_adj))?></p>
<form method="post" name="form1" action="<?php h($_SERVER['REQUEST_URI'])?>">
<input type="hidden" name="batch" value="<?php h($batch_num)?>">
<input type="hidden" name="iterations" value="<?php h($iteration_count)?>">
<button type="submit">Another Pass</button>
<button type="submit" name="action:commit_ratings">Save Ratings</button>
<button type="submit" name="action:cancel">Cancel</button>
</form>

<table border="1">
<tr>
<th>Session</th>
<th>Player</th>
<th>Rating</th>
</tr>
<?php

	$sql = "SELECT r.id,p.name,r.rating_cycle,r.rating
		FROM rating_identity r
		JOIN person p
			ON p.id=r.player
		WHERE batch=".db_quote($batch_num)."
		AND rating_cycle<>0
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
<?php
	if ($iteration_count<200) {
		?>
<script type="text/javascript"><!--
setTimeout(function() {
	document.form1.submit();
	}, 100);
//--></script>
<?php
	}
?>
</body>
</html>
<?php
}

function do_ratings_commit($batch_num)
{
	global $database;

	$sql = "SELECT tournament,target_session FROM rating_batch
		WHERE id=".db_quote($batch_num);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query)
		or die("Invalid batch number");
	$tournament_id = $row[0];
	$target_session = $row[1];

	$sql = "DELETE FROM player_rating
		WHERE id IN (
		SELECT id FROM person WHERE tournament=".db_quote($tournament_id)."
		)
		AND session_num =".db_quote($target_session);
	mysqli_query($database, $sql)
		or die("SQL error 43: ".db_error($database));

	$sql = "INSERT INTO player_rating (id,session_num,post_rating,prior_rating)
		SELECT r.player,".db_quote($target_session).",
			ri.rating AS post_rating,
			(SELECT b.rating FROM rating_identity b
				WHERE b.batch=ri.batch
				AND b.player=ri.player
				AND b.rating_cycle<ri.rating_cycle
				ORDER BY b.rating_cycle DESC
				LIMIT 1
				) AS prior_rating
		FROM (SELECT player, MAX(rating_cycle) AS rating_cycle
			FROM rating_identity
			WHERE batch=".db_quote($batch_num)."
			AND rating_cycle<>0
			GROUP BY player) r
		JOIN rating_identity ri
			ON ri.player=r.player
			AND ri.rating_cycle=r.rating_cycle
			AND ri.batch=".db_quote($batch_num)."
		";
	mysqli_query($database, $sql)
		or die("SQL error 44: ".db_error($database));

	$url = "tournament_dashboard.php?tournament=".urlencode($tournament_id);
	header("Location: $url");
	exit();
}
