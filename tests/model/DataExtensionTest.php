<?php

class DataExtensionTest extends SapphireTest {
	protected static $fixture_file = 'DataExtensionTest.yml';
	
	protected $extraDataObjects = array(
		'DataExtensionTest_Member',
		'DataExtensionTest_Player',
		'DataExtensionTest_RelatedObject',
		'DataExtensionTest_MyObject',
	);
	
	protected $requiredExtensions = array(
		'DataObject' => array( 'DataExtensionTest_AppliedToDO' ),
	);
	
	public function testOneToManyAssociationWithExtension() {
		$contact = new DataExtensionTest_Member();
		$contact->Website = "http://www.example.com";
		
		$object = new DataExtensionTest_RelatedObject();
		$object->FieldOne = "Lorem ipsum dolor";
		$object->FieldTwo = "Random notes";
		
		// The following code doesn't currently work:
		// $contact->RelatedObjects()->add($object);
		// $contact->write();
		
		// Instead we have to do the following
		$contact->write();
		$object->ContactID = $contact->ID;
		$object->write();

		$contactID = $contact->ID;
		unset($contact);
		unset($object);
		
		$contact = DataObject::get_one("DataExtensionTest_Member", array(
			'"DataExtensionTest_Member"."Website"' => 'http://www.example.com'
		));
		$object = DataObject::get_one('DataExtensionTest_RelatedObject', array(
			'"DataExtensionTest_RelatedObject"."ContactID"' => $contactID
		));

		$this->assertNotNull($object, 'Related object not null');
		$this->assertInstanceOf('DataExtensionTest_Member', $object->Contact(),
			'Related contact is a member dataobject');
		$this->assertInstanceOf('DataExtensionTest_Member', $object->getComponent('Contact'),
			'getComponent does the same thing as Contact()');
		
		$this->assertInstanceOf('DataExtensionTest_RelatedObject', $contact->RelatedObjects()->First());
		$this->assertEquals("Lorem ipsum dolor", $contact->RelatedObjects()->First()->FieldOne);
		$this->assertEquals("Random notes", $contact->RelatedObjects()->First()->FieldTwo);
		$contact->delete();
	}
	
	public function testManyManyAssociationWithExtension() {
		$parent = new DataExtensionTest_MyObject();
		$parent->Title = 'My Title';
		$parent->write();
		
		$this->assertEquals(0, $parent->Faves()->Count());
		
		$obj1 = $this->objFromFixture('DataExtensionTest_RelatedObject', 'obj1');
		$obj2 = $this->objFromFixture('DataExtensionTest_RelatedObject', 'obj2');

		$parent->Faves()->add($obj1->ID);
		$this->assertEquals(1, $parent->Faves()->Count());
		
		$parent->Faves()->add($obj2->ID);
		$this->assertEquals(2, $parent->Faves()->Count());
		
		$parent->Faves()->removeByID($obj2->ID);
		$this->assertEquals(1, $parent->Faves()->Count());
	}
	
	/**
	 * Test {@link Object::add_extension()} has loaded DataExtension statics correctly.
	 */
	public function testAddExtensionLoadsStatics() {
		// Object::add_extension() will load DOD statics directly, so let's try adding a extension on the fly
		DataExtensionTest_Player::add_extension('DataExtensionTest_PlayerExtension');
		
		// Now that we've just added the extension, we need to rebuild the database
		$this->resetDBSchema(true);
		
		// Create a test record with extended fields, writing to the DB
		$player = new DataExtensionTest_Player();
		$player->setField('Name', 'Joe');
		$player->setField('DateBirth', '1990-5-10');
		$player->Address = '123 somewhere street';
		$player->write();

		unset($player);

		// Pull the record out of the DB and examine the extended fields
		$player = DataObject::get_one('DataExtensionTest_Player', array(
			'"DataExtensionTest_Player"."Name"' => 'Joe'
		));
		$this->assertEquals($player->DateBirth, '1990-05-10');
		$this->assertEquals($player->Address, '123 somewhere street');
		$this->assertEquals($player->Status, 'Goalie');
	}
	
	/**
	 * Test that DataObject::$api_access can be set to true via a extension
	 */
	public function testApiAccessCanBeExtended() {
		$this->assertTrue(Config::inst()->get('DataExtensionTest_Member', 'api_access', Config::FIRST_SET));
	}
	
	public function testPermissionExtension() {
		// testing behaviour in isolation, too many sideeffects and other checks
		// in SiteTree->can*() methods to test one single feature reliably with them

		$obj = $this->objFromFixture('DataExtensionTest_MyObject', 'object1');
		$websiteuser = $this->objFromFixture('Member', 'websiteuser');
		$admin = $this->objFromFixture('Member', 'admin');
		
		$this->assertFalse(
			$obj->canOne($websiteuser),
			'Both extensions return true, but original method returns false'
		);

		$this->assertFalse(
			$obj->canTwo($websiteuser),
			'One extension returns false, original returns true, but extension takes precedence'
		);
		
		$this->assertTrue(
			$obj->canThree($admin),
			'Undefined extension methods returning NULL dont influence the original method'
		);

	}
	
	public function testPopulateDefaults() {
		$obj = new DataExtensionTest_Member();
		$this->assertEquals(
			$obj->Phone,
			'123',
			'Defaults can be populated through extension'
		);
	}

	/**
	 * Test that DataObject::dbObject() works for fields applied by a extension
	 */
	public function testDbObjectOnExtendedFields() {
		$member = $this->objFromFixture('DataExtensionTest_Member', 'member1');
		$this->assertNotNull($member->dbObject('Website'));
		$this->assertInstanceOf('Varchar', $member->dbObject('Website'));
	}	
	
	public function testExtensionCanBeAppliedToDataObject() {
		$do = new DataObject();
		$mo = new DataExtensionTest_MyObject();

		$this->assertTrue($do->hasMethod('testMethodApplied'));
		$this->assertTrue($mo->hasMethod('testMethodApplied'));

		$this->assertEquals("hello world", $mo->testMethodApplied());
		$this->assertEquals("hello world", $do->testMethodApplied());
	}
}

class DataExtensionTest_Member extends DataObject implements TestOnly {
	
	private static $db = array(
		"Name" => "Varchar",
		"Email" => "Varchar"
	);
	
}

class DataExtensionTest_Player extends DataObject implements TestOnly {

	private static $db = array(
		'Name' => 'Varchar'
	);
	
}

class DataExtensionTest_PlayerExtension extends DataExtension implements TestOnly {
	
	public static function get_extra_config($class = null, $extensionClass = null, $args = null) {
		$config = array();

		// Only add these extensions if the $class is set to DataExtensionTest_Player, to
		// test that the argument works.
		if($class == 'DataExtensionTest_Player') {
			$config['db'] = array(
				'Address' => 'Text',
				'DateBirth' => 'Date',
				'Status' => "Enum('Shooter,Goalie')"
			);
			$config['defaults'] = array(
				'Status' => 'Goalie'
			);
		}

		return $config;
	}
	
}

class DataExtensionTest_ContactRole extends DataExtension implements TestOnly {

	private static $db = array(
		'Website' => 'Varchar',
		'Phone' => 'Varchar(255)',
	);

	private static $has_many = array(
		'RelatedObjects' => 'DataExtensionTest_RelatedObject'
	);

	private static $defaults = array(
		'Phone' => '123'
	);

	private static $api_access = true;

}

class DataExtensionTest_RelatedObject extends DataObject implements TestOnly {
	
	private static $db = array(
		"FieldOne" => "Varchar",
		"FieldTwo" => "Varchar"
	);
	
	private static $has_one = array(
		"Contact" => "DataExtensionTest_Member"
	);
	
}

DataExtensionTest_Member::add_extension('DataExtensionTest_ContactRole');

class DataExtensionTest_MyObject extends DataObject implements TestOnly {
	
	private static $db = array(
		'Title' => 'Varchar', 
	);
	
	public function canOne($member = null) {
		// extended access checks
		$results = $this->extend('canOne', $member);
		if($results && is_array($results)) if(!min($results)) return false;
		
		return false;
	}
	
	public function canTwo($member = null) {
		// extended access checks
		$results = $this->extend('canTwo', $member);
		if($results && is_array($results)) if(!min($results)) return false;
		
		return true;
	}
	
	public function canThree($member = null) {
		// extended access checks
		$results = $this->extend('canThree', $member);
		if($results && is_array($results)) if(!min($results)) return false;
		
		return true;
	}
}

class DataExtensionTest_Ext1 extends DataExtension implements TestOnly {
	
	public function canOne($member = null) {
		return true;
	}
	
	public function canTwo($member = null) {
		return false;
	}
	
	public function canThree($member = null) {
	}
	
}

class DataExtensionTest_Ext2 extends DataExtension implements TestOnly {
	
	public function canOne($member = null) {
		return true;
	}
	
	public function canTwo($member = null) {
		return true;
	}
	
	public function canThree($member = null) {
	}
	
}

class DataExtensionTest_Faves extends DataExtension implements TestOnly {

	private static $many_many = array(
		'Faves' => 'DataExtensionTest_RelatedObject'
	);

}

class DataExtensionTest_AppliedToDO extends DataExtension implements TestOnly {

	public function testMethodApplied() {
		return "hello world";
	}

}

DataExtensionTest_MyObject::add_extension('DataExtensionTest_Ext1');
DataExtensionTest_MyObject::add_extension('DataExtensionTest_Ext2');
DataExtensionTest_MyObject::add_extension('DataExtensionTest_Faves');

