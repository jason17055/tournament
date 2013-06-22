<?php

require_once('auth.php');

function h($str)
{
	echo htmlspecialchars($str);
}

function begin_page($page_title)
{
	global $tournament_id;
?><!DOCTYPE HTML>
<html>
<head>
<title><?php h($page_title)?></title>
<link type="text/css" rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/ui-lightness/jquery-ui.min.css">
<link type="text/css" rel="stylesheet" href="webtd.css">
<script type="text/javascript"><!--
webtd_tournament_id = <?php echo(json_encode($tournament_id))?>;
//--></script>
<script type="text/javascript" src="http://code.jquery.com/jquery-2.0.1.min.js"></script>
<script type="text/javascript" src="http://code.jquery.com/ui/1.10.3/jquery-ui.min.js"></script>
<script type="text/javascript" src="webtd.js"></script>
</head>
<body>
<h1><?php h($page_title)?></h1>
<?php
if (isset($_SESSION['username'])) { ?>
<p>You are logged in as <b><?php h($_SESSION['username'])?></b>.
</p>
<?php } else { ?>
<p><a href="<?php h('login.php?next_url='.urlencode($_SERVER['REQUEST_URI']))?>">Login</a></p>
<?php } //end if not logged in
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

function format_scenario($raw_text)
{
	if (substr($raw_text,0,1)=='{') {
		$url = DOMINION_ROLLER_URL . '?display/'.urlencode($raw_text);
		$icon_url = DOMINION_ROLLER_URL . 'images/20px-Dominion_icon.png';
		?><a href="<?php h($url)?>"><img src="<?php h($icon_url)?>" class="dominion_link" alt="View Card Set"></a>
		<?php
	}
	else {
		h($raw_text);
	}
}
