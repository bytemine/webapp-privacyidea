<?php

require "class.privacyideadata.settings.php";
if(!function_exists('radius_auth')) {
	require_once "Auth/auth_helper.php";
}

/**
 * PHP Class plugin PrivacyIDEA for two-factor authentication
 *
 * @class PluginPrivacyIDEA
 * @extends Plugin
 * @author Daniel Rauer, bytemine GmbH
 * @copyright 2016 bytemine GmbH
 * @license http://www.gnu.org/licenses/ GNU Affero General Public License
 * @link https://www.bytemine.net/
 */
class PluginPrivacyIDEA extends Plugin {

	/**
	 * Function initializes the Plugin and registers all hooks
	 */
	function init() {
		$this->registerHook('server.core.settings.init.before');
		$this->registerHook('server.index.load.main.before');
	}

	/**
	 * Function is executed when a hook is triggered by the PluginManager
	 *
	 * @param string $eventID the id of the triggered hook
	 * @param mixed $data object(s) related to the hook
	 */
	function execute($eventID, &$data) {
                $encryptionStore = EncryptionStore::getInstance();
                $username = $encryptionStore->get('username');
                $password = $encryptionStore->get('password');
		switch($eventID) {
                        case 'server.core.settings.init.before' :
			        $this->injectPluginSettings($data, has_yubikey_attribute($username));
				break;

			case 'server.index.load.main.before' : // don't use the logon trigger because we need the settings

				try {
					if (PLUGIN_PRIVACYIDEA_ALWAYS_ENABLED) {
						$GLOBALS["settings"]->set('zarafa/v1/plugins/privacyidea/enable', true);
						$GLOBALS["settings"]->saveSettings();
					}
					
					if (PLUGIN_PRIVACYIDEA_ALWAYS_ACTIVATED)
						PrivacyIDEAData::setActivate(true);
				
					// Check, if user has enabled plugin and has activated 2FA
					if (!$GLOBALS["settings"]->get('zarafa/v1/plugins/privacyidea/enable')
						|| !PrivacyIDEAData::isActivated())
						break;
					
					// Check, if Client-IP is in Whitelist
					if (PLUGIN_PRIVACYIDEA_WHITELIST !== "") {
						foreach (explode (",", PLUGIN_PRIVACYIDEA_WHITELIST) as $range) {
							if (self::ip_in_range($_SERVER['REMOTE_ADDR'], $range))
								break 2;
						}
					}

					// Check, if token authorisation is already done (example: attachment-upload)
					if (array_key_exists('privacyIDEALoggedOn', $_SESSION) && $_SESSION['privacyIDEALoggedOn']) {

						// Login successful - save or remove code
						if (isset($_SESSION['privacyIDEACode'])) {
							unset($_SESSION['privacyIDEACode']);
						}
						break;
					}

					// Check if the LDAP object of the user has the yubikey attribute set
					if (!has_yubikey_attribute($username)) {
						break;
					}

					// Token needed - logoff, remember credentials for later logon with logon.php/login.php and load token-page
					// store credentials in temporary session, and remove from encryptionStore
					$fingerprint = $_SESSION['fingerprint'];
					$frontendFingerprint = $_SESSION['frontend-fingerprint'];

					$encryptionStore->add('username', '');
					$encryptionStore->add('password', '');

					$_SESSION = array(); // clear session to logoff and don't loose session
					
					$_SESSION['privacyIDEAUsername'] = $username; // or from $_POST/$GLOBALS
					$_SESSION['privacyIDEAPassword'] = $password;
					$_SESSION['privacyIDEAEcho']['boxTitle'] = dgettext('plugin_privacyidea', 'Press Yubikey');
					$_SESSION['privacyIDEAEcho']['txtCodePlaceholder'] = dgettext('plugin_privacyidea', 'Code');
					$_SESSION['privacyIDEAEcho']['msgInvalidCode'] = dgettext('plugin_privacyidea', 'Invalid token. Please check token.');
					$_SESSION['privacyIDEAEcho']['butLogin'] = dgettext('plugin_privacyidea', 'Login');
					$_SESSION['privacyIDEAFingerprint'] = $fingerprint;
					$_SESSION['privacyIDEAFrontendFingerprint'] = $frontendFingerprint;

					header('Location: plugins/privacyidea/php/login.php', true, 303); // delete GLOBALS, go to token page
					exit; // don't execute header-function in index.php

				} catch (Exception $e) {
					$mess = $e->getFile() . ":" . $e->getLine() . "<br />" . $e->getMessage();
					error_log("[privacyidea]: " . $mess);
                                        die($mess);
				}
                }
	}

	/**
	 * Check if a given ip is in a network
	 * (https://gist.github.com/tott/7684443)
	 *
	 * @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
	 * @param  string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
	 * @return boolean true if the ip is in this range / false if not.
	 */
	function ip_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) == false ) {
			$range .= '/32';
		}
		// $range is in IP/CIDR format eg 127.0.0.1/24
		list( $range, $netmask ) = explode( '/', $range, 2 );
		$range_decimal = ip2long( $range );
		$ip_decimal = ip2long( $ip );
		$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal = ~ $wildcard_decimal;
		return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
	}

	/**
	 * Inject default plugin settings
	 *
	 * @param Array $data Reference to the data of the triggered hook
	 */
	function injectPluginSettings(&$data, $has_yubikey) {
		$data['settingsObj']->addSysAdminDefaults(Array(
			'zarafa' => Array(
				'v1' => Array(
					'plugins' => Array(
						'privacyidea' => Array(
							'enable' => PLUGIN_PRIVACYIDEA_ENABLE,
							'enable_but_conf' => PLUGIN_PRIVACYIDEA_ENBUTCONF,
							'enable_but_activ' => PLUGIN_PRIVACYIDEA_ENBUTACTIV,
							'enable_but_reset' => PLUGIN_PRIVACYIDEA_ENBUTTRESET,
							'activate' => PLUGIN_PRIVACYIDEA_ACTIVATE,
							'validation_server' => PLUGIN_PRIVACYIDEA_VALIDATION_SERVER,
							'radius_secret' => PLUGIN_PRIVACYIDEA_RADIUS_SECRET,
							'ldap_host' => PLUGIN_PRIVACYIDEA_LDAP_HOST,
							'ldap_port' => PLUGIN_PRIVACYIDEA_LDAP_PORT,
							'ldap_user_group' => PLUGIN_PRIVACYIDEA_LDAP_USER_GROUP,
							'ldap_search_base' => PLUGIN_PRIVACYIDEA_LDAP_SEARCH_BASE,
							'ldap_username_attribute' => PLUGIN_PRIVACYIDEA_LDAP_USERNAME_ATTRIBUTE,
							'ldap_yubikey_attribute' => PLUGIN_PRIVACYIDEA_LDAP_YUBIKEY_ATTRIBUTE,
							'has_yubikey' => $has_yubikey
						)
					)
				)
			)
		));
	}
}
?>
