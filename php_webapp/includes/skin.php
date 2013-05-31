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

function select_widget($args)
{
	?><select name="<?php h($args['name'])?>">
<?php
	foreach ($args['options'] as $k => $v) {
		select_option($k, $v, $k == $args['value']);
	}
?>
</select><?php
}

function select_option($value, $name, $is_selected)
{
	?><option value="<?php h($value)?>"<?php
		echo($is_selected?' selected="selected"':'')
		?>><?php h($name)?></option>

<?php
}
