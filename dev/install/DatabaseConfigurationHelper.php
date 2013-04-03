<?php

/**
 * Abstract for database helper classes.
 * @package framework
 */
abstract class DatabaseConfigurationHelper {
	
	/**
	 * Generates a basic DBConnector from a parameter list.
	 * Does basically the same work as the database configuration and controller
	 * connection logic
	 * 
	 * @param array $databaseConfig
	 * @return DBConnector
	 * @throws Exception Connection error
	 */
	abstract function makeConnection($databaseConfig);
		
	/**
	 * Ensure that the database function for connectivity is available.
	 * If it is, we assume the PHP module for this database has been setup correctly.
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return boolean
	 */
	abstract function requireDatabaseFunctions($databaseConfig);

	/**
	 * Ensure that the database server exists.
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('okay' => true, 'error' => 'details of error')
	 */
	public function requireDatabaseServer($databaseConfig) {
		return $this->requireDatabaseConnection($databaseConfig);
	}

	/**
	 * Determines the version of the database server
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return string Version of database server
	 */
	public function getDatabaseVersion($databaseConfig) {
		$conn = $this->makeConnection($databaseConfig);
		if($conn) return $conn->getVersion();
	}

	/**
	 * Check database version
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('success' => true, 'error' => 'details of error')
	 */
	public function requireDatabaseVersion($databaseConfig) {
		$version = $this->getDatabaseVersion($databaseConfig);
		return array(
			'success' => !empty($version),
			'error' => empty($version) ? 'Database version could not be determined' : null
		);
	}

	/**
	 * Ensure a database connection is possible using credentials provided.
	 * The established connection resource is returned with the results as well.
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('okay' => true, 'connection' => mysql link, 'error' => 'details of error')
	 */
	public function requireDatabaseConnection($databaseConfig) {
		// Connect and check error
		$conn = null;
		try {
			$conn = $this->makeConnection($databaseConfig);
			$error = $conn ? $conn->getLastError() : null;
		} catch(Exception $ex) {
			$error = $ex->getMessage();
		}
		
		// Fill in empty error if connection failed
		$success = $conn && empty($error);
		if(!$success && empty($error)) {
			$error = 'Database connection could not be established with unknown error';
		}
		
		return array(
			'success' => $success,
			'connection' => $conn,
			'error' => $error
		);
	}

	/**
	 * Ensure that the database connection is able to use an existing database,
	 * or be able to create one if it doesn't exist.
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('okay' => true, 'existsAlready' => 'true')
	 */
	abstract function requireDatabaseOrCreatePermissions($databaseConfig);


	/**
	 * Ensure we have permissions to alter tables.
	 *
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('okay' => true, 'applies' => true), where applies is whether
	 * the test is relevant for the database
	 */
	abstract function requireDatabaseAlterPermissions($databaseConfig);

}
