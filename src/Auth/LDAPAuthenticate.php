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

namespace YALP\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Network\Request;
use Cake\Network\Response;
use YALP\Lib\YalpUtility;

class LDAPAuthenticate extends BaseAuthenticate {

	private $YALP;
	
    /**
     * Constructor
     *
     * @param \Cake\Controller\ComponentRegistry $registry The Component registry used on this request.
     * @param array $config Array of config to use.
     */
	function __construct(ComponentRegistry $registry, $config = array()) {

		$this->form_fields = Configure::read('LDAP.form_fields');
		$this->form_fields = (isset($config['form_fields'])) ? $config['form_fields'] : $this->form_fields;

		$this->YALP = new YalpUtility($config);

		parent::__construct($registry, $config);
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
		CakeLog::write('yalp', "[YALP.authenticate] Authentication started");

		$userField = $this->form_fields['username'];

		$passField = $this->form_fields['password'];

		$userModel = $this->config['userModel'];
		list($plugin, $model) = pluginSplit($userModel);

		// Definitely not authenticated if we haven't got the request data...
		if (!isset($request->data[$userModel])) {
			CakeLog::write('yalp', "[YALP.authenticate] No request data, cannot authenticate");
			return false;
		}

		// We need to know the username, or email, or some other unique ID
		$submittedDetails = $request->data[$userModel];

		if (!isset($submittedDetails[$userField])) {
			CakeLog::write('yalp', "[YALP.authenticate] No username supplied, cannot authenticate");
			return false;
		}

		// Make sure it's a valid string...
		$username = $submittedDetails[$userField];
		if (!is_string($username)) {
			CakeLog::write('yalp', "[YALP.authenticate] Invalid username, cannot authenticate");
			return false;
		}

		// Make sure they gave us a password too...
		$password = $submittedDetails[$passField];
		if (!is_string($password) || empty($password)) {
			return false;
		}

		// Check whether or not user exists on LDAP
		if (! $this->YALP->validateUser($username, $password)) {
			CakeLog::write('yalp', "[YALP.authenticate] User '$username' could not be find on LDAP");
			return false;
		} else {
			CakeLog::write('yalp', "[YALP.authenticate] User '$username' were found on LDAP");
		}

		// Check on DB
		$comparison = 'LOWER(' . $model . '.' . $userField . ')';

		$conditions = array(
			$comparison => strtolower($username),
			);

		$dbUser = ClassRegistry::init($userModel)->find('first', array(
			'conditions' => $conditions,
			'recursive'	=> false
			));

		// If we couldn't find them in the database, create a new DB entry
		if (empty($dbUser) || empty($dbUser[$model])) {
			CakeLog::write('yalp', "[YALP.authenticate] Could not find a database entry for $username");
			return false;
		}

		// Ensure there's nothing in the password field
		unset($dbUser[$model][$passField]);

		// ...and return the user object.
		return $dbUser[$model];
	}

}
