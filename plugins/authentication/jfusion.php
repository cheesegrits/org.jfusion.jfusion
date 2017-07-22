<?php

/**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage Authentication
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Authentication\Authentication;
use JFusion\Factory;
use JFusion\User\Userinfo;

// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * Load the JFusion framework
 */
jimport('joomla.event.plugin');
require_once JPATH_ADMINISTRATOR . '/components/com_jfusion/import.php';
/**
 * JFusion Authentication class
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage Authentication
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class plgAuthenticationjfusion extends JPlugin
{
	var $name = 'jfusion';
	/**
	 * Constructor
	 *
	 * For php4 compatibility we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object &$subject The object to observe
	 * @param array  $config   An array that holds the plugin configuration
	 *
	 * @since 1.5
	 * @return void
	 */
	function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		//load the language
		$this->loadLanguage('com_jfusion', JPATH_BASE);
	}

	/**
	 * @param $credentials
	 * @param $options
	 * @param $response
	 *
	 * @return void
	 */
	function onUserAuthenticate($credentials, $options, &$response){
		jimport('joomla.user.helper');

		$mainframe = JFactory::getApplication();

		$Authentication = Authentication::getInstance();

		$info = new stdClass();

		$info->password_clear = $credentials['password'];
		if (isset($credentials['email'])) {
			$info->email = $credentials['email'];
		}
		if (isset($credentials['username'])) {
			$info->username = $credentials['username'];
		}

		$userinfo = new Userinfo(null);
		$userinfo->bind($info);

		$authResponce = null;
		if ($userinfo instanceof Userinfo) {
			$authResponce = $Authentication->authenticate($userinfo, $options);
		}

		if ($authResponce && $authResponce->status === Authentication::STATUS_SUCCESS) {
			$response->status = JAuthentication::STATUS_SUCCESS;
			$response->userinfo = $authResponce->userinfo;
		} else {
			if (!\JFusion\Factory::getStatus()->get('active.logincheck', false) && $mainframe->isAdmin()) {
				//Logging in via Joomla admin but JFusion failed so attempt the normal joomla behaviour
				$dispatcher = JEventDispatcher::getInstance();

				$db = JFactory::getDbo();
				$query = $db->getQuery(true)
					->select('folder, type, element AS name, params')
					->from('#__extensions')
					->where('element = ' . $db->quote('joomla'))
					->where('type =' . $db->quote('plugin'))
					->where('folder =' . $db->quote('authentication'));

				$plugin = $db->setQuery($query)->loadObject();
				$plugin->type = $plugin->folder;

				require_once JPATH_PLUGINS . '/authentication/joomla/joomla.php';

				$joomlaAuth = new plgAuthenticationJoomla($dispatcher, (array) ($plugin));
				$joomlaAuth->onUserAuthenticate($credentials, $options, $response);

				$authResponce->debugger->addDebug(JText::_('JOOMLA_AUTH_PLUGIN_USED_JFUSION_FAILED'));
			}

			if (isset($response->status) && $response->status != JAuthentication::STATUS_SUCCESS) {
				//no matching password found
				$response->status = JAuthentication::STATUS_FAILURE;
				$response->error_message = JText::_('FUSION_INVALID_PASSWORD');
			}
		}

		if (!\JFusion\Factory::getStatus()->get('active.logincheck', false)) {
			// Check the two factor authentication
			if ($response->status == JAuthentication::STATUS_SUCCESS)
			{
				$joomla = Factory::getUser('joomla_int');

				$joomlauser = new Userinfo(null);
				$joomlauser->username = $credentials['username'];

				$joomlauser = $joomla->getUser($joomlauser);

				require_once JPATH_ADMINISTRATOR . '/components/com_users/helpers/users.php';

				if (method_exists('UsersHelper', 'getTwoFactorMethods')) {
					$methods = UsersHelper::getTwoFactorMethods();

					if (count($methods) <= 1)
					{
						// No two factor authentication method is enabled
						return;
					}

					require_once JPATH_ADMINISTRATOR . '/components/com_users/models/user.php';

					$model = new UsersModelUser;

					// Load the user's OTP (one time password, a.k.a. two factor auth) configuration
					if (!array_key_exists('otp_config', $options))
					{
						$otpConfig = $model->getOtpConfig($joomlauser->userid);
						$options['otp_config'] = $otpConfig;
					}
					else
					{
						$otpConfig = $options['otp_config'];
					}

					// Check if the user has enabled two factor authentication
					if (empty($otpConfig->method) || ($otpConfig->method == 'none'))
					{
						// Warn the user if he's using a secret code but he has not
						// enabed two factor auth in his account.
						if (!empty($credentials['secretkey']))
						{
							try
							{
								$app = JFactory::getApplication();

								$this->loadLanguage();

								$app->enqueueMessage(JText::_('PLG_AUTH_JOOMLA_ERR_SECRET_CODE_WITHOUT_TFA'), 'warning');
							}
							catch (Exception $exc)
							{
								// This happens when we are in CLI mode. In this case
								// no warning is issued
								return;
							}
						}

						return;
					}

					// Load the Joomla! RAD layer
					if (!defined('FOF_INCLUDED'))
					{
						include_once JPATH_LIBRARIES . '/fof/include.php';
					}

					// Try to validate the OTP
					FOFPlatform::getInstance()->importPlugin('twofactorauth');

					$otpAuthReplies = FOFPlatform::getInstance()->runPlugins('onUserTwofactorAuthenticate', array($credentials, $options));

					$check = false;

					/**
					 * This looks like noob code but DO NOT TOUCH IT and do not convert
					 * to in_array(). During testing in_array() inexplicably returned
					 * null when the OTEP begins with a zero! o_O
					 */
					if (!empty($otpAuthReplies))
					{
						foreach ($otpAuthReplies as $authReply)
						{
							$check = $check || $authReply;
						}
					}

					// Fall back to one time emergency passwords
					if (!$check)
					{
						// Did the user use an OTEP instead?
						if (empty($otpConfig->otep))
						{
							if (empty($otpConfig->method) || ($otpConfig->method == 'none'))
							{
								// Two factor authentication is not enabled on this account.
								// Any string is assumed to be a valid OTEP.

								return;
							}
							else
							{
								/**
								 * Two factor authentication enabled and no OTEPs defined. The
								 * user has used them all up. Therefore anything he enters is
								 * an invalid OTEP.
								 */
								return;
							}
						}

						// Clean up the OTEP (remove dashes, spaces and other funny stuff
						// our beloved users may have unwittingly stuffed in it)
						$otep = $credentials['secretkey'];
						$otep = filter_var($otep, FILTER_SANITIZE_NUMBER_INT);
						$otep = str_replace('-', '', $otep);

						$check = false;

						// Did we find a valid OTEP?
						if (in_array($otep, $otpConfig->otep))
						{
							// Remove the OTEP from the array
							$otpConfig->otep = array_diff($otpConfig->otep, array($otep));

							$model->setOtpConfig($joomlauser->userid, $otpConfig);

							// Return true; the OTEP was a valid one
							$check = true;
						}
					}

					if (!$check)
					{
						$response->status = JAuthentication::STATUS_FAILURE;
						$response->error_message = JText::_('JGLOBAL_AUTH_INVALID_SECRETKEY');
					}
				}
			}
		}
	}
}
