<?php

/**
 *  Basic wrapper for Fitbit([ dev.fitbit.com ]) API handles authorization, get and refresh tokens, get profile, subscribe, list subscriptions and delete subscriptions.
 *
 * Note: Library is in beta and provided as-is. We hope to add features as API grows, however
 *       feel free to fork, extend and send pull requests.
 *
 * - https://github.com/beaglecode/fitbitphp-1
 *
 *
 */

class Fitbit{

    protected $id = "app_id";
    protected $secret = "app_secret";
    protected $redirectUrl = "redirect_uri";
    protected $oauth = "";
    protected $message = "";
    private $latestTokens = array(
        'access_token' => '',
        'refresh_token' => ''
    );

    public function __construct($id="", $secret="", $url=""){
        $this->id = $id;
        $this->secret = $secret;
        $this->redirectUrl = $url;
    }

    function setMessage($msg){
        $this->message = $msg;
    }

    function getMessage(){
        return $this->message;
    }

    /**
    * get latest refresh_token & access_token
    * @return array
    */
    function getLatestTokens(){
        return $this->latestTokens;
    }

    function setCredentials($id, $secret){
        $this->id = $id;
        $this->secret = $secret;
    }

    function setRedirectUrl($url){
        $this->redirectUrl = $url;
    }


    function getAccessToken($code){
        $id  = $this->id;
        $secret  = $this->secret;
        $auth = base64_encode("{$id}:{$secret}");
        $url = 'https://api.fitbit.com/oauth2/token';
        $headers = array(
          "Authorization: Basic {$auth}",
          "Content-Type: application/x-www-form-urlencoded",
        );
        $postData = array(
          'code' => $code,
          'client_id' => $this->id,
          'client_secret' => $this->secret,
          'grant_type' => 'authorization_code',
          'redirect_uri' => $this->redirectUrl
        );

        $response = $this->curl_request($url, $postData, $headers);
        $response = json_decode($response, true);
        if(!$this->isResponseOk($response)){
            return false;
        }
        $tokens = array(
          'access_token' => $response['access_token'],
          'refresh_token' => $response['refresh_token']
        );
        $this->latestTokens = $tokens;
        return $tokens;
    }


    function refreshToken($refreshToken){
        $id  = $this->id;
        $secret  = $this->secret;
        $auth = base64_encode("{$id}:{$secret}");
        $url = 'https://api.fitbit.com/oauth2/token';
        $headers = array(
          "Authorization: Basic {$auth}",
          "Content-Type: application/x-www-form-urlencoded",
        );
        $postData = array(
          'refresh_token' => $refreshToken,
          'client_id' => $this->id,
          'grant_type' => 'refresh_token'
        );
        $response = $this->curl_request($url, $postData, $headers);
        $response = json_decode($response, true);
        if(!$this->isResponseOk($response)){
          return false;
        }
        $tokens = array(
          'access_token' => $response['access_token'],
          'refresh_token' => $response['refresh_token'],
          'expires_in' => $response['expires_in']
        );
        $this->latestTokens = $tokens;
        return $tokens;
    }


    function getProfile($accessToken){
        $url = "https://api.fitbit.com/1/user/-/profile.json";
        $headers = array(
          "Authorization: Bearer $accessToken"
        );
        $response = $this->curl_request($url, array(), $headers);
        $response = json_decode($response, true);
        if(!$this->isResponseOk($response)){
          return false;
        }
        return $response;
    }


    /**
     * adding subscription to specific user
     * @param string $accessToken
     * @param string $userId fitbit user ids
     * @param array $collections resources to subscribe user
     * @return array
     */
    function addSubscriptions($accessToken, $userId, $collections){
        $status = array();
        foreach ($collections as $collection) {
            $subscriptionId = $this->id.'-'.$userId.'-'.$collection;
            $url = "https://api.fitbit.com/1/user/-/$collection/apiSubscriptions/$subscriptionId.json";
            $headers = [
            "Authorization: Bearer $accessToken"
            ];
            $response = $this->curl_request($url, array(), $headers);
            $response = json_decode($response, true);
            $status[$collection] =  $this->isResponseOk($response) ? true : false;
        }
        return $status;
    }

    function deleteSubscription($accessToken, $userId, $type){
        $subscriptionId = $this->id.'-'.$userId.'-'.$type;
        $url = "https://api.fitbit.com/1/user/-/$type/apiSubscriptions/$subscriptionId.json";
        $headers = array(
          "Authorization: Bearer $accessToken"
        );
        $response = $this->curl_request($url, array(), $headers, 'delete');
        $response = json_decode($response, true);
        return $this->isResponseOk($response) ? true : false;
    }


    function listSubscriptions($accessToken){
        $url = "https://api.fitbit.com/1/user/-/apiSubscriptions.json";
        $headers = array(
            "Authorization: Bearer $accessToken"
        );
        $response = $this->curl_request($url, array(), $headers, 'get');
        $response = json_decode($response, true);
        if(!$this->isResponseOk($response)){
            return false;
        }
        return $response;
    }

    /**
    * check curl response status
    * @param  array  $response
    * @return boolean
    */
    function isResponseOk($response){
        if(isset($response['errors'])){
            $this->setMessage($response['errors']);
            return false;
        }
        return true;
    }


    function getSubscriptionUpdatedData($accessToken, $ownerId, $date){
        $url = "https://api.fitbit.com/1/user/$ownerId/activities/date/$date.json";
        $headers = array(
          "Authorization: Bearer $accessToken"
        );
        $response = $this->curl_request($url, array(), $headers);
        $response = json_decode($response, true);
        if(!$this->isResponseOk($response)){
          return false;
        }
        return $response;
    }


    function validateToken($accessToken){
        $response = $this->getProfile($accessToken);
        return $response;
    }


    function curl_request($url, $postData, $headers, $method="post"){
        $posts = http_build_query($postData);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if($method == 'post'){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$posts);
        }
        else if($method == 'get'){
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }else{
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        }
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $response = $response ? $response: curl_error($ch);
        curl_close ($ch);
        return $response;
    }


}







 ?>
