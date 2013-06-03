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
