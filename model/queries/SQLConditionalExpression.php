<?php

/**
 * Represents a SQL query for an expression which interacts with existing rows
 * (SELECT / DELETE / UPDATE) with a WHERE clause
 * 
 * @package framework
 * @subpackage model
 */
abstract class SQLConditionalExpression extends SQLExpression {
	
	/**
	 * An array of WHERE clauses.
	 * 
	 * Each item in this array will be in the form of a single-length array
	 * in the format array('predicate' => array($parameters))
	 * 
	 * @var array
	 */
	protected $where = array();
	
	/**
	 * The logical connective used to join WHERE clauses. Defaults to AND.
	 * 
	 * @var string
	 */
	protected $connective = 'AND';
	
	/**
	 * An array of tables. The first one is just the table name.
	 * Used as the FROM in DELETE/SELECT statements, the INTO in INSERT statements,
	 * and the target table in UPDATE statements
     * 
     * The keys of this array are the aliases of the tables (unquoted), where the
     * values are either the literal table names, or an array with join details.
     * 
     * @see SQLConditionalExpression::addLeftJoin()
	 * 
	 * @var array
	 */
	protected $from = array();
	
	/**
	 * Construct a new SQLInteractExpression.
	 *
	 * @param array|string $from An array of Tables (FROM clauses). The first one should be just the table name.
	 * @param array $where An array of WHERE clauses.
	 */
	function __construct($from = array(), $where = array()) {
		$this->setFrom($from);
		$this->setWhere($where);
	}
	
	/**
	 * Sets the list of tables to query from or update
	 *
	 * @example $query->setFrom("MyTable"); // SELECT * FROM MyTable
	 *
	 * @param string|array $from Escaped SQL statement, usually an unquoted table name
	 * @return SQLQuery
	 */
	public function setFrom($from) {
		$this->from = array();
		return $this->addFrom($from);
	}

	/**
	 * @deprecated since version 3.0
	 */
	public function from($from) {
		Deprecation::notice('3.0', 'Please use setFrom() or addFrom() instead!');
		return $this->setFrom($from);
	}

	/**
	 * Add a table to include in the query or update
	 *
	 * @example $query->addFrom("MyTable"); // UPDATE MyTable
	 *
	 * @param string|array $from Escaped SQL statement, usually an unquoted table name
	 * @return SQLQuery
	 */
	public function addFrom($from) {
		if(is_array($from)) {
			$this->from = array_merge($this->from, $from);
		} elseif(!empty($from)) {
			$this->from[str_replace(array('"','`'), '', $from)] = $from;
		}

		return $this;
	}

	/**
	 * Set the connective property.
	 * 
	 * @param string $value either 'AND' or 'OR'
	 */
	public function setConnective($value) {
		$this->connective = $value;
	}

	/**
	 * Get the connective property.
	 * 
	 * @return string 'AND' or 'OR'
	 */
	public function getConnective() {
		return $this->connective;
	}
		
	/**
	 * Use the disjunctive operator 'OR' to join filter expressions in the WHERE clause.
	 */
	public function useDisjunction() {
		$this->setConnective('OR');
	}

	/**
	 * Use the conjunctive operator 'AND' to join filter expressions in the WHERE clause.
	 */
	public function useConjunction() {
		$this->setConnective('AND');
	}
	
	/**
	 * Add a LEFT JOIN criteria to the tables list.
	 *
	 * @param string $table Unquoted table name
	 * @param string $onPredicate The "ON" SQL fragment in a "LEFT JOIN ... AS ... ON ..." statement, Needs to be valid 
	 *                            (quoted) SQL.
	 * @param string $tableAlias Optional alias which makes it easier to identify and replace joins later on
	 * @param int $order A numerical index to control the order that joins are added to the query; lower order values 
	 *                   will cause the query to appear first. The default is 20, and joins created automatically by the
	 *                   ORM have a value of 10.
	 * @return SQLQuery
	 */
	public function addLeftJoin($table, $onPredicate, $tableAlias = '', $order = 20) {
		if(!$tableAlias) {
			$tableAlias = $table;
		}
		$this->from[$tableAlias] = array(
			'type' => 'LEFT',
			'table' => $table,
			'filter' => array($onPredicate), 
			'order' => $order
		);
		return $this;
	}

	/**
	 * @deprecated since version 3.0
	 */
	public function leftjoin($table, $onPredicate, $tableAlias = null, $order = 20) {
		Deprecation::notice('3.0', 'Please use addLeftJoin() instead!');
		$this->addLeftJoin($table, $onPredicate, $tableAlias);
	}

	/**
	 * Add an INNER JOIN criteria
	 *
	 * @param string $table Unquoted table name
	 * @param string $onPredicate The "ON" SQL fragment in an "INNER JOIN ... AS ... ON ..." statement. Needs to be 
	 *                            valid (quoted) SQL.
	 * @param string $tableAlias Optional alias which makes it easier to identify and replace joins later on
	 * @param int $order A numerical index to control the order that joins are added to the query; lower order values 
	 *                   will cause the query to appear first. The default is 20, and joins created automatically by the
	 *                   ORM have a value of 10.
	 * @return SQLQuery
	 */
	public function addInnerJoin($table, $onPredicate, $tableAlias = null, $order = 20) {
		if(!$tableAlias) $tableAlias = $table;
		$this->from[$tableAlias] = array(
			'type' => 'INNER',
			'table' => $table,
			'filter' => array($onPredicate),
			'order' => $order
		);
		return $this;
	}

	/**
	 * @deprecated since version 3.0
	 */
	public function innerjoin($table, $onPredicate, $tableAlias = null, $order = 20) {
		Deprecation::notice('3.0', 'Please use addInnerJoin() instead!');
		return $this->addInnerJoin($table, $onPredicate, $tableAlias, $order);
	}

	/**
	 * Add an additional filter (part of the ON clause) on a join.
	 *
	 * @param string $table Table to join on from the original join
	 * @param string $filter The "ON" SQL fragment (escaped)
	 * @return SQLQuery
	 */
	public function addFilterToJoin($table, $filter) {
		$this->from[$table]['filter'][] = $filter;
		return $this;
	}

	/**
	 * Set the filter (part of the ON clause) on a join.
	 *
	 * @param string $table Table to join on from the original join
	 * @param string $filter The "ON" SQL fragment (escaped)
	 * @return SQLQuery
	 */
	public function setJoinFilter($table, $filter) {
		$this->from[$table]['filter'] = array($filter);
		return $this;
	}
	
	/**
	 * Returns true if we are already joining to the given table alias
	 * 
	 * @return boolean
	 */
	public function isJoinedTo($tableAlias) {
		return isset($this->from[$tableAlias]);
	}
	
	/**
	 * Return a list of tables that this query is selecting from.
	 * 
	 * @return array Unquoted table names
	 */
	public function queriedTables() {
		$tables = array();
		
		foreach($this->from as $key => $tableClause) {
			if(is_array($tableClause)) {
				$table = '"'.$tableClause['table'].'"';
			} else if(is_string($tableClause) && preg_match('/JOIN +("[^"]+") +(AS|ON) +/i', $tableClause, $matches)) {
				$table = $matches[1];
			} else {
				$table = $tableClause;
			}

			// Handle string replacements
			if($this->replacementsOld) $table = str_replace($this->replacementsOld, $this->replacementsNew, $table);
			
			$tables[] = preg_replace('/^"|"$/','',$table);
		}
		
		return $tables;	
	}

	/**
	 * Return a list of tables queried
	 * 
	 * @return array
	 */
	public function getFrom() {
		return $this->from;
	}
	
	/**
	 * Retrieves the finalised list of joins
	 * 
	 * @todo This part of the code could be simplified
	 * 
	 * @return array List of joins as a mapping from array('Alias' => 'Join Expression')
	 */
	public function getJoins() {
		
		// Sort the joins
		$joins = $this->getOrderedJoins($this->from);
		
		// Build from clauses
		foreach($joins as $alias => $join) {
			// $join can be something like this array structure
			// array('type' => 'inner', 'table' => 'SiteTree', 'filter' => array("SiteTree.ID = 1", 
			// "Status = 'approved'", 'order' => 20))
			if(is_array($join)) {
				if(is_string($join['filter'])) {
					$filter = $join['filter'];
				} elseif(sizeof($join['filter']) == 1) {
					$filter = $join['filter'][0];
				} else {
					$filter = "(" . implode(") AND (", $join['filter']) . ")";
				}

				$aliasClause = ($alias != $join['table']) ? " AS \"$alias\"" : "";
				$joins[$alias] = strtoupper($join['type']) . " JOIN \"" . $join['table'] . "\"$aliasClause ON $filter";
			}
		}
		
		return $joins;
	}
	
	/**
	 * Ensure that framework "auto-generated" table JOINs are first in the finalised SQL query.
	 * This prevents issues where developer-initiated JOINs attempt to JOIN using relations that haven't actually 
	 * yet been scaffolded by the framework. Demonstrated by PostGres in errors like: 
	 *"...ERROR: missing FROM-clause..."
	 * 
	 * @param $from array - in the format of $this->from
	 * @return array - and reorderded list of selects
	 */
	protected function getOrderedJoins($from) {
		// shift the first FROM table out from so we only deal with the JOINs
		$baseFrom = array_shift($from);
		$this->mergesort($from, function($firstJoin, $secondJoin) {
			if($firstJoin['order'] == $secondJoin['order']) {
				return 0;
			}
			return ($firstJoin['order'] < $secondJoin['order']) ?  -1 : 1;
		});
		
		// Put the first FROM table back into the results 
		array_unshift($from, $baseFrom);
		return $from;
	}

	/**
	 * Since uasort don't preserve the order of an array if the comparison is equal
	 * we have to resort to a merge sort. It's quick and stable: O(n*log(n)).
	 *
	 * @see http://stackoverflow.com/q/4353739/139301
	 * 
	 * @param array &$array - the array to sort
	 * @param callable $cmpFunction - the function to use for comparison
	 */ 
	protected function mergesort(&$array, $cmpFunction = 'strcmp') {
		// Arrays of size < 2 require no action.
		if (count($array) < 2) {
			return;
		}
		// Split the array in half
		$halfway = count($array) / 2;
		$array1 = array_slice($array, 0, $halfway);
		$array2 = array_slice($array, $halfway);
		// Recurse to sort the two halves
		$this->mergesort($array1, $cmpFunction);
		$this->mergesort($array2, $cmpFunction);
		// If all of $array1 is <= all of $array2, just append them.
		if(call_user_func($cmpFunction, end($array1), reset($array2)) < 1) {
			$array = array_merge($array1, $array2);
			return;
		}
		// Merge the two sorted arrays into a single sorted array
		$array = array();
		$val1 = reset($array1);
		$val2 = reset($array2);
		do {
			if (call_user_func($cmpFunction, $val1, $val2) < 1) {
				$array[key($array1)] = $val1;
				$val1 = next($array1);
			} else {
				$array[key($array2)] = $val2;
				$val2 = next($array2);
			}
		} while($val1 && $val2);
		
		// Merge the remainder
		while($val1) {
			$array[key($array1)] = $val1;
			$val1 = next($array1);
		}
		while($val2) {
			$array[key($array2)] = $val2;
			$val2 = next($array2);
		}
		return;
	}
	
	/**
	 * Set a WHERE clause.
	 * 
	 * @see SQLQuery::addWhere() for syntax examples
	 *
	 * @param mixed $where Predicate(s) to set, as escaped SQL statements or paramaterised queries
	 * @param mixed $where,... Unlimited additional predicates
	 * @return SQLQuery
	 */
	public function setWhere($where) {
		$where = func_num_args() > 1 ? func_get_args() : $where;
		$this->where = array();
		return $this->addWhere($where);
	}

	/**
	 * Adds a WHERE clause.
	 * 
	 * Note that the database will execute any parameterised queries using
	 * prepared statements whenever available.
	 *
	 * There are several different ways of doing this.
	 *
	 * <code>
	 *  // the entire predicate as a single string
	 *  $query->addWhere("Column = 'Value'");
	 *
	 *  // multiple predicates as an array
	 *  $query->addWhere(array("Column = 'Value'", "Column != 'Value'"));
	 * 
	 *  // Shorthand for the above using argument expansion
	 *  $query->addWhere("Column = 'Value'", "Column != 'Value'");
	 * 
	 *  // multiple predicates with parameters
	 *  $query->addWhere(array("Column = ?" => $column, "Name = ?" => $value)));
	 * 
	 *  // Shorthand for simple column comparison (as above), omitting the '?'
	 *  $query->addWhere(array("Column" => $column, "Name" => $value)));
	 * 
	 *  // Multiple predicates, each with multiple parameters.
	 *  $query->addWhere(array(
	 *		"ColumnOne = ? OR ColumnTwo != ?" => array(1, 4),
	 *		"ID != ?" => $value
	 *	));
	 * 
	 *	// Using a dynamically generated condition (any object that implements SQLConditionGroup)
	 *	$condition = new ObjectThatImplements_SQLConditionGroup();
	 *	$query->addWhere($condition);
	 * 
	 * </code>
	 * 
	 * Note that if giving multiple parameters for a single predicate the array
	 * of values must be given as an indexed array, not an associative array.
	 * 
	 * Also should be noted is that any null values for parameters may give unexpected
	 * behaviour. array('Column' => NULL) is shorthand for array('Column = ?', NULL), and
	 * will not match null values for that column, as 'Column IS NULL' is the correct syntax.
	 * 
	 * Additionally, be careful of key conflicts. Adding two predicates with the same
	 * condition but different parameters can cause a key conflict if added in the same array.
	 * This can be solved by wrapping each individual condition in an array. E.g.
	 * 
	 * <code>
	 * // Multiple predicates with duplicate conditions
	 *  $query->addWhere(array(
	 *		array('ID != ?' => 5),
	 *		array('ID != ?' => 6)
	 *	));
	 * 
	 * // Alternatively this can be added in two separate calls to addWhere
	 * $query->addWhere(array('ID != ?' => 5));
	 * $query->addWhere(array('ID != ?' => 6));
	 * 
	 * // Or simply omit the outer array
	 * $query->addWhere(array('ID != ?' => 5), array('ID != ?' => 6));
	 * </code>
	 * 
	 * If it's necessary to force the parameter to be considered as a specific data type
	 * by the database connector's prepared query processor any parameter can be cast
	 * to that type by using the following format.
	 * 
	 * <code>
	 *  // Treat this value as a double type, regardless of its type within PHP
	 *  $query->addWhere(array(
	 *		'Column' => array(
	 *			'value' => $variable,
	 *			'type' => 'double'
	 *		)
	 *	));
	 * </code>
	 *
	 * @param mixed $where Predicate(s) to set, as escaped SQL statements or paramaterised queries
	 * @param mixed $where,... Unlimited additional predicates
	 * @return SQLQuery
	 */
	public function addWhere($where) {
		$where = $this->normalisePredicates(func_get_args());
		
		// If the function is called with an array of items
		$this->where = array_merge($this->where, $where);
		
		return $this;
	}

	/**
	 * @deprecated since version 3.0
	 */
	public function where($where) {
		Deprecation::notice('3.0', 'Please use setWhere() or addWhere() instead!');
		return $this->setWhere($where);
	}

	/**
	 * @deprecated since version 3.0
	 */
	public function whereAny($where) {
		Deprecation::notice('3.0', 'Please use setWhereAny() or setWhereAny() instead!');
		return $this->setWhereAny($where);
	}

	/**
	 * @see SQLQuery::addWhere()
	 * 
	 * @param mixed $filters Predicate(s) to set, as escaped SQL statements or paramaterised queries
	 * @param mixed $filters,... Unlimited additional predicates
	 * @return SQLQuery
	 */
	public function setWhereAny($filters) {
		$filters = func_num_args() > 1 ? func_get_args() : $filters;
		return $this
			->setWhere(array())
			->addWhereAny($filters);
	}
	
	/**
	 * @see SQLQuery::addWhere()
	 * 
	 * @param mixed $filters Predicate(s) to set, as escaped SQL statements or paramaterised queries
	 * @param mixed $filters,... Unlimited additional predicates
	 * @return SQLQuery
	 */
	public function addWhereAny($filters) {
		
		// Parse and split predicates along with any parameters
		$filters = $this->normalisePredicates(func_get_args());
		$this->splitQueryParameters($filters, $predicates, $parameters);
				
		$clause = "(".implode(") OR (", $predicates).")";
		return $this->addWhere(array($clause => $parameters));
	}

	/**
	 * Return a list of WHERE clauses used internally.
	 * 
	 * @return array
	 */
	public function getWhere() {
		return $this->where;
	}
	
	/**
	 * Return a list of WHERE clauses used internally.
	 * 
	 * @param array $parameters Out variable for parameters required for this query
	 * @return array
	 */
	public function getWhereParameterised(&$parameters) {
		$this->splitQueryParameters($this->where, $predicates, $parameters);
		return $predicates;
	}
	
	/**
	 * Given a key / value pair, extract the predicate and any potential paramaters
	 * in a format suitable for storing internally as a list of paramaterised conditions.
	 * 
	 * @param string|integer $key The left hand (key index) of this condition.
	 * Could be the predicate or an integer index.
	 * @param mixed $value The The right hand (array value) of this condition.
	 * Could be the predicate (if non-paramaterised), or the parameter(s). Could also be
	 * an array containing a nested condition in the similar format this function outputs.
	 * @return array|SQLConditionGroup A single item array in the format
	 * array($predicate => array($parameters)), unless it's a SQLConditionGroup
	 */
	protected function parsePredicate($key, $value) {
		
		// If a string key is given then presume this is a paramaterised condition
		if($value instanceof SQLConditionGroup) {
			return $value;
		} elseif(is_string($key)) {
			
			// Extract the parameter(s) from the value
			if(!is_array($value) || isset($value['type'])) {
				$parameters = array($value);
			} else {
				$parameters = $value;
			}

			// Append '= ?' if not present, parameters are given, and we have exactly one parameter
			if(strpos($key, '?') === FALSE) {
				$parameterCount = count($parameters);
				if($parameterCount === 1) {
					$key .= " = ?";
				} elseif($parameterCount > 1) {
					user_error("Incorrect number of '?' in predicate $key. Expected $parameterCount but none given.", E_USER_ERROR);
				}
			}
			return array($key => $parameters);
		} elseif(is_array($value)) {
			
			// If predicates are nested one per array (as per the internal format)
			// then run a quick check over the contents and recursively parse
			if(count($value) != 1) {
				user_error('Nested predicates should be given as a single item array in array($predicate => array($prameters)) format)', E_USER_ERROR);
			}
			foreach($value as $key => $value) {
				return $this->parsePredicate($key, $value);
			}
		} else {
			// Non-paramaterised condition
			return array($value => array());
		}
	}
	
	/**
	 * Given a list of conditions in any user-acceptable format, convert this
	 * to an array of paramaterised predicates suitable for merging with $this->where.
	 *
	 * Normalised predicates are in the below format, in order to avoid key collisions.
	 * 
	 * <code>
	 * array(
	 *	array('Condition != ?' => array('parameter')),
	 *	array('Condition != ?' => array('otherparameter')),
	 *	array('Condition = 3' => array()),
	 *	array('Condition = ? OR Condition = ?' => array('parameter1', 'parameter2))
	 * )
	 * </code>
	 * 
	 * @param array $predicates List of predicates. These should be wrapped in an array
	 * one level more than for addWhere, as query expansion is not supported here.
	 * @return array List of normalised predicates
	 */
	protected function normalisePredicates(array $predicates) {
		// Since this function is called with func_get_args we should un-nest the single first parameter
		if(count($predicates) == 1) $predicates = array_shift($predicates);
		
		// Ensure single predicates are iterable
		if(!is_array($predicates)) $predicates = array($predicates);
		
		$normalised = array();
		foreach($predicates as $key => $value) {
			if(empty($value) && (empty($key) || is_numeric($key))) continue; // Ignore empty conditions
			$normalised[] = $this->parsePredicate($key, $value);
		}
		
		return $normalised;
	}
	
	/**
	 * Given a list of conditions as per the format of $this->where, split
	 * this into an array of predicates, and a separate array of ordered parameters
	 * 
	 * Note, that any SQLConditionGroup objects will be evaluated here.
	 * @see SQLConditionGroup
	 * 
	 * @param array $conditions List of Conditions including parameters
	 * @param array $predicates Out parameter for the list of string predicates
	 * @param array $parameters Out parameter for the list of parameters
	 */
	public function splitQueryParameters($conditions, &$predicates, &$parameters) {
		
		// Merge all filters with paramaterised queries
		$predicates = array();
		$parameters = array();
		foreach($conditions as $condition) {
			// Evaluate the result of SQLConditionGroup here
			if($condition instanceof SQLConditionGroup) {
				$conditionSQL = $condition->conditionSQL($conditionParameters);
				if(!empty($conditionSQL)) {
					$predicates[] = $conditionSQL;
					$parameters = array_merge($parameters, $conditionParameters);
				}
			} else {
				foreach($condition as $key => $value) {
					$predicates[] = $key;
					$parameters = array_merge($parameters, $value);
				}
			}
		}
	}
	
	/**
	 * Checks whether this query is for a specific ID in a table
	 * 
	 * @todo Doesn't work with combined statements (e.g. "Foo='bar' AND ID=5")
	 *
	 * @return boolean
	 */
	public function filtersOnID() {
		$regexp = '/^(.*\.)?("|`)?ID("|`)?\s?=/';
		
		// @todo - Test this works with paramaterised queries
		foreach($this->getWhereParameterised($parameters) as $predicate) {
			if(preg_match($regexp, $predicate)) return true;
		}
		
		return false;
	}
	
	/**
	 * Checks whether this query is filtering on a foreign key, ie finding a has_many relationship
	 * 
	 * @todo Doesn't work with combined statements (e.g. "Foo='bar' AND ParentID=5")
	 *
	 * @return boolean
	 */
	public function filtersOnFK() {
		$regexp = '/^(.*\.)?("|`)?[a-zA-Z]+ID("|`)?\s?=/';
		
		// @todo - Test this works with paramaterised queries
		foreach($this->getWhereParameterised($parameters) as $predicate) {
			if(preg_match($regexp, $predicate)) return true;
		}
		
		return false;
	}
	
	public function isEmpty() {
		return empty($this->from);
	}
	
	/**
	 * Generates an SQLDelete object using the currently specified parameters
	 * 
	 * @return SQLDelete
	 */
	public function toDelete() {
		$delete = new SQLDelete();
        $this->copyTo($delete);
        return $delete;
	}
    
    /**
	 * Generates an SQLQuery object using the currently specified parameters.
	 * 
	 * @return SQLQuery
	 */
    public function toSelect() {
		$select = new SQLQuery();
        $this->copyTo($select);
        return $select;
    }
    
    /**
	 * Generates an SQLUpdate object using the currently specified parameters.
     * No fields will have any assigned values for the newly generated SQLUpdate
     * object.
     * 
	 * @return SQLUpdate
	 */
    public function toUpdate() {
		$update = new SQLUpdate();
        $this->copyTo($update);
        return $update;
    }
}