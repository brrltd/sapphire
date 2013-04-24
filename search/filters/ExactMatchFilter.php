<?php
/**
 * @package framework
 * @subpackage search
 */

/**
 * Selects textual content with an exact match between columnname and keyword.
 *
 * @todo case sensitivity switch
 * @todo documentation
 * 
 * @package framework
 * @subpackage search
 */
class ExactMatchFilter extends SearchFilter {

	public function setModifiers(array $modifiers) {
		if(($extras = array_diff($modifiers, array('not', 'nocase', 'case'))) != array()) {
			throw new InvalidArgumentException(
				get_class($this) . ' does not accept ' . implode(', ', $extras) . ' as modifiers');
		}

		parent::setModifiers($modifiers);
	}

	/**
	 * Applies an exact match (equals) on a field value.
	 *
	 * @return DataQuery
	 */
	protected function applyOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$where = DB::get_conn()->comparisonClause(
			$this->getDbName(),
			null,
			true, // exact?
			false, // negate?
			$this->getCaseSensitive(),
			true
		);
		return $query->where(array($where => $this->getValue()));
	}

	/**
	 * Applies an exact match (equals) on a field value against multiple
	 * possible values.
	 *
	 * @return DataQuery
	 */
	protected function applyMany(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$whereClause = array();
		foreach($this->getValue() as $value) {
			$predicate = DB::get_conn()->comparisonClause(
				$this->getDbName(),
				null,
				true, // exact?
				false, // negate?
				$this->getCaseSensitive(),
				true
			);
			$whereClause[] = array($predicate => $value);
		}
		return $query->whereAny($whereClause);
	}

	/**
	 * Excludes an exact match (equals) on a field value.
	 *
	 * @return DataQuery
	 */
	protected function excludeOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$where = DB::get_conn()->comparisonClause(
			$this->getDbName(),
			null,
			true, // exact?
			true, // negate?
			$this->getCaseSensitive(),
			true
		);
		return $query->where(array($where => $this->getValue()));
	}

	/**
	 * Excludes an exact match (equals) on a field value against multiple
	 * possible values.
	 *
	 * @return DataQuery
	 */
	protected function excludeMany(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$predicates = array();
		$parameters = array();
		foreach($this->getValue() as $value) {
			$predicates[] = DB::get_conn()->comparisonClause(
				$this->getDbName(),
				null,
				true, // exact?
				true, // negate?
				$this->getCaseSensitive(),
				true
			);
			$parameters[] = $value;
		}
		return $query->where(array(implode(' AND ', $predicates) => $parameters));
	}
	
	public function isEmpty() {
		return $this->getValue() === array() || $this->getValue() === null || $this->getValue() === '';
	}
}
