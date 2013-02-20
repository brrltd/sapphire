<?php

class DataObjectSchemaGenerationTest extends SapphireTest {
	protected $extraDataObjects = array(
		'DataObjectSchemaGenerationTest_DO',
		'DataObjectSchemaGenerationTest_IndexDO'
	);
	
	public function setUpOnce() {
		
		// enable fulltext option on this table
		Config::inst()->update('DataObjectSchemaGenerationTest_IndexDO', 'create_table_options',
			array(MySQLSchemaManager::ID => 'ENGINE=MyISAM'));
		
		parent::setUpOnce();
	}

	/**
	 * Check that once a schema has been generated, then it doesn't need any more updating
	 */
	public function testFieldsDontRerequestChanges() {
		$schema = DB::getSchema();
		$test = $this;
		DB::quiet();

		// Table will have been initially created by the $extraDataObjects setting
		
		// Verify that it doesn't need to be recreated
		$schema->schemaUpdate(function() use ($test, $schema) {
			$obj = new DataObjectSchemaGenerationTest_DO();
			$obj->requireTable();
			$needsUpdating = $schema->doesSchemaNeedUpdating();
			$schema->cancelSchemaUpdate();
			$test->assertFalse($needsUpdating);
		});
	}

	/**
	 * Check that updates to a class fields are reflected in the database
	 */
	public function testFieldsRequestChanges() {
		$schema = DB::getSchema();
		$test = $this;
		DB::quiet();

		// Table will have been initially created by the $extraDataObjects setting
		
		// Let's insert a new field here
		$oldDB = DataObjectSchemaGenerationTest_DO::$db;
		DataObjectSchemaGenerationTest_DO::$db['SecretField'] = 'Varchar(100)';
		
		// Verify that the above extra field triggered a schema update
		$schema->schemaUpdate(function() use ($test, $schema) {
			$obj = new DataObjectSchemaGenerationTest_DO();
			$obj->requireTable();
			$needsUpdating = $schema->doesSchemaNeedUpdating();
			$schema->cancelSchemaUpdate();
			$test->assertTrue($needsUpdating);
		});
		
		// Restore db configuration
		DataObjectSchemaGenerationTest_DO::$db = $oldDB;
	}
	
	/**
	 * Check that indexes on a newly generated class do not subsequently request modification 
	 */
	public function testIndexesDontRerequestChanges() {
		$schema = DB::getSchema();
		$test = $this;
		DB::quiet();
		
		// Table will have been initially created by the $extraDataObjects setting
		
		// Verify that it doesn't need to be recreated
		$schema->schemaUpdate(function() use ($test, $schema) {
			$obj = new DataObjectSchemaGenerationTest_IndexDO();
			$obj->requireTable();
			$needsUpdating = $schema->doesSchemaNeedUpdating();
			$schema->cancelSchemaUpdate();
			$test->assertFalse($needsUpdating);
		});
		
		// Test with alternate index format, although these indexes are the same
		$oldIndexes = DataObjectSchemaGenerationTest_IndexDO::$indexes;
		DataObjectSchemaGenerationTest_IndexDO::$indexes = DataObjectSchemaGenerationTest_IndexDO::$indexes_alt;
				
		// Verify that it still doesn't need to be recreated
		$schema->schemaUpdate(function() use ($test, $schema) {
			$obj2 = new DataObjectSchemaGenerationTest_IndexDO();
			$obj2->requireTable();
			$needsUpdating = $schema->doesSchemaNeedUpdating();
			$schema->cancelSchemaUpdate();
			$test->assertFalse($needsUpdating);
		});
		
		// Restore old index format
		DataObjectSchemaGenerationTest_IndexDO::$indexes = $oldIndexes;
	}
	
	/**
	 * Check that updates to a dataobject's indexes are reflected in DDL
	 */
	public function testIndexesRerequestChanges() {
		$schema = DB::getSchema();
		$test = $this;
		DB::quiet();
		
		// Table will have been initially created by the $extraDataObjects setting
		
		// Update the SearchFields index here
		$oldIndexes = DataObjectSchemaGenerationTest_IndexDO::$indexes;
		DataObjectSchemaGenerationTest_IndexDO::$indexes['SearchFields']['value'] = '"Title"';
		
		// Verify that the above index change triggered a schema update
		$schema->schemaUpdate(function() use ($test, $schema) {
			$obj = new DataObjectSchemaGenerationTest_IndexDO();
			$obj->requireTable();
			$needsUpdating = $schema->doesSchemaNeedUpdating();
			$schema->cancelSchemaUpdate();
			$test->assertTrue($needsUpdating);
		});
		
		// Restore old indexes
		DataObjectSchemaGenerationTest_IndexDO::$indexes = $oldIndexes;
	}
}

class DataObjectSchemaGenerationTest_DO extends DataObject implements TestOnly {
	static $db = array(
		'Enum1' => 'Enum("A, B, C, D","")',
		'Enum2' => 'Enum("A, B, C, D","A")',
	);
}


class DataObjectSchemaGenerationTest_IndexDO extends DataObjectSchemaGenerationTest_DO implements TestOnly {
	static $db = array(
		'Title' => 'Varchar(255)',
		'Content' => 'Text'
	);

	static $indexes = array(
		'NameIndex' => 'unique ("Title")',
		'SearchFields' => array(
			'type' => 'fulltext',
			'name' => 'SearchFields',
			'value' => '"Title","Content"'
		)
	);
	
	static $indexes_alt = array(
		'NameIndex' => array(
			'type' => 'unique',
			'name' => 'NameIndex',
			'value' => '"Title"'
		),
		'SearchFields' => 'fulltext ("Title","Content")'
	);
}