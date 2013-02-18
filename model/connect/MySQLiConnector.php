<?php

/**
 * Connector for MySQL using the MySQLi method
 * @package framework
 * @subpackage model
 */
class MySQLiConnector extends DBConnector {
	/**
	 * Connection to the MySQL database
	 * @var MySQLi
	 */
	protected $dbConn = null;

	/**
	 * Name of the currently selected database
	 * @var string
	 */
	protected $databaseName = null;

	public function connect($parameters) {

		$this->dbConn = new MySQLi($parameters['server'], $parameters['username'], $parameters['password']);

		if ($this->dbConn->connect_error) {
			$this->databaseError("Couldn't connect to MySQL database | " . $this->dbConn->connect_error);
		}

		// Set charset if given and not null. Can explicitly set to empty string to omit
		$charset = isset($parameters['charset'])
				? $parameters
				: 'utf8';
		if (!empty($charset)) $this->dbConn->set_charset($charset);
	}

	public function escapeString($value) {
		return $this->dbConn->real_escape_string($value);
	}

	public function quoteString($value) {
		$value = $this->escapeString($value);
		return "'$value'";
	}

	public function getVersion() {
		return $this->dbConn->server_info;
	}

	public function query($sql, $errorLevel = E_USER_ERROR) {

		// Check if we should only preview this query
		if ($this->previewWrite($sql)) return;

		// Benchmark query
		$conn = $this->dbConn;
		$handle = $this->benchmarkQuery($sql, function($sql) use($conn) {
			return $conn->query($sql);
		});

		if (!$handle) {
			if(!$errorLevel) return null;
			$this->databaseError("Couldn't run query: $sql | " . $this->getLastError(), $errorLevel);
		}

		return new MySQLQuery($this, $handle);
	}
	
	/**
	 * Prepares the list of parameters in preparation for passing to mysqli_stmt_bind_param
	 * 
	 * @param array $parameters List of parameters
	 * @param array &$blobs Out parameter for list of blobs to bind separately
	 * @return array List of parameters appropriate for mysqli_stmt_bind_param function
	 */
	public function parsePreparedParameters($parameters, &$blobs) {
		
		$types = '';
		$values = array();
		$blobs = array();
		for($index = 0; $index < count($parameters); $index++) {
			$value = $parameters[$index];
			$phpType = gettype($value);

			// Allow overriding of parameter type using an associative array
			if($phpType === 'array') {
				$phpType = $value['type'];
				$value = $value['value'];
			}

			// Convert php variable type to one that makes mysqli_stmt_bind_param happy
			// @see http://www.php.net/manual/en/mysqli-stmt.bind-param.php
			switch($phpType) {
				case 'boolean':
				case 'integer':
					$types .= 'i';
					break;
				case 'float': // Not actually returnable from gettype
				case 'double':
					$types .= 'd';
					break;
				case 'object': // Allowed if the object or resource has a __toString method
				case 'resource':
				case 'string':
				case 'NULL': // Take care that a where clause should use "where XX is null" not "where XX = null"
					$types .= 's';
					break;
				case 'blob':
					$types .= 'b';
					// Blobs must be sent via send_long_data and set to null here
					$blobs[] = array(
						'index' => $index,
						'value' => $value
					);
					$value = null;
					break;
				case 'array':
				case 'unknown type':
				default:
					user_error("Cannot bind parameter \"$value\" as it is an unsupported type ($phpType)", E_USER_ERROR);
					break;
			}
			$values[] = $value;
		}
		return array_merge(array($types), $values);
	}
	
	public function preparedQuery($sql, $parameters, $errorLevel = E_USER_ERROR) {
		
		// Shortcut to basic query when not given parameters
		if(empty($parameters)) return $this->query($sql, $errorLevel);

		// Check if we should only preview this query
		if ($this->previewWrite($sql)) return;

		// Type check, identify, and prepare parameters for passing to the statement bind function
		$parsedParameters = $this->parsePreparedParameters($parameters, $blobs);
		
		// Benchmark query
		$conn = $this->dbConn;
		$stmt = $this->benchmarkQuery($sql, function($sql) use($conn, $parsedParameters, $blobs) {
			
			// Prepare statement with arguments
			$statement = $conn->stmt_init();
			if(!$statement->prepare($sql)) {
				$statement->close();
				return null;
			}
			call_user_func_array( array($statement, 'bind_param'), $parsedParameters);
			
			// Bind any blobs given
			foreach($blobs as $blob) {
				$statement->send_long_data($blob['index'], $blob['value']);
			}
			
			// Safely execute the statement
			if(!$statement->execute()) {
				$statement->close();
				return null;
			}
			return $statement;
		});

		if (!$stmt && (($result = $stmt->get_result()) !== FALSE)) {
			if(!$errorLevel) return null;
			$this->databaseError("Couldn't run query: $sql | " . $this->getLastError(), $errorLevel);
		}

		return new MySQLQuery($this, $result, $stmt);
	}

	public function selectDatabase($name) {
		if ($this->dbConn->select_db($name)) {
			$this->databaseName = $name;
			return true;
		} else {
			return false;
		}
	}

	public function getSelectedDatabase() {
		return $this->databaseName;
	}

	public function unloadDatabase() {
		$this->databaseName = null;
	}

	public function isActive() {
		return $this->databaseName && $this->dbConn && empty($this->dbConn->connect_error);
	}

	public function affectedRows() {
		return $this->dbConn->affected_rows;
	}

	public function getGeneratedID($table) {
		return $this->dbConn->insert_id;
	}

	public function getLastError() {
		return $this->dbConn->error;
	}

}