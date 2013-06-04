<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');
require_once('includes/auth.php');

$my_url = APP_URL . '/login.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	die("not implemented");
}

if ($_GET['ticket'])
{
	$validate_url = CAS_VALIDATE_URL . '?service=' . urlencode($my_url) . '&ticket=' . urlencode($_GET['ticket']);
	$c = explode("\n", file_get_contents($validate_url));

	if (chop($c[0]) == 'yes') {
		$_SESSION['username'] = chop($c[1]);
		add_login_attributes();
		header("Location: $_SESSION[post_login_url]");
		exit();
	}
	die("Invalid ticket");
}

if ($_SESSION['username']) {
	die("Already logged in");
}

$cas_url = CAS_LOGIN_URL . '?service=' . urlencode($my_url);
$_SESSION['post_login_url'] = $_REQUEST['next_url'];

header("Location: $cas_url");
exit();