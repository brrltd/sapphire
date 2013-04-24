<?php

/**
 * Object representing a SQL UPDATE query.
 * The various parts of the SQL query can be manipulated individually.
 * 
 * @package framework
 * @subpackage model
 */
class SQLUpdate extends SQLConditionalExpression implements SQLWriteExpression {
	
	/**
	 * The assignment to create for this update
	 * 
	 * @var SQLAssignmentRow
	 */
	protected $assignment = null;
	
	/**
	 * Construct a new SQLUpdate object
	 * 
	 * @param string $table Table name to update
	 * @param array $assignment List of column assignments
	 * @param array $where List of where clauses
	 * @return SQLUpdate
	 */
	public static function create($table = null, $assignment = array(), $where = array()) {
		return new SQLUpdate($table, $assignment, $where);
	}
	
	/**
	 * Construct a new SQLUpdate object
	 * 
	 * @param string $table Table name to update
	 * @param array $assignment List of column assignments
	 * @param array $where List of where clauses
	 */
	function __construct($table = null, $assignment = array(), $where = array()) {
		parent::__construct(null, $where);
		$this->assignment = new SQLAssignmentRow();
		$this->setTable($table);
		$this->setAssignments($assignment);
	}
	
	/**
	 * Sets the table name to update
	 *
	 * @param string $table
	 * @return SQLUpdate
	 */
	public function setTable($table) {
		return $this->setFrom($table);
	}
	
	/**
	 * Gets the table name to update
	 * 
	 * @return string Name of the table
	 */
	public function getTable() {
		return reset($this->from);
	}
	
	public function addAssignments(array $assignments) {
		$this->assignment->addAssignments($assignments);
		return $this;
	}
	
	public function setAssignments(array $assignments) {
		$this->assignment->setAssignments($assignments);
		return $this;
	}
	
	public function getAssignments() {
		return $this->assignment->getAssignments();
	}
	
	public function assign($field, $value) {
		$this->assignment->assign($field, $value);
		return $this;
	}
	
	public function assignSQL($field, $sql) {
		$this->assignment->assignSQL($field, $sql);
		return $this;
	}
	
	/**
	 * Clears all currently set assigment values
	 * 
	 * @return SQLUpdate The self reference to this query
	 */
	public function clear() {
		$this->assignment->clear();
		return $this;
	}
	
	public function isEmpty() {
		return empty($this->assignment) || $this->assignment->isEmpty() || parent::isEmpty();
	}
}