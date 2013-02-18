<?php

class SQLQueryTest extends SapphireTest {
	
	public static $fixture_file = 'SQLQueryTest.yml';

	protected $extraDataObjects = array(
		'SQLQueryTest_DO',
	);
	
	public function testEmptyQueryReturnsNothing() {
		$query = new SQLQuery();
		$this->assertEquals('', $query->sql($parameters));
	}
	
	public function testSelectFromBasicTable() {
		$query = new SQLQuery();
		$query->setFrom('MyTable');
		$this->assertEquals("SELECT * FROM MyTable", $query->sql($parameters));
		$query->addFrom('MyJoin');
		$this->assertEquals("SELECT * FROM MyTable MyJoin", $query->sql($parameters));
	}
	
	public function testSelectFromUserSpecifiedFields() {
		$query = new SQLQuery();
		$query->setSelect(array("Name", "Title", "Description"));
		$query->setFrom("MyTable");
		$this->assertEquals("SELECT Name, Title, Description FROM MyTable", $query->sql($parameters));
	}
	
	public function testSelectWithWhereClauseFilter() {
		$query = new SQLQuery();
		$query->setSelect(array("Name","Meta"));
		$query->setFrom("MyTable");
		$query->setWhere("Name = 'Name'");
		$query->addWhere("Meta = 'Test'");
		$this->assertEquals("SELECT Name, Meta FROM MyTable WHERE (Name = 'Name') AND (Meta = 'Test')", $query->sql($parameters));
	}
	
	public function testSelectWithConstructorParameters() {
		$query = new SQLQuery(array("Foo", "Bar"), "FooBarTable");
		$this->assertEquals("SELECT Foo, Bar FROM FooBarTable", $query->sql($parameters));
		$query = new SQLQuery(array("Foo", "Bar"), "FooBarTable", array("Foo = 'Boo'"));
		$this->assertEquals("SELECT Foo, Bar FROM FooBarTable WHERE (Foo = 'Boo')", $query->sql($parameters));
	}
	
	public function testSelectWithChainedMethods() {
		$query = new SQLQuery();
		$query->setSelect("Name","Meta")->setFrom("MyTable")->setWhere("Name = 'Name'")->addWhere("Meta = 'Test'");
		$this->assertEquals("SELECT Name, Meta FROM MyTable WHERE (Name = 'Name') AND (Meta = 'Test')", $query->sql($parameters));
	}
	
	public function testCanSortBy() {
		$query = new SQLQuery();
		$query->setSelect("Name","Meta")->setFrom("MyTable")->setWhere("Name = 'Name'")->addWhere("Meta = 'Test'");
		$this->assertTrue($query->canSortBy('Name ASC'));
		$this->assertTrue($query->canSortBy('Name'));
	}
	
	public function testSelectWithChainedFilterParameters() {
		$query = new SQLQuery();
		$query->setSelect(array("Name","Meta"))->setFrom("MyTable");
		$query->setWhere("Name = 'Name'")->addWhere("Meta = 'Test'")->addWhere("Beta != 'Gamma'");
		$this->assertEquals(
			"SELECT Name, Meta FROM MyTable WHERE (Name = 'Name') AND (Meta = 'Test') AND (Beta != 'Gamma')",
			$query->sql($parameters));
	}
	
	public function testSelectWithLimitClause() {
		if(!(DB::getConn() instanceof MySQLDatabase || DB::getConn() instanceof SQLite3Database 
				|| DB::getConn() instanceof PostgreSQLDatabase)) {
			$this->markTestIncomplete();
		}

		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setLimit(99);
		$this->assertEquals("SELECT * FROM MyTable LIMIT 99", $query->sql($parameters));
	
		// array limit with start (MySQL specific)
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setLimit(99, 97);
		$this->assertEquals("SELECT * FROM MyTable LIMIT 99 OFFSET 97", $query->sql($parameters));
	}
	
	public function testSelectWithOrderbyClause() {
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy('MyName');
		$this->assertEquals('SELECT * FROM MyTable ORDER BY MyName ASC', $query->sql($parameters));
		
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy('MyName desc');
		$this->assertEquals('SELECT * FROM MyTable ORDER BY MyName DESC', $query->sql($parameters));
		
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy('MyName ASC, Color DESC');
		$this->assertEquals('SELECT * FROM MyTable ORDER BY MyName ASC, Color DESC', $query->sql($parameters));
		
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy('MyName ASC, Color');
		$this->assertEquals('SELECT * FROM MyTable ORDER BY MyName ASC, Color ASC', $query->sql($parameters));

		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy(array('MyName' => 'desc'));
		$this->assertEquals('SELECT * FROM MyTable ORDER BY MyName DESC', $query->sql($parameters));
		
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy(array('MyName' => 'desc', 'Color'));
		$this->assertEquals('SELECT * FROM MyTable ORDER BY MyName DESC, Color ASC', $query->sql($parameters));
		
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy('implode("MyName","Color")');
		$this->assertEquals(
			'SELECT *, implode("MyName","Color") AS "_SortColumn0" FROM MyTable ORDER BY "_SortColumn0" ASC', 
			$query->sql($parameters));
		
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy('implode("MyName","Color") DESC');
		$this->assertEquals(
			'SELECT *, implode("MyName","Color") AS "_SortColumn0" FROM MyTable ORDER BY "_SortColumn0" DESC',
			$query->sql($parameters));
		
		$query = new SQLQuery();
		$query->setFrom("MyTable");
		$query->setOrderBy('RAND()');
		
		$this->assertEquals(
			'SELECT *, RAND() AS "_SortColumn0" FROM MyTable ORDER BY "_SortColumn0" ASC',
			$query->sql($parameters));
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testNegativeLimit() {
		$query = new SQLQuery();
		$query->setLimit(-10);
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testNegativeOffset() {
		$query = new SQLQuery();
		$query->setLimit(1, -10);
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testNegativeOffsetAndLimit() {
		$query = new SQLQuery();
		$query->setLimit(-10, -10);
	}

	public function testReverseOrderBy() {
		$query = new SQLQuery();
		$query->setFrom('MyTable');
		
		// default is ASC
		$query->setOrderBy("Name");
		$query->reverseOrderBy();

		$this->assertEquals('SELECT * FROM MyTable ORDER BY Name DESC',$query->sql($parameters));	
		
		$query->setOrderBy("Name DESC");
		$query->reverseOrderBy();

		$this->assertEquals('SELECT * FROM MyTable ORDER BY Name ASC',$query->sql($parameters));
		
		$query->setOrderBy(array("Name" => "ASC"));
		$query->reverseOrderBy();
		
		$this->assertEquals('SELECT * FROM MyTable ORDER BY Name DESC',$query->sql($parameters));
		
		$query->setOrderBy(array("Name" => 'DESC', 'Color' => 'asc'));
		$query->reverseOrderBy();
		
		$this->assertEquals('SELECT * FROM MyTable ORDER BY Name ASC, Color DESC',$query->sql($parameters));
		
		$query->setOrderBy('implode("MyName","Color") DESC');
		$query->reverseOrderBy();
		
		$this->assertEquals(
			'SELECT *, implode("MyName","Color") AS "_SortColumn0" FROM MyTable ORDER BY "_SortColumn0" ASC',
			$query->sql($parameters));
	}

	public function testFiltersOnID() {
		$query = new SQLQuery();
		$query->setWhere("ID = 5");
		$this->assertTrue(
			$query->filtersOnID(),
			"filtersOnID() is true with simple unquoted column name"
		);
		
		$query = new SQLQuery();
		$query->setWhere("ID=5");
		$this->assertTrue(
			$query->filtersOnID(),
			"filtersOnID() is true with simple unquoted column name and no spaces in equals sign"
		);

		$query = new SQLQuery();
		$query->setWhere("Identifier = 5");
		$this->assertFalse(
			$query->filtersOnID(),
			"filtersOnID() is false with custom column name (starting with 'id')"
		);
		
		$query = new SQLQuery();
		$query->setWhere("ParentID = 5");
		$this->assertFalse(
			$query->filtersOnID(),
			"filtersOnID() is false with column name ending in 'ID'"
		);
		
		$query = new SQLQuery();
		$query->setWhere("MyTable.ID = 5");
		$this->assertTrue(
			$query->filtersOnID(),
			"filtersOnID() is true with table and column name"
		);
		
		$query = new SQLQuery();
		$query->setWhere("MyTable.ID = 5");
		$this->assertTrue(
			$query->filtersOnID(),
			"filtersOnID() is true with table and quoted column name "
		);
	}
	
	public function testFiltersOnFK() {
		$query = new SQLQuery();
		$query->setWhere("ID = 5");
		$this->assertFalse(
			$query->filtersOnFK(),
			"filtersOnFK() is true with simple unquoted column name"
		);
		
		$query = new SQLQuery();
		$query->setWhere("Identifier = 5");
		$this->assertFalse(
			$query->filtersOnFK(),
			"filtersOnFK() is false with custom column name (starting with 'id')"
		);
		
		$query = new SQLQuery();
		$query->setWhere("MyTable.ParentID = 5");
		$this->assertTrue(
			$query->filtersOnFK(),
			"filtersOnFK() is true with table and column name"
		);
		
		$query = new SQLQuery();
		$query->setWhere("MyTable.`ParentID`= 5");
		$this->assertTrue(
			$query->filtersOnFK(),
			"filtersOnFK() is true with table and quoted column name "
		);
	}

	public function testInnerJoin() {
		$query = new SQLQuery();
		$query->setFrom('MyTable');
		$query->addInnerJoin('MyOtherTable', 'MyOtherTable.ID = 2');
		$query->addLeftJoin('MyLastTable', 'MyOtherTable.ID = MyLastTable.ID');

		$this->assertEquals('SELECT * FROM MyTable '.
			'INNER JOIN "MyOtherTable" ON MyOtherTable.ID = 2 '.
			'LEFT JOIN "MyLastTable" ON MyOtherTable.ID = MyLastTable.ID',
			$query->sql($parameters)
		);

		$query = new SQLQuery();
		$query->setFrom('MyTable');
		$query->addInnerJoin('MyOtherTable', 'MyOtherTable.ID = 2', 'table1');
		$query->addLeftJoin('MyLastTable', 'MyOtherTable.ID = MyLastTable.ID', 'table2');

		$this->assertEquals('SELECT * FROM MyTable '.
			'INNER JOIN "MyOtherTable" AS "table1" ON MyOtherTable.ID = 2 '.
			'LEFT JOIN "MyLastTable" AS "table2" ON MyOtherTable.ID = MyLastTable.ID',
			$query->sql($parameters)
		);
	}
	

	public function testSetWhereAny() {
		$query = new SQLQuery();
		$query->setFrom('MyTable');

		$query->setWhereAny(array("Monkey = 'Chimp'", "Color = 'Brown'"));
		$this->assertEquals("SELECT * FROM MyTable WHERE (Monkey = 'Chimp' OR Color = 'Brown')",$query->sql($parameters));
	}
	
	public function testSelectFirst() {
		
		// Test first from sequence
		$query = new SQLQuery();
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setOrderBy('"Name"');
		$result = $query->firstRow()->execute();
		
		$this->assertCount(1, $result);
		foreach($result as $row) {
			$this->assertEquals('Object 1', $row['Name']);
		}
		
		// Test first from empty sequence
		$query = new SQLQuery();
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setOrderBy('"Name"');
		$query->setWhere(array("\"Name\" = 'Nonexistent Object'"));
		$result = $query->firstRow()->execute();
		$this->assertCount(0, $result);
		
		// Test that given the last item, the 'first' in this list matches the last
		$query = new SQLQuery();
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setOrderBy('"Name"');
		$query->setLimit(1, 1);
		$result = $query->firstRow()->execute();
		$this->assertCount(1, $result);
		foreach($result as $row) {
			$this->assertEquals('Object 2', $row['Name']);
		}
	}
	
	public function testSelectLast() {
		
		// Test last in sequence
		$query = new SQLQuery();
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setOrderBy('"Name"');
		$result = $query->lastRow()->execute();
		
		$this->assertCount(1, $result);
		foreach($result as $row) {
			$this->assertEquals('Object 2', $row['Name']);
		}
		
		// Test last from empty sequence
		$query = new SQLQuery();
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setOrderBy('"Name"');
		$query->setWhere(array("\"Name\" = 'Nonexistent Object'"));
		$result = $query->lastRow()->execute();
		$this->assertCount(0, $result);
		
		// Test that given the first item, the 'last' in this list matches the first
		$query = new SQLQuery();
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setOrderBy('"Name"');
		$query->setLimit(1);
		$result = $query->lastRow()->execute();
		$this->assertCount(1, $result);
		foreach($result as $row) {
			$this->assertEquals('Object 1', $row['Name']);
		}
	}
	
	/**
	 * Tests aggregate() function
	 */
	public function testAggregate() {
		$query = new SQLQuery();
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setGroupBy("Common");
		
		$queryClone = $query->aggregate('COUNT(*)', 'cnt');
		$result = $queryClone->execute();
		$this->assertEquals(array(2), $result->column('cnt'));
	}

	/**
	 * Test that "_SortColumn0" is added for an aggregate in the ORDER BY
	 * clause, in combination with a LIMIT and GROUP BY clause.
	 * For some databases, like MSSQL, this is a complicated scenario
	 * because a subselect needs to be done to query paginated data.
	 */
	public function testOrderByContainingAggregateAndLimitOffset() {
		$query = new SQLQuery();
		$query->setSelect(array('"Name"', '"Meta"'));
		$query->setFrom('"SQLQueryTest_DO"');
		$query->setOrderBy(array('MAX(Date)'));
		$query->setGroupBy(array('"Name"', '"Meta"'));
		$query->setLimit('1', '1');

		$records = array();
		foreach($query->execute() as $record) {
			$records[] = $record;
		}

		$this->assertCount(1, $records);

		$this->assertEquals('Object 2', $records[0]['Name']);
		$this->assertEquals('2012-05-01 09:00:00', $records['0']['_SortColumn0']);
	}

}

class SQLQueryTest_DO extends DataObject implements TestOnly {
	static $db = array(
		"Name" => "Varchar",
		"Meta" => "Varchar",
		"Common" => "Varchar",
		"Date" => "SS_Datetime"
	);
}

class SQLQueryTestBase extends DataObject implements TestOnly {
	static $db = array(
		"Title" => "Varchar",
	);
}

class SQLQueryTestChild extends SQLQueryTestBase {
	static $db = array(
		"Name" => "Varchar",
	);

	static $has_one = array(
	);
}
