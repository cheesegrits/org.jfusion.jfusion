<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaInt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Authentication class for the internal Joomla database
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_int
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_joomla_int extends JFusionAuth
{
	/**
	 * returns the name of this JFusion plugin
	 *
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'joomla_int';
	}

	/**
	 * Generates an encrypted password based on the userinfo passed to this function
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return string Returns generated password
	 */
	public function generateEncryptedPassword($userinfo)
	{
		if (substr($userinfo->password, 0, 4) == '$2y$') {
			// BCrypt passwords are always 60 characters, but it is possible that salt is appended although non standard.
			$password60 = substr($userinfo->password, 0, 60);

			if (JCrypt::hasStrongPasswordSupport()) {
				$testcrypt = password_verify($userinfo->password_clear, $password60);
			} else {
				$testcrypt = null;
			}
		} elseif (substr($userinfo->password, 0, 8) == '{SHA256}') {
			jimport('joomla.user.helper');
			// Check the password
			$parts	= explode(':', $userinfo->password);
			$crypt	= $parts[0];
			if (isset($parts[1])) {
				$salt	= $parts[1];
			} else {
				$salt = null;
			}

			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $salt, 'sha256', false);
		} else {
			jimport('joomla.user.helper');
			// Check the password
			$parts	= explode(':', $userinfo->password);
			$crypt	= $parts[0];
			if (isset($parts[1])) {
				$salt	= $parts[1];
			} else {
				$salt = null;
			}

			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $salt, 'md5-hex', false);
		}
		return $testcrypt;
	}

	/**
	 * used by framework to ensure a password test
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return boolean
	 */
	function checkPassword($userinfo) {
		return ($this->generateEncryptedPassword($userinfo) === true || $userinfo->password == $this->generateEncryptedPassword($userinfo));
	}
}
