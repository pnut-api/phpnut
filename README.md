phpnut
======

PHP library for the pnut.io Stream API

More info on the pnut.io Stream API <a href="https://github.com/pnut-api/api-spec">here</a>

**Contributors:**
* <a href="https://alpha.app.net/jdolitsky" target="_blank">@jdolitsky</a>
* <a href="https://pnut.io/@ravisorg" target="_blank">@ravisorg</a>
* <a href="https://github.com/wpstudio" target="_blank">@wpstudio</a>
* <a href="https://alpha.app.net/harold" target="_blank">@harold</a>
* <a href="https://alpha.app.net/hxf148" target="_blank">@hxf148</a>
* <a href="https://alpha.app.net/edent" target="_blank">@edent</a>
* <a href="https://pnut.io/@c" target="_blank">@cdn</a>
* <a href="https://pnut.io/@ryantharp" target="_blank">@ryantharp</a>
* <a href="https://pnut.io/@33mhz" target="_blank">@33MHz</a>

Usage:
--------
### EZphpnut

If you are planning to design an app for viewing within a browser that requires a login screen etc, this is a great place to start. This aims to hide all the nasty authentication stuff from the average developer. It is also recommended that you start here if you have never worked with OAuth and/or APIs before.

```php
<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new EZphpnut();

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

?>
```

A basic working example of ezphpnut is contained in the **ez-example** directory - see the <a href="./ez-example/README.md">README.md</a> in that directory for more info.


### phpnut

Use this class if you need more control of your application (such as running a command line process) or are integrating your code with an existing application that handles sessions/cookies in a different way.

First construct your authentication url.
```php
<?php

require_once 'phpnut.php';

// change these to your app's values
$clientId     = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$clientSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// construct the phpnut object
$app = new phpnut($clientId,$clientSecret);

$redirectUri  = 'http://localhost/callback.php';
$scope        =  array('stream','email','write_post','follow','messages','update_profile','presence');

// create an authentication Url
$url = $app->getAuthUrl($redirectUri,$scope);

?>
```
Once the user has authenticated the app, grab the token in the callback script, and get information about the user.
```php
<?php
require_once 'phpnut.php';
$app = new phpnut($clientId,$clientSecret);

// get the token returned by App.net
// (this also sets the token)
$token = $app->getAccessToken($redirectUri);

// get info about the user
$user = $app->getUser();

// get the unique user id
$userId = $user['id'];

?>
```
Save the token and user id in a database or elsewhere, then make API calls in future scripts after setting the token.
```php
<?php

$app->setAccessToken($token);

// post on behalf of the user w/ that token
$app->createPost('Hello world');

?>
```

To consume the stream, try something like:
```php
<?php

require_once 'phpnut.php';
$app = new phpnut($clientId,$clientSecret);

// You need an app token to consume the stream, get the token returned by App.net
// (this also sets the token)
$token = $app->getAppAccessToken();

// create a stream
// if you already have a stream you can skip this step
// this stream is going to consume posts and stars (but not follows)
$stream = $app->createStream(array('post','star','user_follow','stream_marker','message','channel','channel_subscription','mute','token','file'));
// you might want to save $stream['endpoint'] or $stream['id'] for later so
// you don't have to re-create the stream

// we need to create a callback function that will do something with posts/stars
// when they're received from the stream. This function should accept one single
// parameter that will be the php object containing the meta / data for the event.
function handleEvent($event) {
	switch ($event['meta']['type']) {
		case 'post':
			print "Handle a post type\n";
			break;
		case 'star':
			print "Handle a star type\n";
			break;
	}
}

// register that function as the stream handler
$app->registerStreamFunction('handleEvent');

// open the stream for reading
$app->openStream($stream['endpoint']);

// now we want to process the stream. We have two options. If all we're doing
// in this script is processing the stream, we can just call:
// $app->processStreamForever();
// otherwise you can create a loop, and call $app->processStream($milliseconds)
// intermittently, like:
while (true) {
	print "hello, I'm going to do some other non-streaming things here...\n";
	// now we're going to process the stream for awhile (3 seconds)
	$app->processStream(3000000);
	// then do something else...
}
?>
```

Copyright (c) 2013, Josh Dolitsky
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the Josh Dolitsky nor the names of its 
      contributors may be used to endorse or promote products derived 
      from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL TRAVIS RICHARDSON BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
