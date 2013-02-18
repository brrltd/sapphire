<?php

/**
 * Builds a SQL query string from a SQLExpression object
 * 
 * @package framework
 * @subpackage model
 */
class DBQueryBuilder {
	
	/**
	 * Builds a sql query with the specified connection
	 * 
	 * @param SQLExpression $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string The resulting SQL as a string
	 */
	public function buildSQL(SQLExpression $query, &$parameters) {
		$sql = null;
		$parameters = array();
		
		// Ignore null queries
		if($query->isEmpty()) return null;
		
		if($query instanceof SQLQuery) {
			$sql = $this->buildSelectQuery($query, $parameters);
		} elseif($query instanceof SQLDelete) {
			$sql = $this->buildDeleteQuery($query, $parameters);
		} elseif($query instanceof SQLInsert) {
			$sql = $this->buildInsertQuery($query, $parameters);
		} elseif($query instanceof SQLUpdate) {
			$sql = $this->buildUpdateQuery($query, $parameters);
		} else {
			user_error("Not implemented: query generation for type " . $query->getType());
		}
		return $sql;
	}
	
	/**
	 * Builds a query from a SQLQuery expression
	 * 
	 * @param SQLQuery $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed SQL string
	 */
	protected function buildSelectQuery(SQLQuery $query, array &$parameters) {
		$sql  = $this->buildSelectFragment($query, $parameters);
		$sql .= $this->buildFromFragment($query, $parameters);
		$sql .= $this->buildWhereFragment($query, $parameters);
		$sql .= $this->buildGroupByFragment($query, $parameters);
		$sql .= $this->buildHavingFragment($query, $parameters);
		$sql .= $this->buildOrderByFragment($query, $parameters);
		$sql .= $this->buildLimitFragment($query, $parameters);
		return $sql;
	}
	
	/**
	 * Builds a query from a SQLDelete expression
	 * 
	 * @param SQLDelete $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed SQL string
	 */
	protected function buildDeleteQuery(SQLDelete $query, array &$parameters) {
		$sql  = $this->buildDeleteFragment($query, $parameters);
		$sql .= $this->buildFromFragment($query, $parameters);
		$sql .= $this->buildWhereFragment($query, $parameters);
		return $sql;
	}
	
	/**
	 * Builds a query from a SQLInsert expression
	 * 
	 * @param SQLInsert $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed SQL string
	 */
	protected function buildInsertQuery(SQLInsert $query, array &$parameters) {
		
		$into = $query->getInto();
		$sql = "INSERT INTO $into\n";
		
		// Column identifiers
		$columns = $query->getColumns();
		$sql .= " (" . implode(', ', $columns) . ")\n";
		
		// Values
		$sql .= " VALUES\n";
		
		// Build all rows
		$rowParts = array();
		foreach($query->getRows() as $row) {
			// Build all columns in this row
			$assignments = $row->getAssignments();
			// Join SET components together, considering parameters
			$parts = array();
			foreach($columns as $column) {
				// Check if this column has a value for this row
				if(isset($assignments[$column])) {
					// Assigment is a single item array, expand with a loop here
					foreach($assignments[$column] as $assignmentSQL => $assignmentParameters) {
						$parts[] = $assignmentSQL;
						$parameters = array_merge($parameters, $assignmentParameters);
						break;
					}
				} else {
					// This row is missing a value for a column used by another row
					$parts[] = '?';
					$parameters[] = null;
				}
			}
			$rowParts[] = " (" . implode(', ', $parts) . ')';
		}
		$sql .= implode(",\n", $rowParts);
		
		return $sql;
	}
	
	/**
	 * Builds a query from a SQLUpdate expression
	 * 
	 * @param SQLUpdate $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed SQL string
	 */
	protected function buildUpdateQuery(SQLUpdate $query, array &$parameters) {
		$sql  = $this->buildUpdateFragment($query, $parameters);
		$sql .= $this->buildWhereFragment($query, $parameters);
		return $sql;
	}

	/**
	 * Returns the SELECT clauses ready for inserting into a query.
	 * 
	 * @param SQLQuery $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed select part of statement
	 */
	protected function buildSelectFragment(SQLQuery $query, array &$parameters) {
		$distinct = $query->getDistinct();
		$select = $query->getSelect();
		$clauses = array();

		foreach ($select as $alias => $field) {
			// Don't include redundant aliases.
			if ($alias === $field || preg_match('/"' . preg_quote($alias) . '"$/', $field)) {
				$clauses[] = $field;
			} else {
				$clauses[] = "$field AS \"$alias\"";
			}
		}

		$text = 'SELECT ';
		if ($distinct) $text .= 'DISTINCT ';
		return $text .= implode(', ', $clauses) . "\n";
	}

	/**
	 * Return the DELETE clause ready for inserting into a query.
	 * 
	 * @param SQLExpression $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed delete part of statement
	 */
	public function buildDeleteFragment(SQLDelete $query, array &$parameters) {
		$text = 'DELETE';
		
		// If doing a multiple table delete then list the target deletion tables here
		// Note that some schemas don't support multiple table deletion
		$delete = $query->getDelete();
		if(!empty($delete)) {
			$text .= ' ' . implode(', ', $delete);
		}
		return $text . "\n";
	}

	/**
	 * Return the UPDATE clause ready for inserting into a query.
	 * 
	 * @param SQLExpression $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed from part of statement
	 */
	public function buildUpdateFragment(SQLUpdate $query, array &$parameters) {
		$tables = $query->getJoins();
		$text = 'UPDATE ' . implode(' ', $tables) . "\n";
		
		// Join SET components together, considering parameters
		$parts = array();
		foreach($query->getAssignments() as $column => $assignment) {
			// Assigment is a single item array, expand with a loop here
			foreach($assignment as $assignmentSQL => $assignmentParameters) {
				$parts[] = "$column = $assignmentSQL";
				$parameters = array_merge($parameters, $assignmentParameters);
				break;
			}
		}
		$text .= ' SET ' . implode(', ', $parts) . "\n";
		return $text;
	}

	/**
	 * Return the FROM clause ready for inserting into a query.
	 * 
	 * @param SQLExpression $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed from part of statement
	 */
	public function buildFromFragment(SQLExpression $query, array &$parameters) {
		$from = $query->getJoins();
		return ' FROM ' . implode(' ', $from) . "\n";
	}

	/**
	 * Returns the WHERE clauses ready for inserting into a query.
	 * 
	 * @param SQLExpression $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed where condition
	 */
	public function buildWhereFragment(SQLExpression $query, array &$parameters) {
		// Get parameterised elements
		$where = $query->getWhereParameterised($whereParameters);
		if(empty($where)) return '';
		
		// Join conditions
		$connective = $query->getConnective();
		$parameters = array_merge($parameters, $whereParameters);
		return ' WHERE (' . implode(")\n {$connective} (", $where) . ")\n";
	}

	/**
	 * Returns the ORDER BY clauses ready for inserting into a query.
	 * 
	 * @param SQLQuery $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed order by part of statement
	 */
	public function buildOrderByFragment(SQLQuery $query, array &$parameters) {
		$orderBy = $query->getOrderBy();
		if(empty($orderBy)) return '';
		
		// Build orders, each with direction considered
		$statements = array();
		foreach ($orderBy as $clause => $dir) {
			$statements[] = trim("$clause $dir");
		}
		return ' ORDER BY ' . implode(', ', $statements) . "\n";
	}

	/**
	 * Returns the GROUP BY clauses ready for inserting into a query.
	 * 
	 * @param SQLQuery $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string Completed group part of statement
	 */
	public function buildGroupByFragment(SQLQuery $query, array &$parameters) {
		$groupBy = $query->getGroupBy();
		if(empty($groupBy)) return '';
		
		return ' GROUP BY ' . implode(', ', $groupBy) . "\n";
	}

	/**
	 * Returns the HAVING clauses ready for inserting into a query.
	 * 
	 * @param SQLQuery $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string
	 */
	public function buildHavingFragment(SQLQuery $query, array &$parameters) {
		$having = $query->getHavingParameterised($havingParameters);
		if(empty($having)) return '';
		
		// Generate having, considering parameters present
		$connective = $query->getConnective();
		$parameters = array_merge($parameters, $havingParameters);		
		return ' HAVING ( ' . implode(" )\n $connective ( ", $having) . ")\n";
	}

	/**
	 * Return the LIMIT clause ready for inserting into a query.
	 * 
	 * @param SQLQuery $query The expression object to build from
	 * @param array $parameters Out parameter for the resulting query parameters
	 * @return string The finalised limit SQL fragment
	 */
	public function buildLimitFragment(SQLQuery $query, array &$parameters) {
		
		// Ensure limit is given
		$limit = $query->getLimit();
		if(empty($limit)) return '';
		
		// For literal values return this as the limit SQL
		if (!is_array($limit)) {
			return " LIMIT $limit\n";
		}
		
		// Assert that the array version provides the 'limit' key
		if (!isset($limit['limit']) || !is_numeric($limit['limit'])) {
			throw new InvalidArgumentException('DBQueryBuilder::buildLimitSQL(): Wrong format for $limit: '. var_export($limit, true));
		}
		
		// Format the array limit, given an optional start key
		$clause = " LIMIT {$limit['limit']}";
		if(isset($limit['start']) && is_numeric($limit['start'])) {
			$clause .= " OFFSET {$limit['start']}";
		}
		return $clause . "\n";
	}
}