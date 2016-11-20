<?php
/**
 * Created by PhpStorm.
 * User: GROOT
 * Date: 11/16 0016
 * Time: 13:59
 */

class Inup_SocialConnect_Helper_Name extends Mage_Core_Helper_Abstract {

    public function getName($name) {
        if(empty($name)) {
            return ['',''];
        }
        $name = explode(' ', $name, 2);
        if (count($name) > 1) {
            $firstName = $name[0];
            $lastName = $name[1];
        } else {
            $firstName = mb_substr($name[0], 0, 1);
            $lastName = mb_substr($name[0], 1);
        }
        return [$firstName, $lastName];
    }
}