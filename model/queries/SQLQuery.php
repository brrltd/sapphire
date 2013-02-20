<?php

/**
 * Object representing a SQL SELECT query.
 * The various parts of the SQL query can be manipulated individually.
 * 
 * @package framework
 * @subpackage model
 * @deprecated since version 3.1
 */
class SQLQuery extends SQLSelect {
	
	/**
	 * @deprecated since version 3.1
	 */
	public function __construct($select = "*", $from = array(), $where = array(), $orderby = array(), $groupby = array(), $having = array(), $limit = array()) {
		parent::__construct($select, $from, $where, $orderby, $groupby, $having, $limit);
		Deprecation::notice('3.1', 'Use SQLSelect instead');
	}
}