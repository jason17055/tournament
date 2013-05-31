<?php

function h($str)
{
	echo htmlspecialchars($str);
}

function begin_page($page_title)
{
?><!DOCTYPE HTML>
<html>
<head>
<title><?php h($page_title)?></title>
</head>
<body>
<h1><?php h($page_title)?></h1>
<?php
}

function end_page()
{
?>
</body>
</html>
<?php
}
