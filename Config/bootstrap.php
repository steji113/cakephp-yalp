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

 
use Cake\Log\Log;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;

// Load the LDAP configuration
try {
	Configure::load('ldap');
} catch (\Exception $e) {
    die($e->getMessage() . "\n");
}
