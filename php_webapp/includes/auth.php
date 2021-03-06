<?php

session_start();

if (isset($_SESSION['username']) && !$_SERVER['REMOTE_USER']) {
	$_SERVER['REMOTE_USER'] = $_SESSION['username'];
}

if ($_SERVER['REMOTE_USER'] && !isset($_SESSION['username']))
{
	$_SESSION['username'] = $_SERVER['REMOTE_USER'];
	add_login_attributes();
}

function require_auth()
{
	if ($_SERVER['REMOTE_USER']) {
		return true;
	}

	$url = $_SERVER['PHP_SELF'];
	if ($_SERVER['QUERY_STRING']) {
		$url .= '?' . $_SERVER['QUERY_STRING'];
	}

	$login_url = "login.php?next_url=".urlencode($url);
	header("Location: $login_url");
	exit();
}

function add_login_attributes()
{
	global $database;

	$sql = "SELECT enabled,is_sysadmin
		FROM account
		WHERE username=".db_quote($_SESSION['username']);
	$query = mysqli_query($database, $sql);
	$row = mysqli_fetch_row($query);

	if ($row) {
		if ($row[0] != 'Y') {
			die("Account is disabled");
		}
		if ($row[1] == 'Y') {
			$_SESSION['sysadmin'] = 1;
		}
		$sql = "UPDATE account
			SET last_login=NOW()
			WHERE username=".db_quote($_SESSION['username']);
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
	}
	else {
		$sql = "INSERT INTO account (username,created,last_login)
			VALUES (".db_quote($_SESSION['username']).",
			NOW(),NOW()
			)";
		mysqli_query($database, $sql)
			or die("SQL error: ".db_error($database));
	}
}

function is_sysadmin()
{
	return isset($_SESSION['sysadmin']) && !!$_SESSION['sysadmin'];
}

function is_director($tournament_id)
{
	global $database;

	$sql = "SELECT is_director,is_sysadmin
		FROM account a
		LEFT JOIN tournament_role r
			ON r.account=a.username
			AND r.tournament=".db_quote($tournament_id)."
		WHERE a.username=".db_quote($_SESSION['username']);
	$query = mysqli_query($database, $sql)
		or die("SQL error: ".db_error($database));
	$row = mysqli_fetch_row($query);

	return $row && ($row[0]=='Y' || $row[1] == 'Y');
}
