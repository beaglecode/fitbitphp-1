<?php namespace beaglecode\fitbit;
use \Exception;

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
        'refresh_token' => '',
        'expires' => '',
        'user_id' => ''
    );
    private $subscriptions = array();

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

    function resetTokens() {
        $this->latestTokens = array(
            'access_token' => '',
            'refresh_token' => '',
            'expires' => '',
            'user_id' => ''
        );
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
          'refresh_token' => $response['refresh_token'],
          'expires' => date('Y-m-d H:i:s', strtotime('+'.$response['expires_in'].' seconds')),
          'user_id' => $response['user_id']
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
            throw new FitBitException('401', $response['errors'][0]['errorType'], $response['errors'][0]['message']);
        }
        $tokens = array(
          'access_token' => $response['access_token'],
          'refresh_token' => $response['refresh_token'],
          'expires' => date('Y-m-d H:i:s', strtotime('+'.$response['expires_in'].' seconds')),
          'user_id' => $response['user_id']
        );
        $this->latestTokens = $tokens;
        $this->updateSubscriptions();
        return $tokens;
    }


    function getProfile($accessToken){
        $url = "https://api.fitbit.com/1/user/-/profile.json";
        $headers = array(
          "Authorization: Bearer $accessToken"
        );
        $rawResponse = $this->curl_request($url, array(), $headers, "get");
        $response = json_decode($rawResponse, true);
        if(!$this->isResponseOk($response)){
          throw new FitBitException('401', $response['errors'][0]['errorType'], $response['errors'][0]['message']);
        }
        return $response;
    }

    function updateSubscriptions() {
        if ($this->latestTokens['access_token']) {
            $this->subscriptions = $this->listSubscriptions($this->latestTokens['access_token']);
        }
    }


    /**
     * adding subscription to specific user
     * @param string $accessToken
     * @param string $userId fitbit user ids
     * @param string $subscriberId an Id for fitBit to associate your user with
     * @param array $collections resources to subscribe user
     * @return array
     */
    function addSubscriptions($accessToken, $userId, $subscriberId, $collections){
        $status = array();
        foreach ($collections as $collection) {
            $url = "https://api.fitbit.com/1/user/-/$collection/apiSubscriptions/$subscriberId.json";
            $headers = array(
                "Authorization: Bearer $accessToken"
            );
            $response = $this->curl_request($url, array('id'=>$subscriberId), $headers, "POST");
            $response = json_decode($response, true);
            $status[$collection] =  $this->isResponseOk($response) ? true : false;
        }
        $this->updateSubscriptions();
        return $status;
    }

    function deleteSubscription($accessToken, $userId, $type, $subscriptionId=null){
        $subscriptionId = $subscriptionId ?: $this->id.'-'.$userId.'-'.$type;
        $url = "https://api.fitbit.com/1/user/-/$type/apiSubscriptions/$subscriptionId.json";
        $headers = array(
          "Authorization: Bearer $accessToken"
        );
        $response = $this->curl_request($url, array(), $headers, 'delete');
        $response = json_decode($response, true);
        $this->updateSubscriptions();
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


    function getSubscriptionUpdatedData($subscriptionType, $accessToken, $ownerId, $date){
        $url = "https://api.fitbit.com/1/user/$ownerId/$subscriptionType/date/$date.json";
        $headers = array(
          "Authorization: Bearer $accessToken"
        );
        $response = $this->curl_request($url, array(), $headers, "GET");
        $response = json_decode($response, true);
        if(!$this->isResponseOk($response)){
            throw new FitBitException('200', $response['errors'][0]['errorType'], $response['errors'][0]['message']);
        }
        return $response;
    }


    public function getActivities($accessToken, $ownerId, $date) {
        return $this->getSubscriptionUpdatedData('activities', $accessToken, $ownerId, $date);
    }

    public function getSleep($accessToken, $ownerId, $date) {
        return $this->getSubscriptionUpdatedData('sleep', $accessToken, $ownerId, $date);
    }

    public function getWeight($accessToken, $ownerId, $date) {
        return $this->getSubscriptionUpdatedData('body/weight', $accessToken, $ownerId, $date.'/1d');
    }

    function validateToken($accessToken){
        try {
            $response = $this->getProfile($accessToken);
            return $response;
        } catch (FitBitException $e) {
            return false;
        }
    }

    public function getTimeSeries($type, $baseDate, $toPeriod) {

        switch ($type) {
            case 'caloriesIn':
                $path = 'foods/log/caloriesIn';
                break;
            case 'water':
                $path = 'foods/log/water';
                break;

            case 'caloriesOut':
                $path = 'activities/calories';
                break;
            case 'steps':
                $path = 'activities/steps';
                break;
            case 'distance':
                $path = 'activities/distance';
                break;
            case 'floors':
                $path = 'activities/floors';
                break;
            case 'elevation':
                $path = 'activities/elevation';
                break;
            case 'minutesSedentary':
                $path = 'activities/minutesSedentary';
                break;
            case 'minutesLightlyActive':
                $path = 'activities/minutesLightlyActive';
                break;
            case 'minutesFairlyActive':
                $path = 'activities/minutesFairlyActive';
                break;
            case 'minutesVeryActive':
                $path = 'activities/minutesVeryActive';
                break;
            case 'activeScore':
                $path = 'activities/activeScore';
                break;
            case 'activityCalories':
                $path = 'activities/activityCalories';
                break;

            case 'tracker_caloriesOut':
                $path = 'activities/tracker/calories';
                break;
            case 'tracker_steps':
                $path = 'activities/tracker/steps';
                break;
            case 'tracker_distance':
                $path = 'activities/tracker/distance';
                break;
            case 'tracker_floors':
                $path = 'activities/tracker/floors';
                break;
            case 'tracker_elevation':
                $path = 'activities/tracker/elevation';
                break;
            case 'tracker_activeScore':
                $path = 'activities/tracker/activeScore';
                break;

            case 'startTime':
                $path = 'sleep/startTime';
                break;
            case 'timeInBed':
                $path = 'sleep/timeInBed';
                break;
            case 'minutesAsleep':
                $path = 'sleep/minutesAsleep';
                break;
            case 'awakeningsCount':
                $path = 'sleep/awakeningsCount';
                break;
            case 'minutesAwake':
                $path = 'sleep/minutesAwake';
                break;
            case 'minutesToFallAsleep':
                $path = 'sleep/minutesToFallAsleep';
                break;
            case 'minutesAfterWakeup':
                $path = 'sleep/minutesAfterWakeup';
                break;
            case 'efficiency':
                $path = 'sleep/efficiency';
                break;

            case 'weight':
                $path = 'body/weight';
                break;
            case 'bmi':
                $path = 'body/bmi';
                break;
            case 'fat':
                $path = 'body/fat';
                break;
            default:
                return false;
        }

        $userId = $this->latestTokens['user_id'];
        $accessToken = $this->latestTokens['access_token'];
        $date = $baseDate . ($toPeriod ? '/'.$toPeriod : '');
        return $this->getSubscriptionUpdatedData($path, $accessToken, $userId, $date);
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

/**
 * Fitbit API communication exception
 *
 */
class FitBitException extends Exception {
    public $fbMessage = '';
    public $httpcode;

    public function __construct($code, $fbMessage = null, $message = null) {

        $this->fbMessage = $fbMessage;
        $this->httpcode = $code;

        if (isset($fbMessage) && !isset($message))
            $message = $fbMessage;

        try {
            $code = (int)$code;
        } catch (Exception $E) {
            $code = 0;
        }

        parent::__construct($message, $code);
    }

}





 ?>
