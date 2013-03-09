<?php
header("Content-Type: text/json");
$offset = 3600 * 48; //48 hours
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT");
header("Cache-Control: max-age=$offset");

$url = 'http://usgo.org/ratings/RatingsQuery.php?TextOpt=TDListA&Unrated=yes';

$by_prefix = array();
function remember_prefix($prefix,$number)
{
	global $by_prefix;

	if (!$by_prefix[$prefix]) {
		$by_prefix[$prefix] = array();
	}
	$by_prefix[$prefix][] = $number;
}
$fp = fopen($url, 'r')
	or die("fopen() error");
echo "{\n";
$count = 0;
while (!feof($fp))
{
	if ($count++) { echo ",\n"; }

	$line = rtrim(fgets($fp));
	$parts = explode("\t", $line);
	$name = array_shift($parts);
	$number = array_shift($parts);

	echo json_encode($number) . ":";
	echo "[" . json_encode($name);
	foreach ($parts as $p)
	{
		echo "," . json_encode($p);
	}
	echo "]";

	$name_p = strtolower(substr(preg_replace('/[^a-zA-Z]/','',$name),0,2));
	$firstname_p = strtolower(substr(preg_replace('/[^a-zA-Z]/','',preg_replace('/^.*, +/','',$name)),0,2));
	$initials = substr($firstname_p,0,1).substr($name_p,0,1);

	remember_prefix($name_p,$number);
	if (strlen($firstname_p)==2 && $firstname_p != $name_p)
	{
		remember_prefix($firstname_p,$number);
	}
	if (strlen($initials)==2 && $initials != $name_p && $initials != $firstname_p)
	{
		remember_prefix($initials,$number);
	}
}
fclose($fp);

foreach ($by_prefix as $prefix=>$x)
{
	echo ",\n" . json_encode($prefix) . ":" . json_encode($x);
}
echo "\n}\n";
