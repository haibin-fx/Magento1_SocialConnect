<?php
/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/8/16
 * Time: 13:03
 */
class Inup_SocialConnect_Helper_Wechat extends Mage_Core_Helper_Abstract
{
    public function getName($name) {
        return Mage::helper('inup_socialconnect/name')->getName($name);
    }

    public function disconnect(Mage_Customer_Model_Customer $customer)
    {
        Mage::getSingleton('customer/session')
            ->unsInupSocialconnectWechatUserinfo();

        $pictureFilename = Mage::getBaseDir(Mage_Core_Model_Store::URL_TYPE_MEDIA)
            . DS
            . 'inup'
            . DS
            . 'socialconnect'
            . DS
            . 'wechat'
            . DS
            . $customer->getInupSocialconnectCid();

        if (file_exists($pictureFilename)) {
            @unlink($pictureFilename);
        }

        $customer->setInupSocialconnectCid(null)
            ->setInupSocialconnectCtoken(null)
            ->save();
    }

    public function connectByWechatId(
        Mage_Customer_Model_Customer $customer,
        $openid,
        $token,
        $name = null,
        $emit_event = true)
    {

        $parseName = $this->getName($name);
        $customer->setFirstname($parseName[0])
            ->setLastname($parseName[1]);

        $customer->setInupSocialconnectCid($openid)
            ->setInupSocialconnectCtoken($token)
            ->save();

        if($emit_event) Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
    }

    public function connectByCreatingAccount(
        $email,
        $name,
        $openid,
        $token,
        $generate_password = true)
    {
        $customer = Mage::getModel('customer/customer');

        $parseName = $this->getName($name);

        $customer->setEmail($email)
            ->setFirstname($parseName[0])
            ->setLastname($parseName[1])
            ->setInupSocialconnectCid($openid)
            ->setInupSocialconnectCtoken($token);

        if($generate_password) {
            $customer->setPassword($customer->generatePassword(10));
            $customer->save();
        }
        $customer->setConfirmation(null);
        $customer->save();

        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);

    }

    public function loginByCustomer(Mage_Customer_Model_Customer $customer)
    {
        if ($customer->getConfirmation()) {
            $customer->setConfirmation(null);
            $customer->save();
        }

        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
    }

    public function getCustomersByWechatId($openid)
    {
        $customer = Mage::getModel('customer/customer');

        $collection = $customer->getCollection()
            ->addAttributeToSelect('inup_socialconnect_ctoken')
            ->addAttributeToFilter('inup_socialconnect_cid', $openid)
            ->setPageSize(1);

        if ($customer->getSharingConfig()->isWebsiteScope()) {
            $collection->addAttributeToFilter(
                'website_id',
                Mage::app()->getWebsite()->getId()
            );
        }

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $collection->addFieldToFilter(
                'entity_id',
                array('neq' => Mage::getSingleton('customer/session')->getCustomerId())
            );
        }

        return $collection;
    }

    public function getCustomersByEmail($email)
    {
        $customer = Mage::getModel('customer/customer');

        $collection = $customer->getCollection()
            ->addFieldToFilter('email', $email)
            ->setPageSize(1);

        if ($customer->getSharingConfig()->isWebsiteScope()) {
            $collection->addAttributeToFilter(
                'website_id',
                Mage::app()->getWebsite()->getId()
            );
        }

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $collection->addFieldToFilter(
                'entity_id',
                array('neq' => Mage::getSingleton('customer/session')->getCustomerId())
            );
        }

        return $collection;
    }

    public function getProperDimensionsPictureUrl($openid, $pictureUrl)
    {
        $pictureUrl = str_replace('_normal', '', $pictureUrl);

        $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)
            . 'inup'
            . '/'
            . 'socialconnect'
            . '/'
            . 'wechat'
            . '/'
            . $openid;

        $filename = Mage::getBaseDir(Mage_Core_Model_Store::URL_TYPE_MEDIA)
            . DS
            . 'inup'
            . DS
            . 'socialconnect'
            . DS
            . 'wechat'
            . DS
            . $openid;

        $directory = dirname($filename);

        if (!file_exists($directory) || !is_dir($directory)) {
            if (!@mkdir($directory, 0777, true))
                return null;
        }

        if (!file_exists($filename) ||
            (file_exists($filename) && (time() - filemtime($filename) >= 3600))
        ) {
            $client = new Zend_Http_Client($pictureUrl);
            $client->setStream();
            $response = $client->request('GET');
            stream_copy_to_stream($response->getStream(), fopen($filename, 'w'));

            $imageObj = new Varien_Image($filename);
            $imageObj->constrainOnly(true);
            $imageObj->keepAspectRatio(true);
            $imageObj->keepFrame(false);
            $imageObj->resize(150, 150);
            $imageObj->save($filename);
        }

        return $url;
    }

}