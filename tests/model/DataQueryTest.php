<?php

class DataQueryTest extends SapphireTest {

	protected $extraDataObjects = array(
		'DataQueryTest_A',
		'DataQueryTest_B',
		'DataQueryTest_D',
	);

	/**
	 * Test the leftJoin() and innerJoin method of the DataQuery object
	 */
	public function testJoins() {
		$dq = new DataQuery('Member');
		$dq->innerJoin("Group_Members", "\"Group_Members\".\"MemberID\" = \"Member\".\"ID\"");
		$this->assertSQLContains("INNER JOIN \"Group_Members\" ON \"Group_Members\".\"MemberID\" = \"Member\".\"ID\"",
			$dq->sql($parameters));

		$dq = new DataQuery('Member');
		$dq->leftJoin("Group_Members", "\"Group_Members\".\"MemberID\" = \"Member\".\"ID\"");
		$this->assertSQLContains("LEFT JOIN \"Group_Members\" ON \"Group_Members\".\"MemberID\" = \"Member\".\"ID\"",
			$dq->sql($parameters));
	}

	public function testRelationReturn() {
		$dq = new DataQuery('DataQueryTest_C');
		$this->assertEquals('DataQueryTest_A', $dq->applyRelation('TestA'),
			'DataQuery::applyRelation should return the name of the related object.');
		$this->assertEquals('DataQueryTest_A', $dq->applyRelation('TestAs'),
			'DataQuery::applyRelation should return the name of the related object.');
		$this->assertEquals('DataQueryTest_A', $dq->applyRelation('ManyTestAs'),
			'DataQuery::applyRelation should return the name of the related object.');

		$this->assertEquals('DataQueryTest_B', $dq->applyRelation('TestB'),
			'DataQuery::applyRelation should return the name of the related object.');
		$this->assertEquals('DataQueryTest_B', $dq->applyRelation('TestBs'),
			'DataQuery::applyRelation should return the name of the related object.');
		$this->assertEquals('DataQueryTest_B', $dq->applyRelation('ManyTestBs'),
			'DataQuery::applyRelation should return the name of the related object.');
	}

	public function testRelationOrderWithCustomJoin() {
		$dataQuery = new DataQuery('DataQueryTest_B');
		$dataQuery->innerJoin('DataQueryTest_D', '"DataQueryTest_D"."RelationID" = "DataQueryTest_B"."ID"');
		$dataQuery->execute();
	}

	public function testDisjunctiveGroup() {
		$dq = new DataQuery('DataQueryTest_A');

		$dq->where('DataQueryTest_A.ID = 2');
		$subDq = $dq->disjunctiveGroup();
		$subDq->where('DataQueryTest_A.Name = \'John\'');
		$subDq->where('DataQueryTest_A.Name = \'Bob\'');

		$this->assertSQLContains(
			"WHERE (DataQueryTest_A.ID = 2) AND ((DataQueryTest_A.Name = 'John') OR (DataQueryTest_A.Name = 'Bob'))", 
			$dq->sql($parameters)
		);
	}

	public function testConjunctiveGroup() {
		$dq = new DataQuery('DataQueryTest_A');

		$dq->where('DataQueryTest_A.ID = 2');
		$subDq = $dq->conjunctiveGroup();
		$subDq->where('DataQueryTest_A.Name = \'John\'');
		$subDq->where('DataQueryTest_A.Name = \'Bob\'');

		$this->assertSQLContains(
			"WHERE (DataQueryTest_A.ID = 2) AND ((DataQueryTest_A.Name = 'John') AND (DataQueryTest_A.Name = 'Bob'))", 
			$dq->sql($parameters)
		);
	}

	/**
	 * @todo Test paramaterised
	 */
	public function testNestedGroups() {
		$dq = new DataQuery('DataQueryTest_A');

		$dq->where('DataQueryTest_A.ID = 2');
		$subDq = $dq->disjunctiveGroup();
		$subDq->where('DataQueryTest_A.Name = \'John\'');
		$subSubDq = $subDq->conjunctiveGroup();
		$subSubDq->where('DataQueryTest_A.Age = 18');
		$subSubDq->where('DataQueryTest_A.Age = 50');
		$subDq->where('DataQueryTest_A.Name = \'Bob\'');

		$this->assertSQLContains(
			"WHERE (DataQueryTest_A.ID = 2) AND ((DataQueryTest_A.Name = 'John') OR ((DataQueryTest_A.Age = 18) "
				. "AND (DataQueryTest_A.Age = 50)) OR (DataQueryTest_A.Name = 'Bob'))", 
			$dq->sql($parameters)
		);
	}

	public function testEmptySubgroup() {
		$dq = new DataQuery('DataQueryTest_A');
		$dq->conjunctiveGroup();

		// Empty groups should have no where condition at all
		$this->assertSQLNotContains('WHERE', $dq->sql($parameters));
	}

	public function testSubgroupHandoff() {
		$dq = new DataQuery('DataQueryTest_A');
		$subDq = $dq->disjunctiveGroup();

		$orgDq = clone $dq;

		$subDq->sort('"DataQueryTest_A"."Name"');
		$orgDq->sort('"DataQueryTest_A"."Name"');

		$this->assertSQLEquals($dq->sql($parameters), $orgDq->sql($parameters));

		$subDq->limit(5, 7);
		$orgDq->limit(5, 7);

		$this->assertSQLEquals($dq->sql($parameters), $orgDq->sql($parameters));
	}
}


class DataQueryTest_A extends DataObject implements TestOnly {
	private static $db = array(
		'Name' => 'Varchar',
	);

	private static $has_one = array(
		'TestC' => 'DataQueryTest_C',
	);
}

class DataQueryTest_B extends DataQueryTest_A {
	private static $db = array(
		'Title' => 'Varchar',
	);

	private static $has_one = array(
		'TestC' => 'DataQueryTest_C',
	);
}

class DataQueryTest_C extends DataObject implements TestOnly {

	private static $has_one = array(
		'TestA' => 'DataQueryTest_A',
		'TestB' => 'DataQueryTest_B',
	);

	private static $has_many = array(
		'TestAs' => 'DataQueryTest_A',
		'TestBs' => 'DataQueryTest_B',
	);

	private static $many_many = array(
		'ManyTestAs' => 'DataQueryTest_A',
		'ManyTestBs' => 'DataQueryTest_B',
	);
}

class DataQueryTest_D extends DataObject implements TestOnly {

	private static $has_one = array(
		'Relation' => 'DataQueryTest_B',
	);
}
