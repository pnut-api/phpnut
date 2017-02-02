<?php

// checking if the 'Remember me' checkbox was clicked
if (isset($_GET['rem'])) {
	session_start();
	if ($_GET['rem']=='1') {
		$_SESSION['rem']=1;
	} else {
		unset($_SESSION['rem']);
	}
	header('Location: index.php');
}

require_once '../EZphpnut.php';
$app = new EZphpnut();

// check that the user is signed in
if ($app->getSession()) {

	// get the current user as JSON
	$data = $app->getUser();

	// accessing the user's cover image
	echo '<body style="background:url('.$data['cover_image']['url'].')">';
	echo '<div style="background:#fff;opacity:0.8;padding:20px;margin:10px;border-radius:15px;">';
	echo '<h1>Welcome to <a target="_blank" href="https://github.com/jdolitsky/phpnut">';
	echo 'phpnut</a> (the EZ version)</h1>';

	// accessing the user's name
	echo '<h3>'.$data['name'].'</h3>';
	
	// accessing the user's avatar image
	echo '<img style="border:2px solid #000;" src="'.$data['avatar_image']['url'].'" /><br>';

	// try posting to pnut
	if (isset($_POST['run_tests'])) {
		print "<hr />";
		print "<h3>Testing pnut functionality</h3>";
		print '<pre>';
		print '<b>Your access token is: </b>'.htmlspecialchars($app->setSession())."\n";
		$token = $app->setSession();
		print "<b>Clearing access token</b>\n";
		$app->setAccessToken(null);
		print "<b>Checking that we can no longer access pnut.io's API...</b>";
		print '<blockquote>';
		try {
			$denied = $app->getUser();
			print " error - we were granted access without a token?!?\n";
			exit;
		}
		catch (phpnutException $e) {
			if ($e->getCode()==401) {
				print " success (could not get access)\n";
			}
			else {
				throw $e;
			}
		}
		print '</blockquote>';
		print "<b>Resetting access token</b>\n";
		$app->setAccessToken($token);
		print "<b>Attempting access again (this should work this time)...</b>";
		print '<blockquote>';
		$allowed = $app->getUser();
		if (!$allowed || !isset($allowed['name']) || $allowed['name']!=$data['name']) {
			print " error getting access again\n";
			var_dump($allowed);
			exit;
		}
		print "Success! We were granted access\n";
		print '</blockquote>';
		print "<b>Attempting to post a test message to pnut.io...</b>\n";
		print "<blockquote>";
		$sampleText = "Testing posting to pnut.io using phpnut - ".uniqid(mt_rand(0,100000));
		$create = $app->createPost($sampleText);
		// we should now have a post ID and the text should be the same as above
		if (!$create || !$create['id'] || $create['text']!=$sampleText) {
			print "Error posting sample text to pnut\n";
			var_dump($create);
			exit;
		}
		print "Successfully posted to pnut, post ID is ".$create['id']."\n";
		print "</blockquote>";

		// try fetching the post
		print "<b>Attempting to fetch sample post from pnut.io...</b>\n";
		print "<blockquote>";
		$get = $app->getPost($create['id']);
		if (!$get || !$get['id'] || $get['id']!=$create['id'] || $get['text']!=$sampleText) {
			print "Error fetching sample post from pnut:\n";
			var_dump($get);
			exit;
		}
		print "Successfully retrieved the sample post from pnut, post ID is ".$get['id']."\n";
		print "</blockquote>";

		// try deleting the post
		print "<b>Attempting to delete the sample post from pnut.io...</b>\n";
		print "<blockquote>";
		$delete = $app->deletePost($create['id']);
		if (!$delete || !$delete['id'] || $delete['id']!=$create['id']) {
			print "Error deleting sample post from pnut:\n";
			var_dump($delete);
			exit;
		}
		print "Successfully deleted the sample post from pnut, post ID was ".$delete['id']."\n";
		print "</blockquote>";

		// more tests can/should be included here

		// done tests!
		print "<b>All test completed successfully!</b>\n";
		print "</pre>";
	}

	else {
		print "<hr />";
		print "<h3>Complete user data</h3>";
		echo '<pre style="font-weight:bold;font-size:16px">';
		print_r($data);
		echo '</pre>';
	}

	print "<hr />";
	print '<form method="POST" action="index.php"><input type="submit" name="run_tests" value="Run POST/GET/DELETE tests" /><br />This will post a test message to your stream under your name, fetch it, then delete it.</form>';

	print "<hr />";
	echo '<h2><a href="signout.php">Sign out</a></h2>';

	echo '</div></body>';

// otherwise prompt to sign in
} else {
	
	$url = $app->getAuthUrl();
	echo '<a href="'.$url.'"><h2>Sign in using pnut.io</h2></a>';
	if (isset($_SESSION['rem'])) {
		echo 'Remember me <input type="checkbox" id="rem" value="1" checked/>';
	} else {
		echo 'Remember me <input type="checkbox" id="rem" value="2" />';
	}
	?>
	<script>
	document.getElementById('rem').onclick = function(e){
		if (document.getElementById('rem').value=='1') {
			window.location='?rem=2';
		} else {
			window.location='?rem=1';
		};
	}
	</script>
	<?php
}

?>

