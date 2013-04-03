<?php
/**
 * This is a helper class for the SS installer.
 *
 * It does all the specific checking for MySQLDatabase
 * to ensure that the configuration is setup correctly.
 *
 * @package framework
 * @subpackage model
 */
class MySQLDatabaseConfigurationHelper extends DatabaseConfigurationHelper {
	
	public function makeConnection($databaseConfig) {
		switch($databaseConfig['type']) {
			case 'MySQLDatabase':
			case 'MySQLPDODatabase': // Not actually used, but just in case
				$connector = new PDOConnector();
				break;
			case 'MySQLiDatabase':
				$connector = new MySQLiConnector();
				break;
			default:
				return null;
		}
		
		// Connect
		$databaseConfig['driver'] = 'mysql';
		@$connector->connect($databaseConfig);
		@$connector->preparedQuery("SET sql_mode = ?", array('ANSI'));
		return $connector;
	}

	/**
	 * Ensure that the database function for connectivity is available.
	 * If it is, we assume the PHP module for this database has been setup correctly.
	 *
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return boolean
	 */
	public function requireDatabaseFunctions($databaseConfig) {
		switch($databaseConfig['type']) {
			case 'MySQLiDatabase':
				return class_exists('MySQLi');
			case 'MySQLDatabase':
				return class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers());
			default:
				return false;
		}
	}

	/**
	 * Ensure that the MySQL server version is at least 5.0.
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('success' => true, 'error' => 'details of error')
	 */
	public function requireDatabaseVersion($databaseConfig) {
		$version = $this->getDatabaseVersion($databaseConfig);
		$success = false;
		$error = '';
		if($version) {
			$success = version_compare($version, '5.0', '>=');
			if(!$success) {
				$error = "Your MySQL server version is $version. It's recommended you use at least MySQL 5.0.";
			}
		} else {
			$error = "Could not determine your MySQL version.";
		}
		return array(
			'success' => $success,
			'error' => $error
		);
	}

	/**
	 * Ensure that the database connection is able to use an existing database,
	 * or be able to create one if it doesn't exist.
	 *
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('success' => true, 'alreadyExists' => 'true')
	 */
	public function requireDatabaseOrCreatePermissions($databaseConfig) {
		$success = false;
		$alreadyExists = false;
		
		$check = $this->requireDatabaseConnection($databaseConfig);
		$conn = $check['connection'];
		if(!$conn || !$check['success']) {
			// No success
		} else {
			// does this database exist already?
			$list = $conn->query("SHOW DATABASES")->column();
			if(in_array($databaseConfig['database'], $list)) {
				$success = true;
				$alreadyExists = true;
			} else{
				// If no database exists then check DDL permissions
				$alreadyExists = false;
				foreach($conn->query("SHOW GRANTS FOR CURRENT_USER")->column() as $grant) {
					if(preg_match('/GRANT.+((ALL PRIVILEGES)|(ALTER)).+ON.+TO/i', $grant)) {
						$success = true;
						break;
					}
				}
			}
		}
		
		return array(
			'success' => $success,
			'alreadyExists' => $alreadyExists
		);
	}

	/**
	 * Ensure we have permissions to alter tables.
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('okay' => true, 'applies' => true), where applies is whether
	 * the test is relevant for the database
	 */
	public function requireDatabaseAlterPermissions($databaseConfig) {
		$success = false;
		$check = $this->requireDatabaseConnection($databaseConfig);
		$conn = $check['connection'];
		if(!$conn || !$check['success']) {
			// No success
		} else {
			// Annoyingly, MySQL 'escapes' the database, so we need to do it too.
			$dbEscape = preg_quote($databaseConfig['database']);
			$wildcardEscape = preg_quote('*.');
			foreach($conn->query("SHOW GRANTS FOR CURRENT_USER")->column() as $grant) {
				if (preg_match('/GRANT.+((ALL PRIVILEGES)|(ALTER)).+ON.+(('.$dbEscape.')|('.$wildcardEscape.')).+TO/', $grant)) {
					$success = true;
					break;
				}
			}
		}
		return array(
			'success' => $success,
			'applies' => true
		);
	}
}
