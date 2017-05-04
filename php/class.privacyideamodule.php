<?php

require_once("Auth/auth_helper.php");

/**
 * WebApp plugin module for interaction with JS-GUI
 *
 * @class PrivacyIDEAModule
 * @extends Module
 * @author Daniel Rauer, bytemine GmbH
 * @copyright 2016 bytemine GmbH
 * @license http://www.gnu.org/licenses/ GNU Affero General Public License
 * @link https://www.bytemine.net/
 */
class PrivacyIDEAModule extends Module {

	/**
	 * @constructor
         * @access public
	 * @param int $id unique id of the class
	 * @param array $data list of all actions, which is received from the client
	 */
	public function __construct($id, $data) {
		# in Kopano WebApp the construct method is used explicitely
		if(is_callable('parent::__construct')) {
			parent::__construct($id, $data);
		} else {
			parent::Module($id, $data);
		}
	}

	/**
	 * Executes all the actions in the $data variable.
	 *
         * @access public
	 * @return boolean true on success or false on failure.
	 */
	public function execute() {
		$result = false;
		foreach($this->data as $actionType => $actionData) {
			if(isset($actionType)) {
				try {
					switch($actionType) {
						case "resetconfiguration":
							$result = $this->resetConfiguration();
							break;
						case "activate":
							$result = $this->activate();
							break;
						case "isactivated":
							$result = $this->isActivated();
							break;
						case "verifycode":
							$result = $this->verifyCode($actionData);
							break;
						default:
							$this->handleUnknownActionType($actionType);
					}
				} catch (Exception $e) {
					$mess = $e->getFile() . ":" . $e->getLine() . "<br />" . $e->getMessage();
					error_log("[privacyidea]: " . $mess);
					$this->sendFeedback(false, array(
						'type' => ERROR_GENERAL,
						'info' => array('original_message' => $mess, 'display_message' => $mess)
		              		));
				}
			}
		}
		return $result;
	}

	/**
	 * Reset configuration
	 *
         * @access private
	 * @return boolean
	 */
	private function resetConfiguration() {
		PrivacyIDEAData::setActivate(false);
                $response['isActivated'] = false;
		$this->addActionData("resetconfiguration", $response);
		$GLOBALS["bus"]->addData($this->getResponseData());
                return true;
	}

        /**
         * Toggle activate/deactivate two-factor authentication
         *
         * @access private
         * @return boolean
         */
	private function activate() {
		$isActivated = PrivacyIDEAData::isActivated();
		PrivacyIDEAData::setActivate(!$isActivated);
		$response = array();
		$response['isActivated'] = !$isActivated;
		$this->addActionData("activate", $response);
                $GLOBALS["bus"]->addData($this->getResponseData());
		return true;
	}

        /**
         * Send if two-factor authentication is activated
         *
         * @access private
         * @return boolean
         */
	private function isActivated() {
		$isActivated = PrivacyIDEAData::isActivated();
		$response = array();
		$response['isActivated'] = $isActivated;
		$this->addActionData("isactivated", $response);
		$GLOBALS["bus"]->addData($this->getResponseData());
		return true;
        }

        /**
         * Verify code
         *
         * @access private
         * @return boolean
         */
	private function verifyCode($actionData) {
		$code = $actionData['code'];
		$isCodeOK = false;

		if(has_yubikey_attribute($_SESSION['privacyIDEAUsername'])) {
			$isCodeOK = radius_auth($_SESSION['privacyIDEAUsername'], $code);
		}

		$response['isCodeOK'] = $isCodeOK;
		$this->addActionData("verifycode", $response);
		$GLOBALS["bus"]->addData($this->getResponseData());
		return true;
	}
}

?>
