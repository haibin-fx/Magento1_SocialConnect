<?php
/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/8/15
 * Time: 10:13
 */

class Inup_SocialConnect_Block_Checkout extends Mage_Core_Block_Template
{
    protected $clientWeibo = null;
    protected $clientQq = null;

    protected $numEnabled = 0;
    protected $numShown = 0;

    protected function _construct() {
        parent::_construct();

        $this->clientWeibo = Mage::getSingleton('inup_socialconnect/weibo_oauth_client');
        $this->clientQq = Mage::getSingleton('inup_socialconnect/qq_oauth_client');

        if(!$this->_weiboEnabled() &&
            !$this->_qqEnabled()) {
            return;
        }
        
        if($this->_weiboEnabled()) {
            $this->numEnabled++;
        }

        if($this->_qqEnabled()) {
            $this->numEnabled++;
        }

        Mage::register('inup_socialconnect_button_text', $this->__('Continue'), true);

        $this->setTemplate('inup/socialconnect/checkout.phtml');
    }

    protected function _getColSet()
    {
        return 'col'.$this->numEnabled.'-set';
    }

    protected function _getCol()
    {
        return 'col-'.++$this->numShown;
    }

    protected function _weiboEnabled()
    {
        return $this->clientWeibo->isEnabled();
    }

    protected function _qqEnabled()
    {
        return false;
        return $this->clientQq->isEnabled();
    }

}
