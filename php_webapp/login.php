<?php

require_once('config.php');
require_once('includes/db.php');
require_once('includes/skin.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	die("not implemented");
}

begin_page("Login");

?>
<div id="amazon-root"></div>
<script type="text/javascript">

window.onAmazonLoginReady = function() {
	amazon.Login.setClientId('amzn1.application-oa2-client.8059ed5fd2c04d5a930226b76ab335e1');
	};
(function(d) {
	var a = d.createElement('script');
	a.type = 'text/javascript';
	a.async = true;
	a.id = 'amazon-login-sdk';
	a.src = 'https://api-cdn.amazon.com/sdk/login1.js';
	d.getElementById('amazon-root').appendChild(a);
})(document);
</script>
<button id="LoginWithAmazon" style="border:0;background-color:transparent;">
  <img border="0" href="#" alt="Login with Amazon"
      src="https://images-na.ssl-images-amazon.com/images/G/01/lwa/btnLWA_gold_156x32.png"
          width="156" height="32" />
</button>

<script type="text/javascript">
<!--
  document.getElementById('LoginWithAmazon').onclick = function() {
      options = { scope : 'profile' };
      amazon.Login.authorize(options, 'http://carina.messiah.edu/webtd/handle_login.php');
  };

//-->
</script>
<?php

end_page();
