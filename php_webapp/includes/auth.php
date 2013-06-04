<?php

session_start();

function require_auth()
{
	if ($_SESSION['username']) {
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
