# phpnut

PHP library for the pnut.io API.


## Installation


You can install **phpnut** via composer or by downloading the source.

#### Via Composer:

**phpnut** is available on Packagist as the [`pnut-api/phpnut`](http://packagist.org/packages/pnut-api/phpnut) package:

```
composer require pnut-api/phpnut
```

To include the library in your project, you may use normal autoloading similar to `require_once __DIR__.'/vendor/autoload.php';` if your project uses [Composer](https://getcomposer.org/). Otherwise, you can also `require_once 'phpnut.php';` or `require_once 'ezphpnut.php'`.


## Usage

### Quick examples


#### Create a post

```php
$app = new phpnut\phpnut($accessToken);

$app->createPost('Hello world', ['reply_to' => 123]);
```

#### Search for a tag

```php
$app = new phpnut\phpnut($clientId, $clientSecret);

$posts = $app->searchHashtags('mndp');

print_r($posts);
```


## EZphpnut

If you are planning to design an app for viewing within a browser that requires a login screen etc, this is a great place to start. This aims to hide all the nasty authentication stuff from the average developer. It is also recommended that you start here if you have never worked with OAuth and/or APIs before.

```php
<?php

$app = new phpnut\ezphpnut();

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


## phpnut

Use this class if you need more control of your application (such as running a command line process) or are integrating your code with an existing application that handles sessions/cookies in a different way.


### Credentials

If you already have an access token (in a cron job for example):

```php
<?php

$accessToken = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// construct the phpnut object
$app = new phpnut\phpnut($accessToken);

?>
```

If you have client credentials:

```php
<?php

$clientId     = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'; // from https://pnut.io/dev
$clientSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// construct the phpnut object
$app = new phpnut\phpnut($clientId, $clientSecret);

?>
```

You can alternatively use constants:

```php
<?php

// define('PNUT_ACCESS_TOKEN', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
define('PNUT_CLIENT_ID', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
define('PNUT_CLIENT_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

// construct the phpnut object
$app = new phpnut\phpnut();

?>
```



### Applications using client ID and client secret

First construct your authentication url.
```php
<?php

// change these to your app's values
$clientId     = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$clientSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// construct the phpnut object
$app = new phpnut\phpnut($clientId,$clientSecret);

$redirectUri  = 'http://localhost/callback.php';
$scope        = ['stream','email','write_post','follow','messages','update_profile','presence'];

// create an authentication Url
$url = $app->getAuthUrl($redirectUri,$scope);

?>
```

Once the user has authenticated the app, grab the token in the callback script, and get information about the user.

```php
<?php

$app = new phpnut\phpnut($clientId,$clientSecret);

// get the token returned by Pnut
// (this also sets the token)
$token = $app->getAccessToken($redirectUri);

// get info about the user
$user = $app->getUser();

// get the unique user id
$userId = $user['id'];

```

Save the token and user id in a database or elsewhere, then make API calls in future scripts after setting the token.

```php
<?php

$app->setAccessToken($token);

// post on behalf of the user with that token
$app->createPost('Hello world');

```


### App streams (websocket)

To consume the stream, try something like:

```php
<?php

$app = new phpnut\phpnut($clientId,$clientSecret);

// You need an app token to consume the stream, get the token returned by Pnut.io
// (this also sets the token)
$token = $app->getAppAccessToken();

// create a stream
// if you already have a stream you can skip this step
// this stream is going to consume posts and bookmarks (but not follows)
$stream = $app->createStream(['post','bookmark','follow']);
// you might want to save $stream['endpoint'] or $stream['id'] for later so
// you don't have to re-create the stream

// we need to create a callback function that will do something with posts/bookmarks
// when they're received from the stream. This function should accept one single
// parameter that will be the php object containing the meta / data for the event.
function handleEvent($event) {
    switch ($event['meta']['type']) {
        case 'post':
            print "Handle a post type\n";
            break;
        case 'bookmark':
            print "Handle a bookmark type\n";
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
```

## Documentation

More info on the pnut.io API [here](https://docs.pnut.io).

A git repository of it [is also available](https://github.com/pnut-api/api-spec).

For individual help, ask questions in the [Developer chat](https://patter.chat/18).


## Prerequisites

* PHP >= 8.0


## Contributors

* <a href="https://alpha.app.net/jdolitsky" target="_blank">@jdolitsky</a>
* <a href="https://pnut.io/@ravisorg" target="_blank">@ravisorg</a>
* <a href="https://github.com/wpstudio" target="_blank">@wpstudio</a>
* <a href="https://alpha.app.net/harold" target="_blank">@harold</a>
* <a href="https://alpha.app.net/hxf148" target="_blank">@hxf148</a>
* <a href="https://alpha.app.net/edent" target="_blank">@edent</a>
* <a href="https://pnut.io/@c" target="_blank">@cdn</a>
* <a href="https://pnut.io/@ryantharp" target="_blank">@ryantharp</a>
* <a href="https://pnut.io/@33mhz" target="_blank">@33MHz</a>
