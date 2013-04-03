<?php
/**
 * Selects numerical/date content smaller than the input
 *
 * @todo documentation
 * 
 * @package framework
 * @subpackage search
 */
class LessThanFilter extends SearchFilter {
	
	/**
	 * @return DataQuery
	 */
	protected function applyOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);

		$predicate = sprintf("%s < ?", $this->getDbName());
		return $query->where(array(
			$predicate => $this->getDbFormattedValue()
		));
	}

	/**
	 * @return DataQuery
	 */
	protected function excludeOne(DataQuery $query) {
		$this->model = $query->applyRelation($this->relation);

		$predicate = sprintf("%s >= ?", $this->getDbName());
		return $query->where(array(
			$predicate => $this->getDbFormattedValue()
		));
	}
	
	public function isEmpty() {
		return $this->getValue() === array() || $this->getValue() === null || $this->getValue() === '';
	}
}
