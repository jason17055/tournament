<script type="text/javascript"><!--
game_definitions = {
<?php
	$sql = "SELECT id,name,seat_names
		FROM game_definition
		WHERE tournament=".db_quote($tournament_id)."
		ORDER BY id";
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$count = 0;
	while ($row = mysqli_fetch_row($query)) {
		if ($count++) { echo ','; }
		?>
<?php echo json_encode($row[0])?>: {
	"name": <?php echo json_encode($row[1])?>,
	"seat_names": <?php echo json_encode($row[2])?>
	}<?php
	}
	echo "};\n";
?>
//--></script>
