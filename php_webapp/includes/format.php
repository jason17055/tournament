<?php

function format_time_s($datetime_str)
{
	$parts = array_pad(explode('T', $datetime_str),2,NULL);
	$date_str = $parts[0];
	$time_str = $parts[1];

	$time = strtotime("$date_str $time_str");
	$cur_time = time();

	if (abs($time-$cur_time) < 2*3600) {
		return strftime('%l:%M', $time);
	}
	else if (strftime('%Y-%m-%d',$time) == strftime('%Y-%m-%d',$cur_time)) {
		return strftime('%l:%M%P', $time);
	}
	else {
		return strftime('%l:%M%P %h %e', $time);
	}
}
