<div id="chart_div" style="width:400; height:300"></div>

<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript"><!--
var chartData = [
<?php

$sql = "SELECT session_num, prior_rating, post_rating
	FROM player_rating
	WHERE id=".db_quote($person_id)."
	ORDER BY session_num";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));

$count = 0;
while ($row = mysqli_fetch_row($query)) {
	$session_num = $row[0];
	$prior_rating = $row[1];
	$post_rating = $row[2];
	if (0 == $count++) {
		echo "\t".json_encode(array("".($session_num-1), +$prior_rating));
	}
	echo ",\n";
	echo "\t".json_encode(array("$session_num", +$post_rating));
} //end foreach session
?>
	];

google.load('visualization', '1.0', {'packages':['corechart']});
function drawChart() {
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Session');
	data.addColumn('number', 'Rating');
	data.addRows(chartData);
	var options = {
		'width':400,
		'height':300
		};
	var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
	chart.draw(data, options);
}
google.setOnLoadCallback(drawChart);
//--></script>


