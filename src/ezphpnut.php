<?php

namespace phpnut;

/**
 * EZphpnut.php
 * Class for easy web development
 * https://github.com/pnut-api/phpnut
 *
 * This class does as much of the grunt work as possible in helping you to
 * access the pnut.io API. In theory you don't need to know anything about
 * oAuth, tokens, or all the ugly details of how it works, it should "just
 * work". 
 *
 * Note this class assumes you're running a web site, and you'll be 
 * accessing it via a web browser (it expects to be able to do things like
 * cookies and sessions). If you're not using a web browser in your pnut.io
 * application, or you want more fine grained control over what's being
 * done for you, use the included phpnut class, which does much
 * less automatically.
 */

// comment these two lines out in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// comment this out if session is started elsewhere
session_start();

class ezphpnut extends phpnut
{
	private $_callbacks = [];
	private $_autoShutdownStreams = [];

	public function __construct(?string $client_id=null, ?string $client_secret=null)
	{
		// if client id wasn't passed, and it's in the settings.php file, use it from there
		if (!$client_id && defined('PNUT_CLIENT_ID')) {

			// if it's still the default, warn them
			if (PNUT_CLIENT_ID === 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX') {
				throw new phpnutException('You must change the values defined in ez-settings.php');
			}

			$client_id = PNUT_CLIENT_ID;
			$client_secret = PNUT_CLIENT_SECRET;
		}

		// call the parent with the variables we have
		parent::__construct($client_id, $client_secret);

		// set up ez streaming
		$this->registerStreamFunction([$this, 'streamEZCallback']);

		// make sure we cleanup/destroy any streams when we exit
		register_shutdown_function([$this, 'stopStreaming']);
	}

	public function getAuthUrl(?string $callback_uri=null, array|string|null $scope=null): string
	{
		// retained for compatibility
		return parent::getAuthUrl($callback_uri, $scope);
	}

	// user login
	public function setSession(int $cookie=0, ?string $callback=null)
	{
		if (!isset($callback) && defined('PNUT_REDIRECT_URI')) {
			$cb = PNUT_REDIRECT_URI;
		} else {
			$cb = $callback;
		}

		// try and set the token the original way (eg: if they're logging in)
		$token = $this->getAccessToken($cb);

		// if that didn't work, check to see if there's an existing token stored somewhere
		if (!$token) {
			$token = $this->getSession();
		}

		$_SESSION['phpnutAccessToken'] = $token;

		// if they want to stay logged in via a cookie, set the cookie
		if ($token && $cookie !== 0) {
			$cookie_lifetime = time()+(60*60*24*7*$cookie);
			setcookie('phpnutAccessToken', $token, $cookie_lifetime);
		}

		return $token;
	}

	// check if user is logged in
	public function getSession(): false|string
	{
		// first check for cookie
		if (isset($_COOKIE['phpnutAccessToken']) && $_COOKIE['phpnutAccessToken'] !== 'expired') {
			$this->setAccessToken($_COOKIE['phpnutAccessToken']);
			return $_COOKIE['phpnutAccessToken'];
		}
		// else check the session for the token (from a previous page load)
		elseif (isset($_SESSION['phpnutAccessToken'])) {
			$this->setAccessToken($_SESSION['phpnutAccessToken']);
			return $_SESSION['phpnutAccessToken'];
		}

		return false;
	}

	// log the user out
	public function deleteSession(): bool
	{
		// clear the session
		unset($_SESSION['phpnutAccessToken']);

		// unset the cookie
		setcookie('phpnutAccessToken', null, 1);

		// clear the access token
		$this->setAccessToken(null);

		// done!
		return true;
	}

	/**
	 * Registers a callback function to be called whenever an event of a certain
	 * type is received from the pnut.io streaming API. Your function will recieve
	 * a PHP associative array containing a pnut.io object. You must register at
	 * least one callback function before starting to stream (otherwise your data
	 * would simply be discarded). You can register multiple event types and even 
	 * multiple functions per event (just call this method as many times as needed).
	 * If you register multiple functions for a single event, each will be called
	 * every time an event of that type is received.
	 *
	 * Note you should not be doing any significant processing in your callback
	 * functions. Doing so could cause your scripts to fall behind the stream and
	 * risk getting disconnected. Ideally your callback functions should simply
	 * drop the data into a file or database to be collected and processed by
	 * another program.
	 * @param string $type The type of even your callback would like to recieve.
	 * At time of writing the possible options are 'post', 'bookmark', 'user_follow'.
	 */
	public function registerStreamCallback(string $type, $callback)
	{
		switch ($type) {
			case 'post':
			case 'bookmark':
			case 'user_follow':
				if (!array_key_exists($type,$this->_callbacks)) {
					$this->_callbacks[$type] = [];
				}
				$this->_callbacks[$type][] = $callback;
				return true;
				break;
			default:
				throw new phpnutException("Unknown callback type: {$type}");
		}
	}

	/**
	 * This is the easy way to start streaming. Register some callback functions
	 * using registerCallback(), then call startStreaming(). Every time the stream
	 * gets sent a type of object you have a callback for, your callback function(s)
	 * will be called with the proper data. When your script exits the streams will
	 * be cleaned up (deleted). 
	 * 
	 * Do not use this method if you want to spread out streams across multiple
	 * processes or multiple servers, since the first script that exits/crashes will
	 * delete the streams for everyone else. Instead use createStream() and openStream().
	 * @return true
	 * @see phpnutStream::stopStreaming()
	 * @see phpnutStream::createStream()
	 * @see phpnutStream::openStream()
	 */
	public function startStreaming(): bool
	{
		// only listen for object types that we have registered callbacks for
		if (!$this->_callbacks) {
			throw new phpnutException('You must register at least one callback function before calling startStreaming');
		}
		// if there's already a stream running, don't allow another
		if ($this->_currentStream) {
			throw new phpnutException('There is already a stream being consumed, only one stream can be consumed per phpnutStream instance');
		}
		$stream = $this->createStream(array_keys($this->_callbacks));
		// when the script exits, delete this stream (if it's still around)
		$this->_autoShutdownStreams[] = $response['id'];
		// start consuming
		$this->openStream($response['id']);
		return true;
	}

	/**
	 * This is the easy way to stop streaming and cleans up the no longer needed stream. 
	 * This method will be called automatically if you started streaming using 
	 * startStreaming().  
	 * 
	 * Do not use this method if you want to spread out streams across multiple
	 * processes or multiple servers, since it will delete the streams for everyone 
	 * else. Instead use closeStream().
	 * @return true
	 * @see phpnutStream::startStreaming()
	 * @see phpnutStream::deleteStream()
	 * @see phpnutStream::closeStream()
	 */
	public function stopStreaming(): bool
	{
		$this->closeStream();
		// delete any auto streams
		foreach ($this->_autoShutdownStreams as $streamId) {
			$this->deleteStream($streamId);
		}
		return true;
	}

	/**
	 * Internal function used to make your streaming easier. I hope.
	 */
	protected function streamEZCallback($type, $data): void
	{
		// if there are defined callbacks for this object type, then...
		if (array_key_exists($type, $this->_callbacks)) {
			// loop through the callbacks notifying each one in turn
			foreach ($this->_callbacks[$type] as $callback) {
				call_user_func($callback, $data);
			}
		}
	}

}
