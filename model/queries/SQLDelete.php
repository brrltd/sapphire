<?php

/**
 * Object representing a SQL DELETE query.
 * The various parts of the SQL query can be manipulated individually.
 * 
 * @package framework
 * @subpackage model
 */
class SQLDelete extends SQLConditionalExpression {
	
	/**
	 * List of tables to limit the delete to, if multiple tables
	 * are specified in the condition clause
	 * 
	 * @see http://dev.mysql.com/doc/refman/5.0/en/delete.html
	 * 
	 * @var array
	 */
	protected $delete = array();

	/**
	 * List of tables to limit the delete to, if multiple tables
	 * are specified in the condition clause
	 * 
	 * @return array
	 */
	public function getDelete() {
		return $this->delete;
	}
	
	/**
	 * Sets the list of tables to limit the delete to, if multiple tables
	 * are specified in the condition clause
	 *
	 * @param string|array $tables Escaped SQL statement, usually an unquoted table name
	 * @return SQLQuery
	 */
	public function setDelete($tables) {
		$this->delete = array();
		return $this->addDelete($tables);
	}

	/**
	 * Sets the list of tables to limit the delete to, if multiple tables
	 * are specified in the condition clause
	 *
	 * @param string|array $tables Escaped SQL statement, usually an unquoted table name
	 * @return SQLQuery
	 */
	public function addDelete($tables) {
		if(is_array($tables)) {
			$this->delete = array_merge($this->delete, $tables);
		} elseif(!empty($tables)) {
			$this->delete[str_replace(array('"','`'), '', $tables)] = $tables;
		}
	}
}