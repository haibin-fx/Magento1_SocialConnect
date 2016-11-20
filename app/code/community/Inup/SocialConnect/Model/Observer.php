<?php
/**
 * Created by PhpStorm.
 * User: GROOT
 * Date: 11/14 0014
 * Time: 18:50
 */

class Inup_SocialConnect_Model_Observer {

    public function SessionInit(Varien_Event_Observer $observer) {
        $customer = $observer->getEvent()->getCustomer();

        if(!$customer) {
            return;
        }

        $session = Mage::getSingleton('customer/session');
        $type = $session->getSocialLoginType();
        $openid = $session->getSocialLoginOpenid();
        $name = $session->getSocialLoginInfoName();
        $token = $session->getSocialLoginToken();

        switch ($type) {
            case 'qq':
                Mage::helper('inup_socialconnect/qq')->connectByQqId(
                    $customer,
                    $openid,
                    $token,
                    $name,
                    false
                );
                break;
            case 'weibo':
                Mage::helper('inup_socialconnect/weibo')->connectByWeiboId(
                    $customer,
                    $openid,
                    $token,
                    $name,
                    false
                );
                break;
            case 'wechat':
                Mage::helper('inup_socialconnect/wechat')->connectByWechatId(
                    $customer,
                    $openid,
                    $token,
                    $name,
                    false
                );
                break;
            default:
                
        }
        
        if($type) {
            $session->setSocialLoginType(null);
            $session->setSocialLoginOpenid(null);
            $session->setSocialLoginInfoName(null);
            $session->setSocialLoginToken(null);
        }
    }

}
?>