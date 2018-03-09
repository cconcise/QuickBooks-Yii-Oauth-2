<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Facades\Invoice;
use yii\helpers\VarDumper;

class Quickbooks extends Component
{

    public $ClientID;
    public $ClientSecret;
    public $RedirectURI;
    public $scope;
    public $baseUrl;
    public $OAuth2LoginHelper;
    private $session;
    private $dataService;
    const SESSION_ACCESS_TOKEN_KEY = 'quickbooks_access_token_key';
    const SESSION_REFRESH_TOKEN_KEY = 'quickbooks_refresh_token_keuy';
    const SESSION_REALM_ID = 'quickbooks_realm_id';
    const SESSION_TOKEN_EXPIRE_TIME = 'quickbooks_token_expire_time';
    const ERROR_NO_ACCESS_TOKEN = 1;

    public function init()
    {
        $this->dataService = DataService::Configure(array(
            'auth_mode' => 'oauth2',
            'ClientID' => $this->ClientID,
            'ClientSecret' => $this->ClientSecret,
            'RedirectURI' => $this->RedirectURI,
            'scope' => $this->scope,
            'baseUrl' => $this->baseUrl
        ));

        $this->OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();

        parent::init();
        $this->session = Yii::$app->session;
        if ($this->session->isActive) {
            $this->session->open();
        }
    }

    // Returns false if no valid access token exists or else returns a ready to use instance of DataService
    public function connect()
    {

        if (!$this->getAccessTokenKey()) {
            return false;
        }

        else {
            $this->dataService = DataService::Configure(array(
                'auth_mode' => 'oauth2',
                'ClientID' => $this->ClientID,
                'ClientSecret' => $this->ClientSecret,
                'accessTokenKey' => $this->getAccessTokenKey(),
                'refreshTokenKey' => $this->getRefreshTokenKey(),
                'QBORealmID' => $this->getRealmId(),
                'baseUrl' => 'https://sandbox-quickbooks.api.intuit.com/'
            ));

            if(time() > $this->getTokenExpireTime()){
                $this->refreshToken();
                echo "refreshing token";
            }

            return $this->dataService;
        }
	}

    public function getAccessTokenKey()
    {
        return $this->session->get(self::SESSION_ACCESS_TOKEN_KEY);

    }

    public function getRefreshTokenKey()
    {
        return $this->session->get(self::SESSION_REFRESH_TOKEN_KEY);
    }

    public function getTokenExpireTime(){
        return $this->session->get(self::SESSION_TOKEN_EXPIRE_TIME);
    }

    public function getRealmID()
    {
        return $this->session->get(self::SESSION_REALM_ID);
    }

    public function redirectUrl()
    {
        $authorizationCodeUrl = $this->OAuth2LoginHelper->getAuthorizationCodeURL();
        return $authorizationCodeUrl;
    }

    // Called when QuickBooks hits the Oauth Redirect URL
    public function handleOauthCallback()
    {
        $request = Yii::$app->request;


        $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();

        $authorizationCode = $request->get('code');
        $realmId = $request->get('realmId');

        $accessTokenObj = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken($authorizationCode, $realmId);

        $this->persistAccessTokenObjToSession($accessTokenObj);
    }

    public function refreshToken()
    {

        $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();
        $refreshedAccessTokenObj = $OAuth2LoginHelper->refreshToken();
        $error = $OAuth2LoginHelper->getLastError();
        if($error){
            VarDumper::dump($error);
            return false;
        }else{
            //Refresh Token is called successfully
            $this->dataService->updateOAuth2Token($refreshedAccessTokenObj);
            $this->persistAccessTokenObjToSession($refreshedAccessTokenObj);
        }
    }

    public function persistAccessTokenObjToSession($accessTokenObj){
        $session = Yii::$app->session;
        $session->set(self::SESSION_ACCESS_TOKEN_KEY, $accessTokenObj->getAccessToken());
        $session->set(self::SESSION_REFRESH_TOKEN_KEY, $accessTokenObj->getRefreshToken());
        $session->set(self::SESSION_REALM_ID, $accessTokenObj->getRealmID());
        $expire = time() + 3400;
        $session->set(self::SESSION_TOKEN_EXPIRE_TIME, $expire);
    }
}

?>
