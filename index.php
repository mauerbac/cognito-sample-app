<!doctype html>
  <head>
    <title>Cognito App</title>

<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
    <style>
        .tb {
            display: table;
            max-width:80%;
            margin: 10px auto;
        }
        .tbr {
            display: table-row;
        }
        .tbc {
            display: table-cell;
            vertical-align: middle;
        }
        .tb img {
            height: 140px;
            max-width:100%;
        }
    </style>
  </head>
  <body>

<?php

//Facebook Requirements
define('FACEBOOK_SDK_V4_SRC_DIR', 'fb/src/Facebook/');
require __DIR__ . '/fb/autoload.php';

use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequest;
use Facebook\GraphUser;
use Facebook\FacebookRequestException;

//AWS requirements
require "aws.phar";
use Aws\CognitoIdentity\CognitoIdentityClient;
use Aws\Sts\StsClient;
use Aws\S3\S3Client;

session_start();

$accessToken=false;

// Initialize the SDK
FacebookSession::setDefaultApplication('App Id ', 'App Secret');

$helper = new FacebookRedirectLoginHelper('http://mauerbaccogapp-env.elasticbeanstalk.com/' );

// Check if existing session exists
if ( isset( $_SESSION ) && isset( $_SESSION['fb_token'] ) ) {
  // Create new session from saved access_token
  $session = new FacebookSession( $_SESSION['fb_token'] );

    // Validate the access_token to make sure it's still valid
    try {
      if ( ! $session->validate() ) {
        $session = null;
      }
    } catch ( Exception $e ) {
      // Catch any exceptions
      $session = null;
    }
} else {
  // No session exists
  try {
    $session = $helper->getSessionFromRedirect();
  } catch( FacebookRequestException $ex ) {

    // When Facebook returns an error
  } catch( Exception $ex ) {

    // When validation fails or other local issues
    echo $ex->message;
  }
}

// Check if a session exists
if ( isset( $session ) ) {

  // Save the session
  $_SESSION['fb_token'] = $session->getToken();

  // Create session using saved token or the new one we generated at login
  $session = new FacebookSession( $session->getToken() );

  // Create the logout URL (logout page should destroy the session)
  //$logout_url= array( 'next' => 'http://mauerbaccogapp-env.elasticbeanstalk.com/logout.php' );
 // $logoutURL = $helper->getLogoutUrl($logout_url );

  $accessToken = $session->getAccessToken();



    //get profile pic 
   try {
    
    $user_profile_prof = (new FacebookRequest($session, 'GET', '/me/picture',array (
    'redirect' => false,
    'height' => '800',
    'type' => 'normal',
    'width' => '800',
  )))->execute()->getGraphObject(GraphUser::className());

    $user_pic_url= $user_profile_prof->getProperty('url'); 


  } catch(FacebookRequestException $e) {

    echo "Exception occured, code: " . $e->getCode();
    echo " with message: " . $e->getMessage();

  }   

   //get profile information  
   try {
    
    $user_profile_full = (new FacebookRequest($session, 'GET', '/me',array (
    'redirect' => false,
    'height' => '800',
    'type' => 'normal',
    'width' => '800',
  )))->execute()->getGraphObject(GraphUser::className());

    $user_name= $user_profile_full->getProperty('name'); 


  } catch(FacebookRequestException $e) {

    echo "Exception occured, code: " . $e->getCode();
    echo " with message: " . $e->getMessage();

  }   


} else {
  // No session

  // Get login URL
  $loginUrl = $helper->getLoginUrl();
?>
   <div class="container">
 <div class="jumbotron">
      <center>
        <h1>Profile Pic Flash</h1> <br><br>
        <p class="lead">Simply store your Facebook profile picture on AWS S3</p>
      </center>
       <center> <a class="btn btn-lg btn-success" href="<?php echo $loginUrl; ?>" role="button">Login w/ FB</a></center>
        <h3>App details: </h3>
          <ul>
              <li>This app uses Facebook OAuth to request a user’s profile picture and then leverages AWS Cognito to gain temporary credentials to PUT the picture in S3. </li>
              <li>Using Facebook, as a third-party identity provider, we can authenticate users and create Cognito identities.</li>
              <li>The Cognito identity will provide the user with temporary AWS credentials that will allow the user to make calls to AWS services.  In this example, the user is able to make PUTs and GETS to their specific directory in a S3 bucket.</li>
              <li> More Details: <a href="http://mobile.awsblog.com/post/TxBVEDL5Z8JKAC/Use-Amazon-Cognito-in-your-website-for-simple-AWS-authentication">Getting Started</a>. </li>
              <li>Github Repo -> *Coming*</li>
              <li>Extensions: Integrate with AWS Lambda to modify the photo upon PUT in S3.</li>
          </ul>
        </div>

        <div class="row">
                    <div class="tb">
                        <div class="tbr">
                            <div class="tbc"><img src="awslogo.jpg" width="200px" height="220px" class="img-rounded img-responsive"></div>
                            <br>
                            <div class="tbc"><img src="cognito.png" class="img-rounded img-responsive"></div>
                            <br>
                            <div class="tbc"><img src="AmazonS3.png" class="img-rounded img-responsive"></div>
                        </div>
                        <div class="tbr">
                            <div class="tbc"><p class="text-center">AWS</div>
                            <br>
                            <div class="tbc"><p class="text-center">Amazon Cognito</p></div>
                            <br>
                            <div class="tbc"><p class="text-center">Amazon S3</p></div>
                        </div>
                    </div>
        </div>
    </div>





<?php

}


if($accessToken){
 
// initialize a Cognito identity client using the factory
$identityClient = CognitoIdentityClient::factory(array(
    'region'  => 'us-east-1'
));
 
// call the GetId API with the required parameters
$idResp = $identityClient->getId(array(
      'AccountId' => 'AWS Account ID',
      'IdentityPoolId' => 'Cognito Idenity Pool ID',
      'Logins' => array('graph.facebook.com' => (string)$accessToken),
    )
  );
 
// retrieve the identity id from the response data structure
$identityId = $idResp["IdentityId"];


// execute the getOpenIdToken call with the identityId
// retrieved from the previous call or cached
$tokenResp = $identityClient->getOpenIdToken(array(
    'IdentityId' => $identityId,
    'Logins' => array('graph.facebook.com' => (string)$accessToken),
));
 
// read the OpenID token from the response
$token = $tokenResp["Token"];

// create a new STS client
$stsClient = StsClient::factory(array(
    'region'  => 'us-east-1'
));
 
// run the AssumeRoleWithWebIdentity request with the IAM role
// for your user and the OpenID token retrieved from the previous
// API call
$stsResp = $stsClient->assumeRoleWithWebIdentity(array(
    'RoleArn' => 'arn:aws:iam::xxxxxx:role/Cognito_fbPhotosAuth_Role_s3',
    'RoleSessionName' => 'AppTestSession', // you need to give the session a name
    'WebIdentityToken' => $token
));
 


$client = S3Client::factory(array(
    'key'    => $stsResp['Credentials']['AccessKeyId'],
    'secret' => $stsResp['Credentials']['SecretAccessKey'],
    'token'  => $stsResp['Credentials']['SessionToken'],
    'region' => 'us-east-1'
));


copy($user_pic_url, '/tmp/file.jpg');

$result = $client->putObject(array(
    'Bucket' => "mauerbac-cog/$identityId",
    'Key'    => 'profilePic.jpg',
    'SourceFile'   => "/tmp/file.jpg",
    'ContentType' => 'image/jpg',
));


//$s3URL= $result['ObjectURL'];
$s3URL = $client->getObjectUrl("mauerbac-cog", $identityId.'/profilePic.jpg', '+10 minutes');
echo "<br>";
echo "<div class='container'>";
echo "<h4>Hey, $user_name </h4>";
echo "<h4>Your facebook profile picture is now saved in S3. View it here: <a target='_blank' href=".$s3URL.">S3 Link </a> </h4>";
echo "<br><h5>Your temporary credentials only allow access to this directory: https://s3.amazonaws.com/mauerbac-cog/$identityId/";
echo "</div>";



}
?>

</body>
</html>


