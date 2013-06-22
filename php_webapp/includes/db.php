<?php

$database = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME)
	or die("Could not connect: " . mysqli_connect_error());

function db_quote($str)
{
	global $database;
	if (is_null($str) || $str === '') {
		return "NULL";
	}
	else {
		return "'" . mysqli_real_escape_string($database,$str) . "'";
	}
}

function db_error($obj)
{
	global $database;

	return mysqli_error($database);
}

function parse_date_time($date_str, $time_str)
{
	if (strlen($time_str) == 0) {
		return $date_str;
	}
	else {
		return $date_str . 'T' . $time_str;
	}
}

function split_datetime($datetime_str, &$date_str, &$time_str)
{
	$parts = array_pad(explode('T', $datetime_str),2,NULL);
	$date_str = $parts[0];
	$time_str = $parts[1];
}
