# SQL Select

## Introduction

An object representing a SQL select query, which can be serialized into a SQL statement. 
It is easier to deal with object-wrappers than string-parsing a raw SQL-query. 
This object is used by the SilverStripe ORM internally.

Dealing with low-level SQL is not encouraged, since the ORM provides
powerful abstraction APIs (see [datamodel](/topics/datamodel). 
Starting with SilverStripe 3, records in collections are lazy loaded,
and these collections have the ability to run efficient SQL
such as counts or returning a single column.

For example, if you want to run a simple `COUNT` SQL statement,
the following three statements are functionally equivalent:

	:::php
	// Through raw SQL
	$count = DB::query('SELECT COUNT(*) FROM "Member"')->value();
	// Through SQLSelect abstraction layer
	$query = new SQLSelect();
	$count = $query->setFrom('Member')->setSelect('COUNT(*)')->value();
	// Through the ORM
	$count = Member::get()->count();

If you do use raw SQL, you'll run the risk of breaking 
various assumptions the ORM and code based on it have:

*  Custom getters/setters (object property can differ from database column)
*  DataObject hooks like onBeforeWrite() and onBeforeDelete()
*  Automatic casting
*  Default values set through objects
*  Database abstraction

We'll explain some ways to use *SELECT* with the full power of SQL, 
but still maintain a connection to the ORM where possible.

<div class="warning" markdown="1">
Please read our ["security" topic](/topics/security) to find out
how to sanitize user input before using it in SQL queries.
</div>

## Usage

### SELECT

	:::php
	$sqlSelect = new SQLSelect();
	$sqlSelect->setFrom('Player');
	$sqlSelect->selectField('FieldName', 'Name');
	$sqlSelect->selectField('YEAR("Birthday")', 'Birthyear');
	$sqlSelect->addLeftJoin('Team','"Player"."TeamID" = "Team"."ID"');
	$sqlSelect->addWhere(array('YEAR("Birthday") = ?' => 1982));
	// $sqlSelect->setOrderBy(...);
	// $sqlSelect->setGroupBy(...);
	// $sqlSelect->setHaving(...);
	// $sqlSelect->setLimit(...);
	// $sqlSelect->setDistinct(true);
	
	// Get the raw SQL (optional)
	$rawSQL = $sqlSelect->sql();
	
	// Execute and return a Query object
	$result = $sqlSelect->execute();

	// Iterate over results
	foreach($result as $row) {
	  echo $row['BirthYear'];
	}

The result is an array lightly wrapped in a database-specific subclass of `[api:Query]`. 
This class implements the *Iterator*-interface, and provides convenience-methods for accessing the data.

### DELETE

	:::php
	$sqlDelete = $sqlSelect->toDelete();

### INSERT/UPDATE

Use SQLInsert or SQLUpdate to perform write operations

	:::php
	SQLUpdate::create('"Player")
		->setAssignments(array('"Status"' => 'Active'))
		->setWhere(array('"Status"' => 'Inactive'))
		->execute();
	SQLInsert::create('"Player")
		->assign('"Name"', 'Andrew')
		->execute();

### Value Checks

Raw SQL is handy for performance-optimized calls,
e.g. when you want a single column rather than a full-blown object representation.

Example: Get the count from a relationship.

	:::php
	$sqlSelect = new SQLSelect();
  $sqlSelect->setFrom('Player');
  $sqlSelect->addSelect('COUNT("Player"."ID")');
  $sqlSelect->addWhere(array('"Team"."ID"' => 99));
  $sqlSelect->addLeftJoin('Team', '"Team"."ID" = "Player"."TeamID"');
  $count = $sqlSelect->execute()->value();

Note that in the ORM, this call would be executed in an efficient manner as well:

	:::php
	$count = $myTeam->Players()->count();

### Mapping

Creates a map based on the first two columns of the query result. 
This can be useful for creating dropdowns.

Example: Show player names with their birth year, but set their birth dates as values.

	:::php
	$sqlSelect = new SQLSelect();
	$sqlSelect->setFrom('Player');
	$sqlSelect->setSelect('Birthdate');
	$sqlSelect->selectField('CONCAT("Name", ' - ', YEAR("Birthdate")', 'NameWithBirthyear');
	$map = $sqlSelect->execute()->map();
	$field = new DropdownField('Birthdates', 'Birthdates', $map);

Note that going through SQLSelect is just necessary here 
because of the custom SQL value transformation (`YEAR()`). 
An alternative approach would be a custom getter in the object definition.

	:::php
	class Player extends DataObject {
		static $db = array(
			'Name' => 
			'Birthdate' => 'Date'
		);
		function getNameWithBirthyear() {
			return date('y', $this->Birthdate);
		}
	}
	$players = Player::get();
	$map = $players->map('Name', 'NameWithBirthyear');

## Related

*  [datamodel](/topics/datamodel)
*  `[api:DataObject]`
*  [database-structure](database-structure)
