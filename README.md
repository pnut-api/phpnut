AppDotNetPHP
============

PHP library for the App.net Stream API

More info on the App.net Stream API here: https://github.com/appdotnet/api-spec

NOTE:<br>
The Stream API is currently under development. This library will be rapidly changing in accordance with changes made in the API.

Usage:
--------
Good examples of how to use the library can be found in <b>index.php</b>, <b>callback.php</b>, and <b>signout.php</b>

Here is a simple example of sign-in and data retrieval:
<pre>
<code>
require_once 'AppDotNet.php';

$app = new AppDotNet();

// check that the user is signed in
if ($app->getSession()) {

	// post on behalf of the user
	$app->createPost('Hello world');

	// get the current user as JSON
	$data = $app->getUser();

	// accessing the user's username
	echo 'Welcome '.$data['username'];

// if not, redirect to sign in
} else {

	$url = $app->getAuthUrl();
	header('Location: '.$url);
	
}
</code>
</pre>

Setup:
--------
Open up <b>AppDotNet.php</b> for editing

You will need to change the values for the following between lines 22-34:
<ol>
<ul>Client ID</ul>
<ul>Client Secret</ul>
<ul>Callback URL</ul>
<ul>Scope</ul>
</ol>