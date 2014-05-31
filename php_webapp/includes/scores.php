<?php

function update_all_player_scores()
{
	global $database;
	global $tournament_id;

	$sql = "
		UPDATE contest_participant cp JOIN contest c ON c.id=cp.contest
		SET cp.w_points=
			--
			CASE
			-- sole winner
			WHEN c.status='completed' AND cp.placement=1 AND c.id IN (
				-- identify all single-winner games
				SELECT * FROM (
				SELECT id FROM contest cc
				WHERE (SELECT COUNT(*) FROM contest_participant
					WHERE contest=cc.id
					AND placement=1) = 1
				AND cc.tournament=".db_quote($tournament_id)."
				) tmp1 -- work-around MySQL limitation
				)
				THEN 1.0
			-- tied with another player
			WHEN c.status='completed' AND cp.placement=1
				THEN 0.5
			-- game not finished or non-winner
			ELSE 0.0
			END
		WHERE c.tournament=".db_quote($tournament_id)."
		";
	mysqli_query($database, $sql)
		or die("SQL error (in update_scores): ".db_error($database));
				
	$sql = "
		INSERT INTO score (player,score_method,score)
		SELECT id,'raw_score',
			IFNULL((SELECT SUM(w_points)
				FROM contest_participant cp
				JOIN contest c ON c.id=cp.contest
				WHERE cp.player=p.id
				AND (cp.participant_status IS NULL OR cp.participant_status NOT IN ('M'))
				AND c.status='completed'
				), 0)
		FROM person p
		WHERE p.tournament=".db_quote($tournament_id)."
		AND p.member_of IS NULL
		AND p.status IS NOT NULL
		ON DUPLiCATE KEY UPDATE score=VALUES(score)
		";
	mysqli_query($database, $sql)
		or die("SQL error (in update_scores): ".db_error($database));
}
