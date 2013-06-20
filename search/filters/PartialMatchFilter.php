<?php
/**
 * @package framework
 * @subpackage search
 */

/**
 * Matches textual content with a LIKE '%keyword%' construct.
 *
 * @package framework
 * @subpackage search
 */
class PartialMatchFilter extends SearchFilter {

	public function setModifiers(array $modifiers) {
		if(($extras = array_diff($modifiers, array('not', 'nocase', 'case'))) != array()) {
			throw new InvalidArgumentException(
				get_class($this) . ' does not accept ' . implode(', ', $extras) . ' as modifiers');
		}

		parent::setModifiers($modifiers);
	}
	
	/**
	 * Apply the match filter to the given variable value
	 * 
	 * @param string $value The raw value
	 * @return string
	 */
	protected function getMatchPattern($value) {
		return "%$value%";
	}
	
	protected function applyOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$where = DB::get_conn()->comparisonClause(
			$this->getDbName(),
			null,
			false, // exact?
			false, // negate?
			$this->getCaseSensitive(),
			true
		);
		return $query->where(array($where => $this->getMatchPattern($this->getValue())));
	}

	protected function applyMany(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$whereClause = array();
		foreach($this->getValue() as $value) {
			$predicate = DB::get_conn()->comparisonClause(
				$this->getDbName(),
				null,
				false, // exact?
				false, // negate?
				$this->getCaseSensitive(),
				true
			);
			$whereClause[] = array($predicate => $this->getMatchPattern($value));
		}
		return $query->whereAny($whereClause);
	}

	protected function excludeOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$where = DB::get_conn()->comparisonClause(
			$this->getDbName(),
			null,
			false, // exact?
			true, // negate?
			$this->getCaseSensitive(),
			true
		);
		return $query->where(array($where => $this->getMatchPattern($this->getValue())));
	}

	protected function excludeMany(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);
		$predicates = array();
		$parameters = array();
		foreach($this->getValue() as $value) {
			$predicates[] = DB::get_conn()->comparisonClause(
				$this->getDbName(),
				null,
				false, // exact?
				true, // negate?
				$this->getCaseSensitive(),
				true
			);
			$parameters[] = $this->getMatchPattern($value);
		}
		return $query->where(array(implode(' AND ', $predicates) => $parameters));
	}
	
	public function isEmpty() {
		return $this->getValue() === array() || $this->getValue() === null || $this->getValue() === '';
	}
}
