<?php

$c = curl_init('https://api.amazon.com/user/profile');
curl_setopt($c, CURLOPT_HTTPHEADER, array('Authorization: bearer '.$_REQUEST['access_token']));
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

// make the request
$r = curl_exec($c);
curl_close($c);

// decode the response
$d = json_decode($r);
?>
Name: <?php echo $d->name?><br>
Email: <?php echo $d->email?><br>
User ID: <?php echo $d->user_id?>
