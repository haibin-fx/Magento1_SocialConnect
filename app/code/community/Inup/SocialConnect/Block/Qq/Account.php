<?php
/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/8/15
 * Time: 10:13
 */

class Inup_SocialConnect_Block_Qq_Account extends Mage_Core_Block_Template
{
    protected $client = null;
    protected $userInfo = null;

    protected function _construct() {
        parent::_construct();

        $this->client = Mage::getSingleton('inup_socialconnect/qq_oauth_client');
        if(!($this->client->isEnabled())) {
            return;
        }

        $this->userInfo = Mage::registry('inup_socialconnect_qq_userinfo');

        $this->setTemplate('inup/socialconnect/qq/account.phtml');

    }

    protected function _hasData()
    {
        return $this->userInfo->hasData();
    }


    protected function _getqqId()
    {
        return $this->userInfo->getId();
    }

    protected function _getStatus()
    {
        return '<a href="'.sprintf('https://qq.com/%s', $this->userInfo->getScreenName()).'" target="_blank">'.
        $this->escapeHtml($this->userInfo->getScreenName()).'</a>';
    }

    protected function _getPicture()
    {
        if($this->userInfo->getProfileImageUrl()) {
            return Mage::helper('inup_socialconnect/qq')
                ->getProperDimensionsPictureUrl($this->userInfo->getId(),
                    $this->userInfo->getProfileImageUrl());
        }

        return null;
    }

    protected function _getName()
    {
        return $this->userInfo->getName();
    }

}