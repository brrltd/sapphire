<?php

/**
 * MySQL connector class using PDO
 *
 * @package framework
 * @subpackage model
 */
class PDOMySQLDatabase extends MySQLDatabase {
	/**
	 * The most recent statement returned from PDOMySQLDatabase->query
	 * @var PDOStatement
	 */
	protected $lastStatement = null;

	public function getConnect($parameters) {

		// Build DSN string
		$dsn = sprintf("mysql:host=%s;dbname=%s", $parameters['server'], $parameters['database']);
		if (!empty($parameters['port'])) {
			$dsn .= ";port=" . $parameters['port'];
		}

		if (!empty(self::$connection_charset)) {
			$dsn .= ';charset=' . self::$connection_charset;
		}

		// Connection commands to be run on every re-connection
		$options = array(
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
		);

		// May throw a PDOException if fails
		return new PDO($dsn, $parameters['username'], $parameters['password'], $options);
	}

	/**
	 * Get the version of MySQL.
	 * @return string
	 */
	public function getVersion() {
		return $this->dbConn->getAttribute(PDO::ATTR_SERVER_VERSION);
	}
	
	public function addslashes($value) {
		$value = $this->quote($value);
		// Since the PDO library quotes the value, we should remove this to maintain
		// consistency with MySQLDatabase::addslashes
		if (preg_match('/^\'(?<value>.+)\'$/', $value, $matches)) {
			$value = $matches['value'];
		}
		return $value;
	}

	public function quote($value) {
		return $this->dbConn->quote($value);
	}

	public function query($sql, $errorLevel = E_USER_ERROR) {
		if (isset($_REQUEST['previewwrite']) && in_array(strtolower(substr($sql, 0, strpos($sql, ' '))), array('insert', 'update', 'delete', 'replace'))) {
			Debug::message("Will execute: $sql");
			return;
		}

		if (isset($_REQUEST['showqueries']) && Director::isDev(true)) {
			$starttime = microtime(true);
		}

		$this->lastStatement = $this->dbConn->query($sql);

		if (isset($_REQUEST['showqueries']) && Director::isDev(true)) {
			$endtime = round(microtime(true) - $starttime, 4);
			Debug::message("\n$sql\n{$endtime}ms\n", false);
		}

		if (!$this->lastStatement && $errorLevel) {
			$this->databaseError("Couldn't run query: $sql | " . $this->getLastError(), $errorLevel);
		}

		return new PDOMySQLStatement($this->lastStatement);
	}

	function getLastError() {
		$error = $this->dbConn->errorInfo();
		if ($error)
			return sprintf("%s-%s: %s", $error[0], $error[1], $error[2]);
	}

	public function getGeneratedID($table) {
		return $this->dbConn->lastInsertId();
	}

	/**
	 * Return the number of rows affected by the previous operation.
	 * @return int
	 */
	public function affectedRows() {
		if(empty($this->lastStatement)) return 0;
		return $this->lastStatement->rowCount();
	}

	/**
	 * Switches to the given database.
	 * If the database doesn't exist, you should call createDatabase() after calling selectDatabase()
	 */
	public function selectDatabase($dbname) {
		$this->database = $dbname;
		$this->tableList = $this->fieldList = $this->indexList = null;
		if($this->active = $this->databaseExists($this->database)) {
			$this->query("USE \"{$this->database}\"");
		}
		return $this->active;
	}
}

/**
 * A result-set from a MySQL database.
 * @package framework
 * @subpackage model
 */
class PDOMySQLStatement extends SS_Query {
	/**
	 * The internal MySQL handle that points to the result set.
	 * @var PDOStatement
	 */
	protected $statement = null;

	protected $results = null;

	/**
	 * Hook the result-set given into a Query class, suitable for use by SilverStripe.
	 * @param handle the internal mysql handle that is points to the resultset.
	 */
	public function __construct(PDOStatement $statement) {
		$this->statement = $statement;
		// Since no more than one PDOStatement for any one connection can be safely
		// traversed, each statement simply requests all rows at once for safety.
		// This could be re-engineered to call fetchAll on an as-needed basis
		$this->results = $statement->fetchAll();
	}

	public function __destruct() {
		$this->statement->closeCursor();
	}

	public function seek($row) {
		$this->rowNum = $row - 1;
		return $this->nextRecord();
	}

	public function numRecords() {
		return count($this->results);
	}

	public function nextRecord() {
		$index = $this->rowNum + 1;
		
		if (isset($this->results[$index])) {
			return $this->results[$index];
		} else {
			return false;
		}
	}

}
