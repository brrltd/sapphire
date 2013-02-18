<?php

/**
 * Represents an object responsible for wrapping DB connector api
 * 
 * @package framework
 * @subpackage model
 */
abstract class DBConnector {

	/**
	 * Error handler for database errors.
	 * All database errors will call this function to report the error.  It isn't a static function;
	 * it will be called on the object itself and as such can be overridden in a subclass.
	 * Subclasses should run all errors through this function.
	 * 
	 * @todo hook this into a more well-structured error handling system.
	 * @param string $msg The error message.
	 * @param integer $errorLevel The level of the error to throw.
	 */
	protected function databaseError($msg, $errorLevel = E_USER_ERROR) {

		// try to extract and format query
		if (preg_match('/Couldn\'t run query: ([^\|]*)\|\s*(.*)/', $msg, $matches)) {
			$formatter = new SQLFormatter();
			$msg = "Couldn't run query: \n" . $formatter->formatPlain($matches[1]) . "\n\n" . $matches[2];
		}

		user_error($msg, $errorLevel);
	}
	
	public static $write_operations = array('insert', 'update', 'delete', 'replace');

	/**
	 * Determines if the query should be previewed, and thus interrupted silently.
	 * If so, this function also displays the query via the debuging system.
	 * Subclasess should respect the results of this call for each query, and not
	 * execute any queries that generate a true response.
	 * 
	 * @param string $sql The query to be executed
	 * @return boolean Flag indicating that the query was previewed
	 */
	protected function previewWrite($sql) {

		// Break if not requested
		if (!isset($_REQUEST['previewwrite'])) return false;

		// Break if non-write operation
		$operation = strtolower(substr($sql, 0, strpos($sql, ' ')));
		if (!in_array($operation, self::$write_operations)) {
			return false;
		}

		// output preview message
		Debug::message("Will execute: $sql");
		return true;
	}

	/**
	 * Allows the display and benchmarking of queries as they are being run
	 * 
	 * @param string $sql Query to run, and single parameter to callback
	 * @param callable $callback Callback to execute code
	 * @return mixed Result of query
	 */
	protected function benchmarkQuery($sql, $callback) {

		if (isset($_REQUEST['showqueries']) && Director::isDev(true)) {
			$starttime = microtime(true);
			$result = $callback($sql);
			$endtime = round(microtime(true) - $starttime, 4);
			Debug::message("\n$sql\n{$endtime}ms\n", false);
			return $result;
		} else {
			return $callback($sql);
		}
	}

	/**
	 * Link this connector to the database given the specified parameters
	 * Will throw an exception rather than return a success state.
	 * The connector should not select the database once connected until
	 * explicitly called by selectDatabase()
	 * 
	 * @param array $parameters List of parameters such as
	 * <ul>
	 *   <li>type</li>
	 *   <li>server</li>
	 *   <li>username</li>
	 *   <li>password</li>
	 *   <li>database</li>
	 *   <li>path</li>
	 * </ul>
	 */
	abstract public function connect($parameters);

	/**
	 * Query for the version of the currently connected database
	 * 
	 * @return string Version of this database
	 */
	abstract public function getVersion();

	/**
	 * Given a value escape this for use in a query for the current database
	 * connector. Note that this does not quote the value.
	 * 
	 * @param string $value The value to be escaped
	 * @return string The appropritaely escaped string for value
	 */
	abstract public function escapeString($value);

	/**
	 * Given a value escape and quote this appropriately for the current
	 * database connector.
	 * 
	 * @param string $value The value to be injected into a query
	 * @return string The appropriately escaped and quoted string for $value
	 */
	abstract public function quoteString($value);

	/**
	 * Executes the following query with the specified error level.
	 * Implementations of this function should respect previewWrite and benchmarkQuery
	 * 
	 * @see http://php.net/manual/en/errorfunc.constants.php
	 * @param string $sql The SQL query to execute
	 * @param integer $errorLevel For errors to this query, raise PHP errors
	 * using this error level. 
	 */
	abstract public function query($sql, $errorLevel = E_USER_ERROR);

	/**
	 * Execute the given SQL parameterised query with the specified arguments
	 * 
	 * @param string $sql The SQL query to execute. The ? character will denote parameters.
	 * @param array $parameters An ordered list of arguments.
	 * @param int $errorLevel The level of error reporting to enable for the query
	 * @return SS_Query
	 */
	abstract public function preparedQuery($sql, $parameters, $errorLevel = E_USER_ERROR);
	
	/**
	 * Select a database by name
	 * 
	 * @param string $name Name of database
	 * @return boolean Flag indicating success
	 */
	abstract public function selectDatabase($name);

	/**
	 * Retrieves the name of the currently selected database
	 * 
	 * @return string Name of the database, or null if none selected
	 */
	abstract public function getSelectedDatabase();

	/**
	 * De-selects the currently selected database
	 */
	abstract public function unloadDatabase();

	/**
	 * Retrieves the last error generated from the database connection
	 * 
	 * @return string The error message
	 */
	abstract public function getLastError();

	/**
	 * Determines the last ID generated from the specified table.
	 * Note that some connectors may not be able to return $table specific responses,
	 * and this parameter may be ignored.
	 * 
	 * @param string $table The target table to return the last generated ID for
	 * @return integer ID value
	 */
	abstract public function getGeneratedID($table);

	/**
	 * Determines the number of affected rows from the last SQL query
	 * 
	 * @return integer Number of affected rows
	 */
	abstract public function affectedRows();

	/**
	 * Determines if we are connected to a server AND have a valid database
	 * selected.
	 * 
	 * @return boolean Flag indicating that a valid database is connected
	 */
	abstract public function isActive();
}