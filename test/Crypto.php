<?php

namespace Stateless;

class Crypto {
    /**
     * @brief Hash a string
     * @param string $str The string to hash
     */
    public static function hash($str) {
        return password_hash($str, PASSWORD_BCRYPT);
    }

    /**
     * @brief Verify a clear-text string against a hash
     * @param string $str The clear-text string
     * @param string $hash The hash to verify
     * @return boolean Returns if $str matches $hash
     */
    public static function verifyHash($str, $hash) {
        return password_verify($str, $hash);
    }

    /**
     * @brief Appends $salt to $str
     * @param string $str The string to salt
     * @param string $salt The salt to append
     * @param boolean Returns the salted string
     */
    public static function salt($str, $salt) {
        return $str . $salt;
    }

    /**
     * @brief Returns pepper generated by $uuid
     * @param mixed $uuid The user Id to generate the pepper from
     * @param mixed $pepperLength Length of the pepper to create
     * @return string Returns the generated pepper
     */
    public static function getPepper($uuid, $pepperLength) {
        $uuid = strval($uuid);
        $hash = sha1($uuid);
        $hash = substr($hash, strlen($hash) - $pepperLength);

        return substr($hash, 0, $pepperLength);
    }

    /**
     * @brief Prepends pepper to $str
     * @param string $str The string to pepper
     * @param mixed $uuid User Id to derive the pepper from
     * @param mixed $pepperLength Length of the pepper to create
     * @return string Returns the peppered string
     */
    public static function pepper($str, $uuid, $pepperLength) {
        return getPepper($uuid, $pepperLength) . $str;
    }

    /**
     * @brief Joins salt and pepper with the string
     * @param string $str The string to salt and pepper
     * @param mixed $uuid The user Id to derive the pepper from
     * @param string $salt String to use as a salt
     * @param integer $pepperLength Length of the pepper to generate
     * @param string Returns the string with salt and pepper
     */
    public static function spice($str, $uuid, $salt, $pepperLength) {
        return getPepper($uuid, $pepperLength) . salt($str, $salt);
    }

    /**
     * @brief Check if $str contains $salt
     * @param string $str The string to check
     * @param string $salt The salt to check for
     * @return boolean Returns if the salt is at the end
     */
    public static function checkSalt($str, $salt) {
        $strlen = strlen($str);
        $saltLen = strlen($salt);

        return (
            substr_compare($str, $salt, $strLen-$saltLen, $saltLen) === 0
        );
    }

    /**
     * @brief Check for pepper at the beginning of $str
     * @param string $str The string to check
     * @param integer $uuid The user Id to check for
     * @param integer $pepperLength The length of the pepper
     * @return boolean Returns if the pepper is at the beginning of $str
     */
    public static function checkPepper($str, $uuid, $pepperLength) {
        $pepper = pepper("", $uuid, $pepperLength);

        return (
            substr_compare(substr($str, 0, $pepperLength), $pepper, 0) === 0
        );
    }
    
    /**
     * @brief Check $str for spice (salt and pepper)
     * @param string $str The string to check
     * @param integer $uuid The user Id to validate
     * @param string $salt The salt to check for
     * @param integer $pepperLength The length of the pepper
     * @return boolean Returns if the salt and pepper are valid
     */
    public static function checkSpice($str, $uuid, $salt, $pepperLength) {
        return (
            checkSalt($str, $salt) &&
            checkPepper($str, $uuid, $pepperLength)
        );
    }

    /**
     * @brief Remove $salt from $str, if it exists
     * @param string $str The string to unsalt
     * @param string $salt The salt to remove from the string
     * @return string Returns the unsalted string
     */
    public static function unsalt($str, $salt) {
        return substr($str, 0, strlen($str) - strlen($salt));
    }

    /**
     * @brief Remove pepper from $str, if it exists
     * @param string $str The string to unpepper
     * @param integer $uuid The user Id to unpepper
     * @param integer $pepperLength The length of the pepper
     * @return string Returns the unpeppered string
     */
    public static function unpepper($str, $uuid, $pepperLength) {
        // Check if pepper exists
        if (checkPepper($str, $uuid, $pepperLength)) {

            // Return the unpeppered string
            return substr($str, $pepperLength);
        }
        else {

            // Return the string
            return $str;
        }
    }

    /**
     * @brief Remove $salt and pepper from $str, if it exists
     * @param string $str The string to unspice
     * @param integer $uuid The user Id to unpepper
     * @param string $salt The salt to remove
     * @param integer $pepperLength The length of the pepper to remove
     * @return string Returns the unspiced string
     */
    public static function unspice($str, $uuid, $salt, $pepperLength) {
        return unsalt(unpepper($str, $uuid, $pepperLength), $salt);
    }

    /**
     * @brief Returns a timestamp to be used with a nonce
     * @return integer Returns the timestamp
     */
    public static function nonceTime() {
        $t = new DateTime();
        return $t->getTimestamp();
    }

    /**
     * @brief Encrypt timestamps in nonces
     * @param string $str The timestamp to encrypt
     * @return mixed Returns the encrypted timestamp, or false on failure
     */
    public static function encryptTime($str) {
        $swaps = array('a', 'b', 'c', 'd', '$', 'f', 'g', 'h', '.', 'j');
        $strArray = str_split($str);
        $strLen = count($strArray);
        $str = '';

        for ($i = 0; $i < $strLen; $i++) {
            $n = intval($strArray[$i]);
            $str .= $swaps[$n];
        }

        return $str;
    }

    /**
     * @brief Decrypt timestamp
     * @param string $str The timestamp to decrypt
     * @return string Returns the decrypted timestamp
     */
    public static function decryptTime($str) {
        $swaps = array(
            'a' => '0', 'b' => '1', 'c' => '2', 'd' => '3',
            '$' => '4', 'f' => '5', 'g' => '6', 'h' => '7',
            '.' => '8', 'j' => '9'
        );

        $strArray = str_split($str);
        $strLen = count($strArray);
        $str = '';

        for ($i = 0; $i < $strLen; $i++) {
            $c = $strArray[$i];
            $str .= $swaps[$c];
        }

        return $str;
    }

    /**
     * @brief Creates a hashed nonce string
     * @param string $action The action being performed
     * @param integer $uuid The user Id to include in the nonce
     * @param integer $obid The object Id currently being edited
     * @param integer $ttl The amount of time from now until the nonce expires
     * @param string $salt The string the nonce was salted with
     * @param integer $pepperLength The length of the pepper
     * @return string Returns the hashed nonce
     */
    function nonce($action, $uuid, $obid, $ttl, $salt, $pepperLength) {
        $time = nonceTime() + $ttl;
        $time = encryptTime($time);
        $hash = hash($action . $uuid . $obid);

        return getPepper($uuid, $pepperLength) . $hash . $time . $salt;
    }

    /**
     * @brief Validates a nonce string
     * @param string $nonce The nonce to check
     * @param string $action The action being performed
     * @param integer $uuid The user Id to include in the nonce
     * @param integer $obid The object Id currently being edited
     * @param string $salt The string the nonce was salted with
     * @param integer $pepperLength The length of the pepper
     * @return boolean Returns if the nonce is valid
     */
    function validateNonce(
        $nonce,
        $action,
        $uuid,
        $obid,
        $ttl,
        $salt,
        $pepperLength
    ) {

        // Check for spice & remove
        if (!checkSpice($nonce, $uuid, $salt, $pepperLength)) {
            return false;
        }

        // Unspice the nonce
        $nonce = unspice($nonce, $uuid, $salt, $pepperLength);

        // Find the start of timestamp
        $startTime = strlen($nonce) - NONCE_TIME_LENGTH;

        // Check if there is even enough room for the timestamp in the nonce
        if ((strlen($nonce)) < $startTime + NONCE_TIME_LENGTH) {
            return false;
        }

        // Split off & decrypt the timestamp
        $nonceTime = substr($nonce, strlen($nonce) - NONCE_TIME_LENGTH);
        $nonceTime = decryptTime($nonceTime);
        $nonce = substr($nonce, 0, $startTime);

        // Check the action, uuid, & object id
        $verify = $action . $uuid . $obid;

        if (!password_verify($verify, $nonce)) {
            return false;
        }

        // Check the timestamp
        $timeNow = nonceTime();

        if ($timeNow > $nonceTime) {
            return false;
        }
        
        // All checks OK, return true
        return true;
    }

    /**
     * @brief Generates a hidden nonce field
     * @param string $name The key for this field in the form
     * @param string $nonce The nonce for the value of this form
     * @return string Returns the html markup of the hidden field
     */
    function getNonceField($key, $nonce) {
        return sprintf(
            "<input type=\"hidden\" name=\"%s\" value=\"%s\">",
            $key,
            $nonce
        );
    }
}