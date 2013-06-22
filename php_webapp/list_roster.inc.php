<script type="text/javascript"><!--
players_src = [
  <?php
	$sql = "SELECT id,name
		FROM person
		WHERE tournament=".db_quote($tournament_id)."
		AND status IS NOT NULL
		AND (id=".db_quote($_REQUEST['player'])."
			OR id NOT IN (SELECT player FROM contest_participant WHERE contest=".db_quote($contest_id)." AND player IS NOT NULL)
		    )
		ORDER BY name";
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$count = 0;
	while ($row = mysqli_fetch_row($query)) {
		if ($count++) { echo ','; }
		?>
	{ "value": <?php echo json_encode($row[0])?>,
	"label": <?php echo json_encode($row[1])?>}
<?php
	}
	?> ];
//-->
</script>
