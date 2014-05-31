<?php

function update_all_player_scores()
{
	global $database;
	global $tournament_id;

	$sql = "
		INSERT INTO score (player,score_method,score)
		SELECT id,'raw_score',
			IFNULL((SELECT SUM(
				CASE WHEN EXISTS(
					SELECT 1 FROM contest_participant
					WHERE contest=cp.contest
					AND placement=1
					AND NOT (player=p.id)
					) THEN 0.5
				ELSE 1 END
				)
				FROM contest_participant cp
				JOIN contest c ON c.id=cp.contest
				WHERE cp.player=p.id
				AND cp.placement=1
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
		or die("SQL error(scores): ".db_error($database));
}
