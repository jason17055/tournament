<?php

$database = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME)
	or die("Could not connect: " . mysqli_connect_error());

function db_quote($str)
{
	global $database;
	if (is_null($str)) {
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
