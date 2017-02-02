<?php
/**
 * phpnut.php
 * pnut.io PHP library
 * https://github.com/pnut-api/phpnut
 *
 * This class handles a lower level type of access to pnut.io. It's ideal
 * for command line scripts and other places where you want full control
 * over what's happening, and you're at least a little familiar with oAuth.
 *
 * Alternatively you can use the EZphpnut class which automatically takes
 * care of a lot of the details like logging in, keeping track of tokens,
 * etc. EZphpnut assumes you're accessing pnut.io via a browser, whereas
 * this class tries to make no assumptions at all.
 */
class phpnut {

    protected $_baseUrl = 'https://api.pnut.io/v0/';
    protected $_authUrl = 'https://pnut.io/oauth/';

    private $_authPostParams=[];

    // stores the access token after login
    private $_accessToken = null;

    // stores the App access token if we have it
    private $_appAccessToken = null;

    // stores the user ID returned when fetching the auth token
    private $_user_id = null;

    // stores the username returned when fetching the auth token
    private $_username = null;

    // The total number of requests you're allowed within the alloted time period
    private $_rateLimit = null;

    // The number of requests you have remaining within the alloted time period
    private $_rateLimitRemaining = null;

    // The number of seconds remaining in the alloted time period
    private $_rateLimitReset = null;

    // The scope the user has
    private $_scope = null;

    // token scopes
    private $_scopes=[];

    // debug info
    private $_last_request = null;
    private $_last_response = null;

    // ssl certification
    private $_sslCA = null;

    // the callback function to be called when an event is received from the stream
    private $_streamCallback = null;

    // the stream buffer
    private $_streamBuffer = '';

    // stores the curl handler for the current stream
    private $_currentStream = null;

    // stores the curl multi handler for the current stream
    private $_multiStream = null;

    // stores the number of failed connects, so we can back off multiple failures
    private $_connectFailCounter = 0;

    // stores the most recent stream url, so we can re-connect when needed
    private $_streamUrl = null;

    // keeps track of the last time we've received a packet from the api, if it's too long we'll reconnect
    private $_lastStreamActivity = null;

    // stores the headers received when connecting to the stream
    private $_streamHeaders = null;

    // response meta max_id data
    private $_maxid = null;

    // response meta min_id data
    private $_minid = null;

    // response meta more data
    private $_more = null;

    // response stream marker data
    private $_last_marker = null;

    // strip envelope response from returned value
    private $_stripResponseEnvelope=true;

    // if processing stream_markers or any fast stream, decrease $sleepFor
    public $streamingSleepFor=20000;

    /**
     * Constructs an phpnut PHP object with the specified client ID and
     * client secret.
     * @param string $client_id The client ID you received from pnut.io when
     * creating your app.
     * @param string $client_secret The client secret you received from
     * pnut.io when creating your app.
     */
    public function __construct($client_id,$client_secret) {
        $this->_clientId = $client_id;
        $this->_clientSecret = $client_secret;

        // if the digicert certificate exists in the same folder as this file,
        // remember that fact for later
        if (file_exists(dirname(__FILE__).'/DigiCertHighAssuranceEVRootCA.pem')) {
            $this->_sslCA = dirname(__FILE__).'/DigiCertHighAssuranceEVRootCA.pem';
        }
    }

    /**
     * Set whether or not to strip Envelope Response (meta) information
     * This option will be deprecated in the future. Is it to allow
     * a stepped migration path between code expecting the old behavior
     * and new behavior. When not stripped, you still can use the proper
     * method to pull the meta information. Please start converting your code ASAP
     */
    public function includeResponseEnvelope() {
        $this->_stripResponseEnvelope=false;
    }

    /**
     * Construct the proper Auth URL for the user to visit and either grant
     * or not access to your app. Usually you would place this as a link for
     * the user to client, or a redirect to send them to the auth URL.
     * Also can be called after authentication for additional scopes
     * @param string $callbackUri Where you want the user to be directed
     * after authenticating with pnut.io. This must be one of the URIs
     * allowed by your pnut.io application settings.
     * @param array $scope An array of scopes (permissions) you wish to obtain
     * from the user. Currently options are stream, email, write_post, follow,
     * messages, and export. If you don't specify anything, you'll only receive
     * access to the user's basic profile (the default).
     */
    public function getAuthUrl($callback_uri,$scope=null) {

        // construct an authorization url based on our client id and other data
        $data = [
            'client_id'=>$this->_clientId,
            'response_type'=>'code',
            'redirect_uri'=>$callback_uri,
        ];

        $url = $this->_authUrl;
        if ($this->_accessToken) {
            $url .= 'authorize?';
        } else {
            $url .= 'authenticate?';
        }
        $url .= $this->buildQueryString($data);

        if ($scope) {
            $url .= '&scope='.implode('+',$scope);
        }

        // return the constructed url
        return $url;
    }

    /**
     * Call this after they return from the auth page, or anytime you need the
     * token. For example, you could store it in a database and use
     * setAccessToken() later on to return on behalf of the user.
     */
    public function getAccessToken($callback_uri) {
        // if there's no access token set, and they're returning from
        // the auth page with a code, use the code to get a token
        if (!$this->_accessToken && isset($_GET['code']) && $_GET['code']) {

            // construct the necessary elements to get a token
            $data = [
                'client_id'=>$this->_clientId,
                'client_secret'=>$this->_clientSecret,
                'grant_type'=>'authorization_code',
                'redirect_uri'=>$callback_uri,
                'code'=>$_GET['code']
            ];

            // try and fetch the token with the above data
            $res = $this->httpReq('post',$this->_authUrl.'access_token', $data);

            // store it for later
            $this->_accessToken = $res['access_token'];
            $this->_username = $res['username'];
            $this->_user_id = $res['user_id'];
        }

        // return what we have (this may be a token, or it may be nothing)
        return $this->_accessToken;
    }

    /**
     * Check the scope of current token to see if it has required scopes
     * has to be done after a check
     */
    public function checkScopes($app_scopes) {
        if (!count($this->_scopes)) {
            return -1; // _scope is empty
        }
        $missing=[];
        foreach($app_scopes as $scope) {
            if (!in_array($scope,$this->_scopes)) {
                if ($scope=='public_messages') {
                    // messages works for public_messages
                    if (in_array('messages',$this->_scopes)) {
                        // if we have messages in our scopes
                        continue;
                    }
                }
                $missing[]=$scope;
            }
        }
        // identify the ones missing
        if (count($missing)) {
            // do something
            return $missing;
        }
        return 0; // 0 missing
     }

    /**
     * Set the access token (eg: after retrieving it from offline storage)
     * @param string $token A valid access token you're previously received
     * from calling getAccessToken().
     */
    public function setAccessToken($token) {
        $this->_accessToken = $token;
    }

    /**
     * Deauthorize the current token (delete your authorization from the API)
     * Generally this is useful for logging users out from a web app, so they
     * don't get automatically logged back in the next time you redirect them
     * to the authorization URL.
     */
    public function deauthorizeToken() {
        return $this->httpReq('delete',$this->_baseUrl.'token');
    }

    /**
     * Returns the total number of requests you're allowed within the
     * alloted time period.
     * @see getRateLimitReset()
     */
    public function getRateLimit() {
        return $this->_rateLimit;
    }

    /**
     * The number of requests you have remaining within the alloted time period
     * @see getRateLimitReset()
     */
    public function getRateLimitRemaining() {
        return $this->_rateLimitRemaining;
    }

    /**
     * The number of seconds remaining in the alloted time period.
     * When this time is up you'll have getRateLimit() available again.
     */
    public function getRateLimitReset() {
        return $this->_rateLimitReset;
    }

    /**
     * The scope the user has
     */
    public function getScope() {
        return $this->_scope;
    }

    /**
     * Internal function, parses out important information pnut.io adds
     * to the headers.
     */
    protected function parseHeaders($response) {
        // take out the headers
        // set internal variables
        // return the body/content
        $this->_rateLimit = null;
        $this->_rateLimitRemaining = null;
        $this->_rateLimitReset = null;
        $this->_scope = null;

        $response = explode("\r\n\r\n",$response,2);
        $headers = $response[0];

        if($headers == 'HTTP/1.1 100 Continue') {
            $response = explode("\r\n\r\n",$response[1],2);
            $headers = $response[0];
        }

        if (isset($response[1])) {
            $content = $response[1];
        }
        else {
            $content = null;
        }

        // this is not a good way to parse http headers
        // it will not (for example) take into account multiline headers
        // but what we're looking for is pretty basic, so we can ignore those shortcomings
        $headers = explode("\r\n",$headers);
        foreach ($headers as $header) {
            $header = explode(': ',$header,2);
            if (count($header)<2) {
                continue;
            }
            list($k,$v) = $header;
            switch ($k) {
                case 'X-RateLimit-Remaining':
                    $this->_rateLimitRemaining = $v;
                    break;
                case 'X-RateLimit-Limit':
                    $this->_rateLimit = $v;
                    break;
                case 'X-RateLimit-Reset':
                    $this->_rateLimitReset = $v;
                    break;
                case 'X-OAuth-Scopes':
                    $this->_scope = $v;
                    $this->_scopes=explode(',',$v);
                    break;
            }
        }
        return $content;
    }

    /**
     * Internal function. Used to turn things like TRUE into 1, and then
     * calls http_build_query.
     */
    protected function buildQueryString($array) {
        foreach ($array as $k=>&$v) {
            if ($v===true) {
                $v = '1';
            }
            elseif ($v===false) {
                $v = '0';
            }
            unset($v);
        }
        return http_build_query($array);
    }


    /**
     * Internal function to handle all
     * HTTP requests (POST,PUT,GET,DELETE)
     */
    protected function httpReq($act, $req, $params=[],$contentType='application/x-www-form-urlencoded') {
        $ch = curl_init($req);
        $headers = array();
        if($act != 'get') {
            curl_setopt($ch, CURLOPT_POST, true);
            // if they passed an array, build a list of parameters from it
            if (is_array($params) && $act != 'post-raw') {
                $params = $this->buildQueryString($params);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            $headers[] = "Content-Type: ".$contentType;
        }
        if($act != 'post' && $act != 'post-raw') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($act));
        }
        if($act == 'get' && isset($params['access_token'])) {
            $headers[] = 'Authorization: Bearer '.$params['access_token'];
        }
        else if ($this->_accessToken) {
            $headers[] = 'Authorization: Bearer '.$this->_accessToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        if ($this->_sslCA) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->_sslCA);
        }
        $this->_last_response = curl_exec($ch);
        $this->_last_request = curl_getinfo($ch,CURLINFO_HEADER_OUT);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status==0) {
            throw new phpnutException('Unable to connect to '.$req);
        }
        if ($this->_last_request===false) {
            if (!curl_getinfo($ch,CURLINFO_SSL_VERIFYRESULT)) {
                throw new phpnutException('SSL verification failed, connection terminated.');
            }
        }
        if ($this->_last_response) {
            $response = $this->parseHeaders($this->_last_response);
            if ($response) {
                $response = json_decode($response,true);

                if (isset($response['meta'])) {
                    if (isset($response['meta']['max_id'])) {
                        $this->_maxid=$response['meta']['max_id'];
                        $this->_minid=$response['meta']['min_id'];
                    }
                    if (isset($response['meta']['more'])) {
                        $this->_more=$response['meta']['more'];
                    }
                    if (isset($response['meta']['marker'])) {
                        $this->_last_marker=$response['meta']['marker'];
                    }
                }

                // look for errors
                if (isset($response['error'])) {
                    if (is_array($response['error'])) {
                        throw new phpnutException($response['error']['message'],
                                        $response['error']['code']);
                    }
                    else {
                        throw new phpnutException($response['error']);
                    }
                } 

                // look for response migration errors
                elseif (isset($response['meta'], $response['meta']['error_message'])) {
                    throw new phpnutException($response['meta']['error_message'],$response['meta']['code']);
                }

            }
        }

        if ($http_status<200 || $http_status>=300) {
            throw new phpnutException('HTTP error '.$http_status);
        }

        // if we've received a migration response, handle it and return data only
        elseif ($this->_stripResponseEnvelope && isset($response['meta'], $response['data'])) {
            return $response['data'];
        }

        // else non response migration response, just return it
        else if (isset($response)) {
            return $response;
        }

        else {
            throw new phpnutException("No response");
        }
    }


    /**
     * Get max_id from last meta response data envelope
     */
    public function getResponseMaxID() {
        return $this->_maxid;
    }

    /**
     * Get min_id from last meta response data envelope
     */
    public function getResponseMinID() {
        return $this->_minid;
    }

    /**
     * Get more from last meta response data envelope
     */
    public function getResponseMore() {
        return $this->_more;
    }

    /**
     * Get marker from last meta response data envelope
     */
    public function getResponseMarker() {
        return $this->_last_marker;
    }

    /**
     * Fetch API configuration object
     */
    public function getConfig() {
        return $this->httpReq('get',$this->_baseUrl.'sys/config');
    }

    /**
     * Process user content, message or post text.
     * Mentions and hashtags will be parsed out of the
     * text, as will bare URLs. To create a link in the text without using a
     * bare URL, include the anchor text in the object text and include a link
     * entity in the function call.
     * @param string $text The text of the user/message/post
     * @param array $data An associative array of optional post data. This
     * will likely change as the API evolves, as of this writing allowed keys are:
     * reply_to, and raw. "raw" may be a complex object represented
     * by an associative array.
     * @param array $params An associative array of optional data to be included
     * in the URL (such as 'include_raw')
     * @return array An associative array representing the post.
     */
    public function processText($text=null, $data = [], $params = []) {
        $data['text'] = $text;
        $json = json_encode($data);
        $qs = '';
        if (!empty($params)) {
            $qs = '?'.$this->buildQueryString($params);
        }
        return $this->httpReq('post',$this->_baseUrl.'text/process'.$qs, $json, 'application/json');
    }

    /**
     * Create a new Post object. Mentions and hashtags will be parsed out of the
     * post text, as will bare URLs. To create a link in a post without using a
     * bare URL, include the anchor text in the post's text and include a link
     * entity in the post creation call.
     * @param string $text The text of the post
     * @param array $data An associative array of optional post data. This
     * will likely change as the API evolves, as of this writing allowed keys are:
     * reply_to, is_nsfw, and raw. "raw" may be a complex object represented
     * by an associative array.
     * @param array $params An associative array of optional data to be included
     * in the URL (such as 'include_raw')
     * @return array An associative array representing the post.
     */
    public function createPost($text=null, $data = [], $params = []) {
        $data['text'] = $text;
        $json = json_encode($data);
        $qs = '';
        if (!empty($params)) {
            $qs = '?'.$this->buildQueryString($params);
        }
        return $this->httpReq('post',$this->_baseUrl.'posts'.$qs, $json, 'application/json');
    }

    /**
     * Returns a specific Post.
     * @param integer $post_id The ID of the post to retrieve
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are: include_raw.
     * @return array An associative array representing the post
     */
    public function getPost($post_id=null,$params = []) {
        return $this->httpReq('get',$this->_baseUrl.'posts/'.urlencode($post_id)
                        .'?'.$this->buildQueryString($params));
    }

    /**
     * Delete a Post. The current user must be the same user who created the Post.
     * It returns the deleted Post on success.
     * @param integer $post_id The ID of the post to delete
     * @param array An associative array representing the post that was deleted
     */
    public function deletePost($post_id=null) {
        return $this->httpReq('delete',$this->_baseUrl.'posts/'.urlencode($post_id));
    }

    /**
     * Retrieve the Posts that are 'in reply to' a specific Post.
     * @param integer $post_id The ID of the post you want to retrieve replies for.
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are:    count, before_id, since_id, include_muted, include_deleted,
     * and include_post_raw.
     * @return An array of associative arrays, each representing a single post.
     */
    public function getPostThread($post_id=null,$params = []) {
        return $this->httpReq('get',$this->_baseUrl.'posts/'.urlencode($post_id)
                .'/thread?'.$this->buildQueryString($params));
    }

    /**
     * Get the most recent Posts created by a specific User in reverse
     * chronological order (most recent first).
     * @param mixed $user_id Either the ID of the user you wish to retrieve posts by,
     * or the string "me", which will retrieve posts for the user you're authenticated
     * as.
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are:    count, before_id, since_id, include_muted, include_deleted,
     * and include_post_raw.
     * @return An array of associative arrays, each representing a single post.
     */
    public function getUserPosts($user_id='me', $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users/'.urlencode($user_id)
                    .'/posts?'.$this->buildQueryString($params));
    }

    /**
     * Get the most recent Posts mentioning by a specific User in reverse
     * chronological order (newest first).
     * @param mixed $user_id Either the ID of the user who is being mentioned, or
     * the string "me", which will retrieve posts for the user you're authenticated
     * as.
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are:    count, before_id, since_id, include_muted, include_deleted,
     * and include_post_raw.
     * @return An array of associative arrays, each representing a single post.
     */
    public function getUserMentions($user_id='me',$params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users/'
            .urlencode($user_id).'/mentions?'.$this->buildQueryString($params));
    }

    /**
     * Return the 20 most recent posts from the current User and
     * the Users they follow.
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are:    count, before_id, since_id, include_muted, include_deleted,
     * and include_post_raw.
     * @return An array of associative arrays, each representing a single post.
     */
    public function getUserStream($params = []) {
        return $this->httpReq('get',$this->_baseUrl.'posts/streams/me?'.$this->buildQueryString($params));
    }

    /**
     * Returns a specific user object.
     * @param mixed $user_id The ID of the user you want to retrieve, or the string "@-username", or the string
     * "me" to retrieve data for the users you're currently authenticated as.
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are: include_raw|include_user_raw.
     * @return array An associative array representing the user data.
     */
    public function getUser($user_id='me', $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users/'.urlencode($user_id)
                        .'?'.$this->buildQueryString($params));
    }

    /**
     * Returns multiple users request by an array of user ids
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are: include_raw|include_user_raw.
     * @return array An associative array representing the users data.
     */
    public function getUsers($user_arr, $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users?ids='.join(',',$user_arr)
                    .'&'.$this->buildQueryString($params));
    }

    /**
     * Add the specified user ID to the list of users followed.
     * Returns the User object of the user being followed.
     * @param integer $user_id The user ID of the user to follow.
     * @return array An associative array representing the user you just followed.
     */
    public function followUser($user_id=null) {
        return $this->httpReq('put',$this->_baseUrl.'users/'.urlencode($user_id).'/follow');
    }

    /**
     * Removes the specified user ID to the list of users followed.
     * Returns the User object of the user being unfollowed.
     * @param integer $user_id The user ID of the user to unfollow.
     * @return array An associative array representing the user you just unfollowed.
     */
    public function unfollowUser($user_id=null) {
        return $this->httpReq('delete',$this->_baseUrl.'users/'.urlencode($user_id).'/follow');
    }

    /**
     * Returns an array of User objects the specified user is following.
     * @param mixed $user_id Either the ID of the user being followed, or
     * the string "me", which will retrieve posts for the user you're authenticated
     * as.
     * @return array An array of associative arrays, each representing a single
     * user following $user_id
     */
    public function getFollowing($user_id='me') {
        return $this->httpReq('get',$this->_baseUrl.'users/'.$user_id.'/following');
    }
    
    /**
     * Returns an array of User ids the specified user is following.
     * @param mixed $user_id Either the ID of the user being followed, or
     * the string "me", which will retrieve posts for the user you're authenticated
     * as.
     * @return array user ids the specified user is following.
     */
    public function getFollowingIDs($user_id='me') {
        return $this->httpReq('get',$this->_baseUrl.'users/'.$user_id.'/following?include_user_as_id=1');
    }
    
    /**
     * Returns an array of User objects for users following the specified user.
     * @param mixed $user_id Either the ID of the user being followed, or
     * the string "me", which will retrieve posts for the user you're authenticated
     * as.
     * @return array An array of associative arrays, each representing a single
     * user following $user_id
     */
    public function getFollowers($user_id='me') {
        return $this->httpReq('get',$this->_baseUrl.'users/'.$user_id.'/followers');
    }
    
    /**
     * Returns an array of User ids for users following the specified user.
     * @param mixed $user_id Either the ID of the user being followed, or
     * the string "me", which will retrieve posts for the user you're authenticated
     * as.
     * @return array user ids for users following the specified user
     */
    public function getFollowersIDs($user_id='me') {
        return $this->httpReq('get',$this->_baseUrl.'users/'.$user_id.'/followers?include_user_as_id=1');
    }

    /**
     * Retrieve a list of all public Posts on pnut.io, often referred to as the
     * global stream.
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are:    count, before_id, since_id, include_muted, include_deleted,
     * and include_post_raw.
     * @return An array of associative arrays, each representing a single post.
     */
    public function getPublicPosts($params = []) {
        return $this->httpReq('get',$this->_baseUrl.'posts/streams/global?'.$this->buildQueryString($params));
    }

    /**
     * List User interactions
     */
    public function getMyActions($params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users/me/actions?'.$this->buildQueryString($params));
    }

    /**
     * Retrieve a user's user ID by specifying their username.
     * @param string $username The username of the user you want the ID of, without
     * an @ symbol at the beginning.
     * @return integer The user's user ID
     */
    public function getIdByUsername($username=null) {
        return $this->httpReq('get',$this->_baseUrl.'users/@'.$username.'?include_user_as_id=1');
    }

    /**
     * Mute a user
     * @param integer $user_id The user ID to mute
     */
    public function muteUser($user_id=null) {
         return $this->httpReq('put',$this->_baseUrl.'users/'.urlencode($user_id).'/mute');
    }

    /**
     * Unmute a user
     * @param integer $user_id The user ID to unmute
     */
    public function unmuteUser($user_id=null) {
        return $this->httpReq('delete',$this->_baseUrl.'users/'.urlencode($user_id).'/mute');
    }

    /**
     * List the users muted by the current user
     * @return array An array of associative arrays, each representing one muted user.
     */
    public function getMuted() {
        return $this->httpReq('get',$this->_baseUrl.'users/me/muted');
    }

    /**
    * Bookmark a post
    * @param integer $post_id The post ID to bookmark
    */
    public function bookmarkPost($post_id=null) {
        return $this->httpReq('put',$this->_baseUrl.'posts/'.urlencode($post_id).'/bookmark');
    }

    /**
    * Unbookmark a post
    * @param integer $post_id The post ID to unbookmark
    */
    public function unbookmarkPost($post_id=null) {
        return $this->httpReq('delete',$this->_baseUrl.'posts/'.urlencode($post_id).'/bookmark');
    }

    /**
    * List the posts bookmarked by the current user
    * @param array $params An associative array of optional general parameters.
    * This will likely change as the API evolves, as of this writing allowed keys
    * are:    count, before_id, since_id, include_muted, include_deleted,
    * and include_post_raw.
    * See https://github.com/appdotnet/api-spec/blob/master/resources/posts.md#general-parameters
    * @return array An array of associative arrays, each representing a single
    * user who has bookmarked a post
    */
    public function getBookmarked($user_id='me', $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users/'.urlencode($user_id).'/bookmarks'
                    .'?'.$this->buildQueryString($params));
    }

    /**
    * List the bookmarks of a post
    * @param integer $post_id the post ID to get stars from
    * @return array An array of associative arrays, each representing one bookmark action.
    */
    public function getPostBookmarks($post_id=null) {
        return $this->httpReq('get',$this->_baseUrl.'posts/'.urlencode($post_id).'/actions?filter=bookmark');
    }

    /**
     * Returns an array of User objects of users who reposted the specified post.
     * @param integer $post_id the post ID to
     * @return array An array of associative arrays, each representing a single
     * user who reposted $post_id
     */
    public function getPostReposts($post_id){
        return $this->httpReq('get',$this->_baseUrl.'posts/'.urlencode($post_id).'/actions?filter=repost');
    }

    /**
     * Repost an existing Post object.
     * @param integer $post_id The id of the post
     * @return the reposted post
     */
    public function repost($post_id){
        return $this->httpReq('put',$this->_baseUrl.'posts/'.urlencode($post_id).'/repost');
    }

    /**
     * Delete a post that the user has reposted.
     * @param integer $post_id The id of the post
     * @return the un-reposted post
     */
    public function deleteRepost($post_id){
        return $this->httpReq('delete',$this->_baseUrl.'posts/'.urlencode($post_id).'/repost');
    }

    /**
     * Get a user object by username
     * @param string $name the @name to get
     * @return array representing one user
     */
    public function getUserByName($name=null) {
        return $this->httpReq('get',$this->_baseUrl.'users/@'.$name);
    }

    /**
     * Return the 20 most recent posts for a stream using a valid Token
     * @param array $params An associative array of optional general parameters.
     * This will likely change as the API evolves, as of this writing allowed keys
     * are: count, before_id, since_id, include_muted, include_deleted,
     * and include_post_raw.
     * @return An array of associative arrays, each representing a single post.
     */
    public function getUserPersonalStream($params = []) {
        if ($params['access_token']) {
            return $this->httpReq('get',$this->_baseUrl.'posts/streams/me?'.$this->buildQueryString($params),$params);
        } else {
            return $this->httpReq('get',$this->_baseUrl.'posts/streams/me?'.$this->buildQueryString($params));
        }
    }
    
    /**
    * Return the 20 most recent Posts from the current User's personalized stream
    * and mentions stream merged into one stream.
    * @param array $params An associative array of optional general parameters.
    * This will likely change as the API evolves, as of this writing allowed keys
    * are: count, before_id, since_id, include_muted, include_deleted,
    * include_directed_posts, and include_annotations.
    * @return An array of associative arrays, each representing a single post.
    */
    public function getUserUnifiedStream($params = []) {
        return $this->httpReq('get',$this->_baseUrl.'posts/streams/unified?'.$this->buildQueryString($params));
    }

    /**
     * Update Profile Data via JSON
     * @data array containing user descriptors
     */
    public function updateUserData($data = [], $params = []) {
        $json = json_encode($data);
        return $this->httpReq('put',$this->_baseUrl.'users/me'.'?'.
                        $this->buildQueryString($params), $json, 'application/json');
    }

    /**
     * Update a user image
     * @which avatar|cover
     * @image path reference to image
     */
    protected function updateUserImage($which = 'avatar', $image = null) {
        $data = array($which=>"@$image");
        return $this->httpReq('post-raw',$this->_baseUrl.'users/me/'.$which, $data, 'multipart/form-data');
    }

    public function updateUserAvatar($avatar = null) {
        if($avatar != null)
            return $this->updateUserImage('avatar', $avatar);
    }

    public function updateUserCover($cover = null) {
        if($cover != null)
            return $this->updateUserImage('cover', $cover);
    }

    /**
     * update stream marker
     */
    public function updateStreamMarker($data = []) {
        $json = json_encode($data);
        return $this->httpReq('post',$this->_baseUrl.'markers', $json, 'application/json');
    }

    /**
     * get a page of current user subscribed channels
     */
    public function getMyChannelSubscriptions($params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users/me/channels/subscribed?'.$this->buildQueryString($params));
    }

    /**
     * get user channels
     */
    public function getMyChannels($params = []) {
        return $this->httpReq('get',$this->_baseUrl.'users/me/channels?'.$this->buildQueryString($params));
    }

    /**
     * create a channel
     * note: you cannot create a channel with type=io.pnut.core.pm (see createMessage)
     */
    public function createChannel($data = []) {
        $json = json_encode($data);
        return $this->httpReq('post',$this->_baseUrl.'channels'.($pm?'/pm/messsages':''), $json, 'application/json');
    }

    /**
     * get channelid info
     */
    public function getChannel($channelid, $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'channels/'.$channelid.'?'.$this->buildQueryString($params));
    }

    /**
     * get multiple channels' info by an array of channelids
     */
    public function getChannels($channels, $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'channels?ids='.join(',',$channels).'&'.$this->buildQueryString($params));
    }

    /**
     * update channelid
     */
    public function updateChannel($channelid, $data = []) {
        $json = json_encode($data);
        return $this->httpReq('put',$this->_baseUrl.'channels/'.$channelid, $json, 'application/json');
    }

    /**
     * subscribe from channelid
     */
    public function channelSubscribe($channelid) {
        return $this->httpReq('put',$this->_baseUrl.'channels/'.$channelid.'/subscribe');
    }

    /**
     * unsubscribe from channelid
     */
    public function channelUnsubscribe($channelid) {
        return $this->httpReq('delete',$this->_baseUrl.'channels/'.$channelid.'/subscribe');
    }

    /**
     * get all user objects subscribed to channelid
     */
    public function getChannelSubscriptions($channelid, $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'channel/'.$channelid.'/subscribers?'.$this->buildQueryString($params));
    }

    /**
     * get all user IDs subscribed to channelid
     */
    public function getChannelSubscriptionsById($channelid) {
        return $this->httpReq('get',$this->_baseUrl.'channel/'.$channelid.'/subscribers?include_user_as_id=1');
    }
    
    /**
     * mark channel inactive
     */
    public function deleteChannel($channelid) {
        return $this->httpReq('delete',$this->_baseUrl.'channels/'.$channelid);
    }


    /**
     * get a page of messages in channelid
     */
    public function getMessages($channelid, $params = []) {
        return $this->httpReq('get',$this->_baseUrl.'channels/'.$channelid.'/messages?'.$this->buildQueryString($params));
    }

    /**
     * create message
     * @param $channelid numeric or "pm" for auto-chanenl (type=io.pnut.core.pm)
     * @param $data array('text'=>'YOUR_MESSAGE') If a type=io.pnut.core.pm, then "destinations" key can be set to address as an array of people to send this PM too
     */
    public function createMessage($channelid,$data) {
        $json = json_encode($data);
        return $this->httpReq('post',$this->_baseUrl.'channels/'.$channelid.'/messages', $json, 'application/json');
    }

    /**
     * get message
     */
    public function getMessage($channelid,$messageid) {
        return $this->httpReq('get',$this->_baseUrl.'channels/'.$channelid.'/messages/'.$messageid);
    }

    /**
     * delete messsage
     */
    public function deleteMessage($channelid,$messageid) {
        return $this->httpReq('delete',$this->_baseUrl.'channels/'.$channelid.'/messages/'.$messageid);
    }

    public function getLastRequest() {
        return $this->_last_request;
    }
    public function getLastResponse() {
        return $this->_last_response;
    }

}

class phpnutException extends Exception {}
