<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/format.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,location,start_time,
	scoreboard_roundrobin_style
	FROM tournament WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
$row = mysqli_fetch_row($query);
if (!$row) {
	header("HTTP/1.0 404 Not Found");
	exit();
}
$tournament_info = array(
	'name' => $row[0],
	'location' => $row[1],
	'start_time' => $row[2],
	'scoreboard_roundrobin_style' => ($row[3]=='Y')
	);

header("Content-Type: text/json");

echo "{\n";
echo '"tournament":'.json_encode($tournament_info).",\n";

$sql = "SELECT p.id,
		p.name,
		p.entry_rank,
		p.member_number,
		p.ordinal,
		last_g.id AS last_contest,
		cur_g.id AS cur_contest,
		s1.score AS raw_score
	FROM person p
	LEFT JOIN contest last_g
		ON last_g.id=(SELECT id FROM contest c
			WHERE tournament=p.tournament
			AND status IN ('completed')
			AND EXISTS (SELECT 1 FROM contest_participant WHERE contest=c.id AND player=p.id)
			ORDER BY started DESC, id DESC
			LIMIT 1
			)
	LEFT JOIN contest cur_g
		ON cur_g.id=(SELECT id FROM contest c
			WHERE tournament=p.tournament
			AND status IN ('scheduled','assigned','started')
			AND p.id IN (SELECT player FROM contest_participant WHERE contest=c.id)
			ORDER BY started ASC, id ASC
			LIMIT 1
			)
	LEFT JOIN score s1 ON s1.player=p.id AND s1.score_method='raw_score'
	WHERE p.tournament=".db_quote($tournament_id)."
	AND p.status IS NOT NULL
	AND p.status NOT IN ('prereg')
	";

$scoreboard_order = "raw_score DESC,ordinal ASC";
$sql = "SELECT * FROM ($sql) tmp_s1 ORDER BY $scoreboard_order";

$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

echo '"players":[';
$count = 0;
while ($row = mysqli_fetch_row($query)) {
	if ($count++) { echo ",\n"; }
	$p = array(
		'pid' => $row[0],
		'name' => $row[1],
		'entryRank' => $row[2],
		'member_number' => $row[3],
		'ordinal' => $row[4]
		);
	$last_contest_id = $row[5];
	$cur_contest_id = $row[6];

	if ($last_contest_id) {
		$sql = "SELECT s.placement,
			(SELECT GROUP_CONCAT(player)
				FROM contest_participant
				WHERE contest=s.contest
				AND NOT (player=s.player)
				) AS opponents,
			(SELECT COUNT(*)
				FROM contest_participant
				WHERE contest=s.contest
				AND NOT (player=s.player)
				AND placement=s.placement) AS tie_count,
			c.round
			FROM contest c
			JOIN contest_participant s
				ON s.contest=c.id
			WHERE c.id=".db_quote($last_contest_id)."
			AND s.player=".db_quote($p['pid']);
		$q2 = mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$r2 = mysqli_fetch_row($q2);

		$placement = $r2[0];
		$opponents = $r2[1];
		$tie_count = $r2[2];
		$round = $r2[3];

		$p['lastGame'] = array(
			'result' => ($placement==1 && $tie_count!=0 ? 'TIE' :
					($placement==1 ? 'WIN' : 'LOSS')
					),
			'round' => $round,
			'opponents' => $opponents
			);
	}

	if ($cur_contest_id) {
		$sql = "SELECT v.venue_name,
			(SELECT GROUP_CONCAT(player)
				FROM contest_participant
				WHERE contest=s.contest
				AND NOT (player=s.player)
				) AS opponents,
			c.starts,
			c.round,
			c.status
			FROM contest c
			JOIN contest_participant s
				ON s.contest=c.id
			LEFT JOIN venue v
				ON v.id=c.venue
			WHERE c.id=".db_quote($cur_contest_id)."
			AND s.player=".db_quote($p['pid']);
		$q2 = mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
		$r2 = mysqli_fetch_row($q2);

		$venue_name = $r2[0];
		$opponents = $r2[1];
		$starts = $r2[2];
		$round = $r2[3];
		$c_status = $r2[4];

		$p['curGame'] = array(
			'venue' => $venue_name,
			'round' => $round,
			'status' => $c_status,
			'opponents' => $opponents,
			'startTime' => format_time_s($starts)
			);
	}

	echo json_encode($p);
}
echo "],\n";
echo '"games":[';

$sql = "SELECT c.id,c.status,c.scenario,
	(SELECT COUNT(*) FROM contest_participant WHERE contest=c.id
		AND placement=1) AS nwinners,
	c.round
	FROM contest c
	JOIN tournament t ON t.id=c.tournament
	WHERE t.id=".db_quote($tournament_id)."
	AND c.status IN ('completed','started')
	AND (c.session_num IS NULL OR c.session_num=t.current_session)
	ORDER BY c.round,c.starts,c.id";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

$count = 0;
while ($row=mysqli_fetch_row($query)) {
	$contest_id = $row[0];
	$game_status = $row[1];
	$scenario = $row[2];
	$nwinners = $row[3];
	$round = $row[4];
	if ($round && preg_match('/^\d+$/', $round)) {
		$round = "R$round";
	}

	$g = array();
	if ($game_status != 'completed') {
		$g['in_progress'] = true;
	}
	if ($scenario) {
		$g['scenario'] = $scenario;
	}
	if ($round) {
		$g['round'] = $round;
	}

	$sql = "SELECT a.player,a.placement,a.seat,a.participant_status
		FROM contest_participant a
		WHERE a.contest=".db_quote($contest_id);
	$query1 = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$seat_count=0;
	$all_seats = array();
	$mulligans = array();
	while ($row1 = mysqli_fetch_row($query1))
	{
		$pid = $row1[0];
		$placement = $row1[1];
		$seat = $row1[2];
		$participant_status = $row1[3];

		if ($participant_status=='M') {
			$mulligans[] = $pid;
		}

		$a_seat = $seat ?: (++$seat_count);
		$g['player.'.$a_seat] = $pid;
		$all_seats[] = $a_seat;

		if ($placement == 1) {
			$g['winner'] = $a_seat;
		}
	}

	if ($g['winner'] && $nwinners > 1) {
		$g['winner'] = 'TIE';
	}

	if (count($mulligans)) {
		$g['mulligan_for'] = implode(',', $mulligans);
	}

	$g['seats'] = implode(',', $all_seats);
	if ($count++) { echo ",\n"; }
	echo json_encode($g);
}
echo "]\n";
echo "}\n";
