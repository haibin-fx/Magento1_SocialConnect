<?php
/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/8/15
 * Time: 10:13
 */
class Inup_SocialConnect_QqController extends Inup_SocialConnect_Controller_Abstract
{

    protected function _disconnectCallback(Mage_Customer_Model_Customer $customer) {
        Mage::helper('inup_socialconnect/qq')->disconnect($customer);

        Mage::getSingleton('core/session')
            ->addSuccess(
                $this->__('You have successfully disconnected your QQ account from our store account.')
            );
    }

    protected function _connectCallback() {
        $errorCode = $this->getRequest()->getParam('error');
        $code = $this->getRequest()->getParam('code');
        $state = $this->getRequest()->getParam('state');
        if(!($errorCode || $code) && !$state) {
            // Direct route access - deny
            return $this;
        }

        if(!$state || $state != Mage::getSingleton('core/session')->getQqCsrf()) {
            return $this;
        }

        if($errorCode) {
            if($errorCode === 'access_denied') {
                Mage::getSingleton('core/session')
                    ->addNotice(
                        $this->__('QQ Connect process aborted.')
                    );

                return $this;
            }

            throw new Exception(
                sprintf(
                    $this->__('Sorry, "%s" error occured. Please try again.'),
                    $errorCode
                )
            );
        }

        if ($code) {

            $info = Mage::getModel('inup_socialconnect/qq_info')->load();

            $token = $info->getClient()->getAccessToken();

            $customersByQqId = Mage::helper('inup_socialconnect/qq')
                ->getCustomersByQqId($info->getId());

            if(Mage::getSingleton('customer/session')->isLoggedIn()) {
                // Logged in user
                if($customersByQqId->getSize()) {
                    Mage::getSingleton('core/session')
                        ->addNotice(
                            $this->__('Your QQ account is already connected to one of our store accounts.')
                        );

                    return $this;
                }

                // Connect from account dashboard - attach
                $customer = Mage::getSingleton('customer/session')->getCustomer();

                Mage::helper('inup_socialconnect/qq')->connectByQqId(
                    $customer,
                    $info->getId(),
                    $token
                );

                Mage::getSingleton('core/session')->addSuccess(
                    $this->__('Your QQ account is now connected to your store account. You can now login using our QQ Login button or using store account credentials you will receive to your email address.')
                );

                return $this;
            }

            if($customersByQqId->getSize()) {
                // Existing connected user - login
                $customer = $customersByQqId->getFirstItem();

                Mage::helper('inup_socialconnect/qq')->loginByCustomer($customer);

                Mage::getSingleton('core/session')
                    ->addSuccess(
                        $this->__('You have successfully logged in using your QQ account.')
                    );

                return $this;
            }

            $customersByEmail = Mage::helper('inup_socialconnect/qq')
                ->getCustomersByEmail($info->getEmailAddress());

            if($customersByEmail->getSize()) {
                // Email account already exists - attach, login
                $customer = $customersByEmail->getFirstItem();

                Mage::helper('inup_socialconnect/qq')->connectByQqId(
                    $customer,
                    $info->getId(),
                    $token
                );

                Mage::getSingleton('core/session')->addSuccess(
                    $this->__('We have discovered you already have an account at our store. Your QQ account is now connected to your store account.')
                );

                return $this;
            }

            // New connection - create, attach, login
            $firstName = $info->getFirstName();
            if(empty($firstName)) {
                throw new Exception(
                    $this->__('Sorry, could not retrieve your QQ first name. Please try again.')
                );
            }

            $lastName = $info->getLastName();
            if(empty($lastName)) {
                throw new Exception(
                    $this->__('Sorry, could not retrieve your QQ last name. Please try again.')
                );
            }

            Mage::helper('inup_socialconnect/qq')->connectByCreatingAccount(
                $info->getEmailAddress(),
                $info->getFirstName(),
                $info->getLastName(),
                $info->getId(),
                $token
            );

            Mage::getSingleton('core/session')->addSuccess(
                $this->__('Your QQ account is now connected to your new user account at our store. Now you can login using our QQ Login button.')
            );
        }
    }

}