<?php

if (strtoupper(substr(PHP_OS,0,3)) == 'WIN') {
	//Windows format specifiers
	define('LONG_DATE_FORMAT', '%#x');
	define('DATETIME_FMT', '%#I:%M%p %b %#d');
	define('TIME_FMT', '%#I:%M%p');
	define('TIME_12H_FMT', '%#I:%M');
} else {
	//Unix/Linux format specifiers
	define('LONG_DATE_FORMAT', '%A, %h %e, %Y');
	define('DATETIME_FMT', '%l:%M%P %h %e');
	define('TIME_FMT', '%l:%M%P');
	define('TIME_12H_FMT', '%l:%M');
}

function format_time_s($datetime_str)
{
	if (!$datetime_str) { return NULL; }

	$parts = array_pad(explode('T', $datetime_str),2,NULL);
	$date_str = $parts[0];
	$time_str = $parts[1];

	$time = strtotime("$date_str $time_str");
	$cur_time = time();

	if (abs($time-$cur_time) < 2*3600) {
		return strftime(TIME_12H_FMT, $time);
	}
	else if (strftime('%Y-%m-%d',$time) == strftime('%Y-%m-%d',$cur_time)) {
		return strftime(TIME_FMT, $time);
	}
	else {
		return strftime(DATETIME_FMT, $time);
	}
}

function stringify_time_interval($num_secs)
{
	if (is_null($num_secs)) {
		return '';
	}
	else if ($num_secs > 0 && $num_secs % 60 == 0) {
		return ($num_secs/60)."mn";
	}
	else {
		return $num_secs;
	}
}

function parse_time_interval($str)
{
	if ($str == '') {
		return NULL;
	}
	elseif (preg_match('/^(\d+)\s*(mn|min|minute|minutes)$/i', $str, $m)) {
		return 60*$m[1];
	}
	else {
		return 0+$str;
	}
}
