# fitbit
Baisc wrapper for Fitbit([ dev.fitbit.com ]) API handles authorization, get and refresh tokens, get profile, subscribe, list subscriptions and delete subscriptions.
# Using
```
$fitbit = new Fitbit($appId, $appSecret, $redirectUrl);
```
# Available Features
generate an access token from authorization code
```
$profile = $fitbit->getAccessToken($authorization_code);
```
refresh an access token
```
$tokens = $fitbit->refreshToken($refreshToken);
```
 get user profile
 ```
$profile = $fitbit->getProfile($accessToken);
```
add subscriptions
```
// $collections is an array represents the collections eg. ['activities', 'sleep']
$status - $fitbit->addSubscriptions($accessToken, $ownerId, $collections);
```
delete specific subscription for user
```
$status = $fitbit->deleteSubscription($accessToken, $userId, $collection);
```
list user's subscriptions
```
$subscriptions = $fitbit->listSubscriptions($accessToken);
```
get subscription updated data
```
$data = $fitbit->getSubscriptionUpdatedData($accessToken, $ownerId, $date);
```
validate access token
```
$status = $fitbit->validateToken($accessToken);
```
if error occured you can get the error response by this method
```
$message = $fitbit->getMessage();
```

##### library under development.
