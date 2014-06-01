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

$by_venue = array();
$granularity = $tournament_info['schedule_granularity'] ?: 3600;
if (isset($_REQUEST['start'])) {
	split_datetime($_REQUEST['start'], $_REQUEST['start_date'], $_REQUEST['start_time']);
	$cur_row_time = strtotime("$_REQUEST[start_date] $_REQUEST[start_time]");
}
else {
	$cur_row_time = time();
	$cur_row_time -= $cur_row_time % $granularity;
}

$num_rows = isset($_REQUEST['rows']) ? $_REQUEST['rows'] : 16;

$ONE_DAY = 86400;
$url_com = 'tournament='.urlencode($tournament_id).
	(isset($_REQUEST['rows']) ? '&rows='.urlencode($_REQUEST['rows']) : '');
$previous_day_url = 'scheduler.php?'.$url_com
	.'&start='.urlencode(make_datetime($cur_row_time-$ONE_DAY));
$earlier_url = 'scheduler.php?'.$url_com
	.'&start='.urlencode(make_datetime($cur_row_time-$num_rows*$granularity));
$next_day_url = 'scheduler.php?'.$url_com
	.'&start='.urlencode(make_datetime($cur_row_time+$ONE_DAY));
$later_url = 'scheduler.php?'.$url_com
	.'&start='.urlencode(make_datetime($cur_row_time+$num_rows*$granularity));


?>
<table class="scheduler_table">
<caption>
	<div style="float:left">
	<a href="<?php h($previous_day_url)?>">Previous Day</a>
	|
	<a href="<?php h($earlier_url)?>">Earlier</a>
	</div>
	<div style="float:right">
	<a href="<?php h($later_url)?>">Later</a>
	|
	<a href="<?php h($next_day_url)?>">Next Day</a>
	</div>
	<span class="scheduler_day"><?php h(strftime(LONG_DATE_FORMAT, $cur_row_time))?></span>
</caption>
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
$row_count = 0;

function output_contest_info($d)
{
	$round = $d['round'];
	if (preg_match('/^\d+$/', $round)) {
		$round = "R$round";
	}

	?><div>
	<?php
	contest_status_icon($d['status']);
	if ($round) {
	?>
	<span class="round"><?php h($round)?></span>: 
	<?php }?>
	<span class="participants"><?php
	h($d['participant_ordinals']);
	?></span>
	<a href="<?php h($d['url'])?>"><img src="images/edit.gif" width="18" height="18" alt="Edit" border="0"></a>
	</div>
<?php
}

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
<td class="time_col"><?php h(strftime(TIME_FMT,$cur_row_time))?></td>
<?php
	foreach ($venues_in_order as $venue_id) {

		?><td class="scheduler_cell" data-venue-id="<?php h($venue_id)?>">
		<?php
		if (isset($by_venue[$venue_id])) {
			$a = $by_venue[$venue_id];
		}
		else {
			$DUMMY_GAME = array(
				'url' => 'contest.php?tournament='.urlencode($tournament_id).'&starts='.urlencode(make_datetime($cur_row_time)).'&venue='.urlencode($venue_id)
				. '&next_url='.urlencode($_SERVER['REQUEST_URI']),
				'round' =>  '',
				'status' => '',
				'participant_ordinals' => ''
				);
			$a = array($DUMMY_GAME);
		}
		foreach ($a as $d) {
			output_contest_info($d);
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

$sql = "SELECT c.id,c.status,venue,starts,round,
	(SELECT GROUP_CONCAT(ordinal SEPARATOR 'v') FROM contest_participant cp
			JOIN person p ON p.id=cp.player
			WHERE cp.contest=c.id) AS participant_ordinals
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
	'starts' => $row[3],
	'round' => $row[4],
	'participant_ordinals' => $row[5]
	);
	$d['url'] = 'contest.php?id='.urlencode($d['id'])
		. '&next_url='.urlencode($_SERVER['REQUEST_URI']);

	while ($d['starts'] >= make_datetime($cur_row_time+$granularity)) {
		output_current_scheduler_row();
		$cur_row_time += $granularity;
		if ($row_count >= $num_rows) {
			break;
		}
	}

	$by_venue[$d['venue']][] = $d;
}
while ($row_count < $num_rows) {
	output_current_scheduler_row();
	$cur_row_time += $granularity;
}

?>
</table>
<?php

$dashboard_url = 'tournament_dashboard.php?tournament='.urlencode($tournament_id);
?>
<p>
<a href="<?php h($dashboard_url)?>">Dashboard</a>
</p>

<?php

end_page();
