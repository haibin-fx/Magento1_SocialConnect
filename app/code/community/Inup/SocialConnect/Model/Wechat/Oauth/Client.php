<?php

/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/8/15
 * Time: 10:13
 */
class Inup_SocialConnect_Model_Wechat_Oauth_Client
{
    const REDIRECT_URI_ROUTE = 'socialconnect/wechat/connect';
    const REQUEST_TOKEN_URI_ROUTE = 'socialconnect/wechat/request';

    const OAUTH_QE_URI = 'https://open.weixin.qq.com/connect/qrconnect';
    const OAUTH_WEB_URI = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    const OAUTH_TOKEN_URI = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    const OAUTH_SERVICE_URI = 'https://api.weixin.qq.com';

    const MODULE_PAYMENT_WEIXIN_CORP_ACCESS_TOKEN   = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?';
    const MODULE_PAYMENT_WEIXIN_OAUTH2_CORP_OPENID = 'https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo?';
    const MODULE_PAYMENT_WEIXIN_CORP_USERID_TO_OPENID = 'https://qyapi.weixin.qq.com/cgi-bin/user/convert_to_openid?';

    const XML_PATH_WEB_ENABLED = 'customer/inup_socialconnect_wechat_web/enabled';
    const XML_PATH_WEB_CLIENT_ID = 'customer/inup_socialconnect_wechat_web/client_id';
    const XML_PATH_WEB_CLIENT_SECRET = 'customer/inup_socialconnect_wechat_web/client_secret';
    const XML_PATH_QR_ENABLED = 'customer/inup_socialconnect_wechat_qr/enabled';
    const XML_PATH_QR_CLIENT_ID = 'customer/inup_socialconnect_wechat_qr/client_id';
    const XML_PATH_QR_CLIENT_SECRET = 'customer/inup_socialconnect_wechat_qr/client_secret';
    const XML_PATH_FORCE_LOGIN_WEB = 'customer/inup_socialconnect_wechat_web/force_login';
    const XML_PATH_FORCE_LOGIN_QR = 'customer/inup_socialconnect_wechat_qr/force_login';
    const XML_PATH_WEB_IS_CORP = 'customer/inup_socialconnect_wechat_web/is_corp';

    protected $clientId = null;
    protected $clientSecret = null;
    protected $redirectUri = null;
    protected $client = null;
    protected $token = null;

    protected $openid = null;
    protected $inside = false;

    public function __construct()
    {
        $this->inside = $inside = $this->isWechatInside();
        if (($this->isEnabled = $this->_isEnabled($inside))) {
            $this->clientId = $this->_getClientId($inside);
            $this->clientSecret = $this->_getClientSecret($inside);
            $this->redirectUri = Mage::getModel('core/url')->sessionUrlVar(
                Mage::getUrl(self::REDIRECT_URI_ROUTE)
            );
            $this->client = $this;
        }
    }

    public function isWechatInside() {
        $ua = Mage::helper('core/http')->getHttpUserAgent();
        return !!preg_match("/MicroMessenger/i",$ua);
    }

    public function isEnabled()
    {
        return (bool)$this->isEnabled;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    public function setAccessToken($token)
    {
        $this->token = $token;
    }

    public function getAccessToken($code = null)
    {
        if($this->isCorp()) {
            $access_token = $this->GetCorpAccessToken();
            $openid = $this->GetOpenidFromCorp($code, $access_token);
            $this->setOpenid($openid);
            return $access_token;
        }

        if ($this->token) {
            return $this->token;
        }
        $token = Mage::getSingleton('customer/session')->getInupSocialconnectWechatAccessToken();
        if ($token) {
            return $token;
        }

        $token = $this->fetchAccessToken($code);
        Mage::getSingleton('customer/session')->setInupSocialconnectWechatAccessToken($token);
        return $token;
    }

    public function fetchAccessToken($code)
    {
        $http = new Zend_Http_Client(self::OAUTH_TOKEN_URI);
        $http->setParameterGet('appid', $this->clientId);
        $http->setParameterGet('secret', $this->clientSecret);
        $http->setParameterGet('grant_type', 'authorization_code');
        $http->setParameterGet('code', $code);
        $response = $http->request(Zend_Http_Client::GET)->getBody();

        $res = json_decode($response);
        if (!isset($res->access_token)) {
            throw new Exception(
                Mage::helper('inup_socialconnect')
                    ->__('Unable to retrieve request token.' . $res->errmsg)
            );
        }
        $this->setAccessToken($res->access_token);
        $this->setOpenid($res->openid);
        return $this->token;
    }


    public function setOpenid($openid)
    {
        Mage::getSingleton('customer/session')->setInupSocialconnectWchatOpenId($openid);
        $this->openid = $openid;
    }

    public function getOpenid()
    {
        $this->openid = Mage::getSingleton('customer/session')->getInupSocialconnectWchatOpenId();
        return $this->openid;
    }

    public function createAuthUrl()
    {
        return Mage::getUrl(self::REQUEST_TOKEN_URI_ROUTE);
    }

    public function redirectToAuthorize()
    {
        $state = md5(time());
        Mage::getSingleton('customer/session')->setWechatAuthState($state);
        if($this->inside) {
            $url = vsprintf(self::OAUTH_WEB_URI.'?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_userinfo&state=%s#wechat_redirect',[
                $this->clientId,
                $this->redirectUri,
                $state
            ]);
        } else {
            $url = vsprintf(self::OAUTH_QE_URI.'?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_login&state=%s#wechat_redirect',[
                $this->clientId,
                urlencode($this->redirectUri),
                $state
            ]);
        }
        Mage::app()->getResponse()->setRedirect($url)->sendResponse();
    }

    public function api($endpoint, $method = 'GET', $params = array())
    {
        $url = self::OAUTH_SERVICE_URI . $endpoint;

        $response = $this->_httpRequest($url, strtoupper($method), $params);

        return $response;
    }

    protected function _httpRequest($url, $method = 'GET', $params = array())
    {
        $client = new Zend_Http_Client($url);

        switch ($method) {
            case 'GET':
                $client->setMethod(Zend_Http_Client::GET);
                $client->setParameterGet($params);
                break;
            case 'POST':
                $client->setMethod(Zend_Http_Client::POST);
                $client->setParameterPost($params);
                break;
            case 'DELETE':
                $client->setMethod(Zend_Http_Client::DELETE);
                break;
            default:
                throw new Exception(
                    Mage::helper('inup_socialconnect')
                        ->__('Required HTTP method is not supported.')
                );
        }

        $response = $client->request();

        Inup_SocialConnect_Helper_Data::log($response->getStatus() . ' - ' . $response->getBody());

        $decodedResponse = json_decode($response->getBody());

        if ($response->isError()) {
            $status = $response->getStatus();
            if (($status == 400 || $status == 401 || $status == 429)) {
                if (isset($decodedResponse->error->message)) {
                    $message = $decodedResponse->error->message;
                } else {
                    $message = Mage::helper('inup_socialconnect')
                        ->__('Unspecified OAuth error occurred.');
                }

                throw new Inup_SocialConnect_Model_Weibo_Oauth_Exception($message);
            } else {
                $message = sprintf(
                    Mage::helper('inup_socialconnect')
                        ->__('HTTP error %d occurred while issuing request.'),
                    $status
                );

                throw new Exception($message);
            }
        }

        return $decodedResponse;
    }

    protected function _isEnabled($inside = false)
    {
        return $this->_getStoreConfig($inside ? self::XML_PATH_WEB_ENABLED : self::XML_PATH_QR_ENABLED);
    }

    protected function _getClientId($inside = false)
    {
        return $this->_getStoreConfig($inside ? self::XML_PATH_WEB_CLIENT_ID : self::XML_PATH_QR_CLIENT_ID);
    }

    protected function _getClientSecret($inside = false)
    {
        return $this->_getStoreConfig($inside ? self::XML_PATH_WEB_CLIENT_SECRET : self::XML_PATH_QR_CLIENT_SECRET);
    }

    public function _getForceLogin($inside)
    {
        return $this->_getStoreConfig($inside ? self::XML_PATH_FORCE_LOGIN_WEB : self::XML_PATH_FORCE_LOGIN_QR);
    }

    public function IsCorp() {
        if(!$this->isWechatInside()) {
            return false;
        }
        return $this->_getStoreConfig(self::XML_PATH_WEB_IS_CORP) == 1;
    }

    protected function _getStoreConfig($xmlPath)
    {
        return Mage::getStoreConfig($xmlPath, Mage::app()->getStore()->getId());
    }




    /**
     * 获取企业号access_token
     * @return bool|string
     */
    public function GetCorpAccessToken() {
        $cache = Mage::getSingleton('core/cache');
        $cache_key = 'wx_corp_access_token';
        $access_token = $cache->load($cache_key);
        if(!empty($access_token)) {
            return $access_token;
        }

        $url = $this->createCorpAccessTokenUrl();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $res = curl_exec($ch);
        curl_close($ch);

        $access_token = '';

        if ($data = json_decode($res, true)) {
            if (isset($data['access_token'])) {
                $access_token = $data['access_token'];
            }
        }

        if (empty($access_token)) {
            return false;
        }

        $cache->save($access_token, $cache_key, array(), 3500 * 2);

        return $access_token;
    }

    /**
     * 获取企业号openid
     * @param $code
     */
    public function GetOpenidFromCorp($code, $access_token = null) {
        $access_token = $access_token == null ? $this->GetCorpAccessToken() : $access_token;

        $url = $this->createCorpOpenidUrl($access_token, $code);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $res = curl_exec($ch);
        curl_close($ch);


        $openid = '';

        if ($data = json_decode($res, true)) {
            if (isset($data['OpenId'])) {
                $openid = $data['OpenId'];
            }
            if (isset($data['UserId'])) {
                $openid = $this->corpUserIdToOpenid($access_token, $data['UserId']);
            }
        }

        if (empty($openid)) {
            return false;
        }

        return $openid;
    }

    /**
     * 企业号userid转换成openid接口
     * @param $access_token
     * @param $user_id
     */
    public function corpUserIdToOpenid($access_token, $user_id) {

        $url = $this->createCorpUseridToOpenidUrl($access_token);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'userid' => $user_id
        )));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));

        $res = curl_exec($ch);
        curl_close($ch);

        $openid = '';

        if ($data = json_decode($res, true)) {
            if (isset($data['openid'])) {
                $openid = $data['openid'];
            }
        }

        if (empty($openid)) {
            return false;
        }

        return $openid;

    }
    /**
     * 构造获取企业号access token链接
     * @return string
     */
    public function createCorpAccessTokenUrl() {
        $params['corpid']     = $this->getClientId();
        $params['corpsecret']    = $this->getClientSecret();

        $str = $this->toUrlParams($params, false);
        $url = self::MODULE_PAYMENT_WEIXIN_CORP_ACCESS_TOKEN . $str;

        return $url;
    }

    /**
     * 构造获取企业号openid链接
     * @param $access_token
     * @param $code
     * @return string
     */
    public function createCorpOpenidUrl($access_token, $code) {
        $params['access_token']     = $access_token;
        $params['code']    = $code;

        $str = $this->toUrlParams($params, false);
        $url = self::MODULE_PAYMENT_WEIXIN_OAUTH2_CORP_OPENID . $str;

        return $url;
    }

    /**
     * 构造企业号userid转openid链接
     * @param $access_token
     */
    public function createCorpUseridToOpenidUrl($access_token) {
        $params['access_token']     = $access_token;

        $str = $this->toUrlParams($params, false);
        $url = self::MODULE_PAYMENT_WEIXIN_CORP_USERID_TO_OPENID . $str;

        return $url;
    }


    public function toUrlParams($params, $url_encode = false)
    {
        $str = '';

        ksort($params);

        foreach ($params as $k => $v) {
            if ($url_encode) {
                $v = urlencode($v);
            }

            $str .= ($k . '=' . $v . '&');
        }

        if (strlen($str) > 0) {
            $str = substr($str, 0, strlen($str)-1);
        }

        return $str;
    }
}