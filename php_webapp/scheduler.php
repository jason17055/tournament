<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/format.php');

$tournament_id = $_GET['tournament'];
$sql = "SELECT name,schedule_granularity
	FROM tournament t
	WHERE id=".db_quote($tournament_id);
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
$row = mysqli_fetch_row($query)
	or die("Not Found: tournament $tournament_id");

$tournament_info = array(
	'id' => $tournament_id,
	'name' => $row[0],
	'schedule_granularity' => $row[1]
	);

if (!is_director($tournament_id)) {
	die("Not authorized.");
}

$page_title = "$tournament_info[name] - Scheduler";
begin_page($page_title);

?>
<table class="scheduler_table">
<tr>
<th class="time_hdr">Time</th>
<?php

$venues_in_order = array();
$sql = "SELECT id,venue_name FROM venue
	WHERE tournament=".db_quote($tournament_id)."
	AND venue_status='enabled'
	ORDER BY venue_name";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {

	$venue_id = $row[0];
	$venue_name = $row[1];

	$venues_in_order[] = $venue_id;
	?>
<th class="venue_hdr" data-venue-id="<?php h($venue_id)?>"><?php h($venue_name)?></th>
<?php
}
?>
</tr>

<?php
$by_venue = array();
$granularity = $tournament_info['schedule_granularity'] ?: 3600;
$cur_row_time = time();
$cur_row_time -= $cur_row_time % $granularity;
$row_count = 0;

function output_current_scheduler_row()
{
	global $row_count;
	global $by_venue;
	global $cur_row_time;
	global $venues_in_order;
	global $tournament_id;

	$row_count++;
	$tr_class = $row_count % 2 == 0 ? 'even' : 'odd';
	?>
<tr class="<?php h($tr_class)?>">
<td><?php h(strftime('%l:%M',$cur_row_time))?></td>
<?php
	foreach ($venues_in_order as $venue_id) {

		?><td class="scheduler_cell" data-venue-id="<?php h($venue_id)?>">
		<?php
		$a = $by_venue[$venue_id];
		if (count($a) == 0) {
			$DUMMY_GAME = array(
				'url' => 'contest.php?tournament='.urlencode($tournament_id).'&starts='.urlencode(make_datetime($cur_row_time)).'&venue='.urlencode($venue_id)
				);
			$a = array($DUMMY_GAME);
		}
		foreach ($a as $d) {
			?><div>
			<a href="<?php h($d['url'])?>"><img src="images/edit.gif" width="18" height="18" alt="Edit" border="0"></a>
				<?php h($d['id'])?>
			</div>
			<?php
		}
		?>
		</td>
		<?php
	}
	?>
</tr>
<?php
	$by_venue = array();
}

$sql = "SELECT c.id,c.status,venue,starts
	FROM contest c
	WHERE c.tournament=".db_quote($tournament_id)."
	AND starts IS NOT NULL
	AND starts >= ".db_quote(make_datetime($cur_row_time))."
	ORDER BY starts";
$query = mysqli_query($database, $sql)
	or die("SQL error: ".db_error($database));
while ($row = mysqli_fetch_row($query)) {

	$d = array(
	'id' => $row[0],
	'status' => $row[1],
	'venue' => $row[2],
	'starts' => $row[3]
	);
	$d['url'] = 'contest.php?id='.urlencode($d['id']);

	if ($d['starts'] >= make_datetime($cur_row_time+$granularity)) {
		output_current_scheduler_row();
		$cur_row_time += $granularity;
		if ($row_count >= 10) {
			break;
		}
	}

	$by_venue[$d['venue']][] = $d;
}
while ($row_count < 10) {
	output_current_scheduler_row();
	$cur_row_time += $granularity;
}

?>
</table>
<?php

end_page();
