<?php

/**
 * Yet Another LDAP Plugin
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2013, Jose Valecillos.
 * @link http://jvalecillos.net
 * @author Jose Valecillos <valecillosjg@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Yalp\Auth;

use Cake\Core\Configure;
use Cake\Controller\ComponentRegistry;
use Cake\Log\Log;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Auth\BaseAuthenticate;
use Yalp\Lib\YalpUtility;
use Cake\ORM\TableRegistry;

class LdapAuthenticate extends BaseAuthenticate {

	private $Yalp;
	
	/**
	 * Constructor
	 *
     * @param \Cake\Controller\ComponentRegistry $registry The Component registry
     *   used on this request.
     * @param array $config Array of config to use.
	 */
	function __construct(ComponentRegistry $collection, $settings = array()) {
		$this->form_fields = Configure::read('Ldap.form_fields');
		$this->form_fields = (isset($settings['form_fields'])) ? $settings['form_fields'] : $this->form_fields;

		$this->Yalp = new YalpUtility($settings);

		parent::__construct($collection, $settings);
	}

	/**
	 * Authentication hook to authenticate a user against an LDAP server.
	 *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @param \Cake\Network\Response $response Unused response object.
     * @return mixed False on login failure.  An array of User data on success.
	 */
	public function authenticate(Request $request, Response $response) {
		// This will probably be cn or an email field to search for
		Log::debug("[Yalp.authenticate] Authentication started", 'yalp');

		$userField = $this->form_fields['username'];

		$passField = $this->form_fields['password'];

		$userModel = $this->config('userModel');
		list($plugin, $model) = pluginSplit($userModel);

		// Definitely not authenticated if we haven't got the request data...
		if (!isset($request->data[$userModel])) {
			Log::error("[Yalp.authenticate] No request data, cannot authenticate", 'yalp');
			return false;
		}

		// We need to know the username, or email, or some other unique ID
		$submittedDetails = $request->data[$userModel];

		if (!isset($submittedDetails[$userField])) {
			//Log::write('yalp', "[Yalp.authenticate] No username supplied, cannot authenticate");
			return false;
		}

		// Make sure it's a valid string...
		$username = $submittedDetails[$userField];
		if (!is_string($username)) {
			Log::error("[Yalp.authenticate] Invalid username, cannot authenticate", 'yalp');
			return false;
		}

		// Make sure they gave us a password too...
		$password = $submittedDetails[$passField];
		if (!is_string($password) || empty($password)) {
			Log::error("[Yalp.authenticate] Invalid password, cannot authenticate", 'yalp');
			return false;
		}

		// Check whether or not user exists on LDAP
		if (!$this->Yalp->validateUser($username, $password)) {
			Log::error("[Yalp.authenticate] User '$username' could not be found on LDAP", 'yalp');
			return false;
		} else {
			Log::debug("[Yalp.authenticate] User '$username' was found on LDAP", 'yalp');
		}

		// Check on DB
		$comparison = 'LOWER(' . $model . '.' . $userField . ')';

		$conditions = array(
			$comparison => strtolower($username),
			);

		$dbUser = TableRegistry::get($userModel)->find('all', array(
			'conditions' => $conditions,
			'recursive'	=> false
			))->first();
			
		// If we couldn't find them in the database, create a new DB entry
		if (empty($dbUser)) {
			Log::warning("[Yalp.authenticate] Could not find a database entry for $username", 'yalp');
		}

		// ...and return the user object.
		return $dbUser->toArray();
	}

}
