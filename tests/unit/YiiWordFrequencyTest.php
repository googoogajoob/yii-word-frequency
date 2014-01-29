<?php 
//class YiiWordFrequencyTest extends WUnitTestCase {
//class YiiWordFrequencyTest extends CTestCase {
class YiiWordFrequencyTest extends CDbTestCase {
	
	/** DB Fixtures
	 * leave defined but empty to prevent the setUp method from reinitializing the tables before each test
	 * the fixture file init.php now initializes the tables once at the beginning of all tests
	 */
	public $fixtures = array(
		//'testdata' => 'Testdata', 
	);
	
	// Static Class Fixtures
	private $inputFixture = array(
		'This is a test string. This is a second test string',
		array(array('This is a test string.'), array('This is a second test string')),
		'This,is,a,test,string.,This,is,a, second,test,string',
		'This is a test 321 string. This is a second test string 47',
	);

	// Static Class Fixtures
	private $outputFixture = array(
		// 0 - normal count
		array('This' => 2, 'is' => 2, 'a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 1 - double count
		array('This' => 4, 'is' => 4, 'a' => 4, 'test' => 4, 'string.' => 2, 'string' => 2, 'second'=> 2,),
		// 2 - double count sorted ascending
		array('a' => 4,'is' => 4,'second'=> 2,'string' => 2,'string.' => 2,'test' => 4,'This' => 4),
		// 3 - double count sorted descending
		array('This' => 4,'test' => 4,'string.' => 2,'string' => 2,'second'=> 2,'is' => 4,'a' => 4,),
		// 4 - triple count
		array('This' => 6, 'is' => 6, 'a' => 6, 'test' => 6, 'string.' => 3, 'string' => 3, 'second'=> 3,),
		// 5 - normal count lowercase
		array('this' => 2, 'is' => 2, 'a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 6 - normal count UPPERCASE
		array('THIS' => 2, 'IS' => 2, 'A' => 2, 'TEST' => 2, 'STRING.' => 1, 'STRING' => 1, 'SECOND'=> 1,),
		// 7 - normal count - blacklist items removed, nocase
		array('a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 8 - normal count - blacklist items removed, nocase
		array('This' => 2, 'a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
	);
	
	public function setUp() {
		parent::setUp();
		$this->ywf = Yii::createComponent(array('class' => 'YiiWordFrequency'));
	}

	public function testStringInput() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testArrayInput() {
		$this->ywf->sourceList = array($this->inputFixture[1]);
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testActiveRecordInput1() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1)); 
		$criteria->select = "col1";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testActiveRecordInput2() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(2)); 
		$criteria->select = "col1, col2";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testActiveRecordInput3() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(3)); 
		$criteria->select = "col1, col2, col3";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testActiveRecordInput4() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1,2)); 
		$criteria->select = "col1, col2, col3";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[1], $this->ywf->tagFrequencyList);
	}

	public function testAllInputSources() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1)); 
		$criteria->select = "col1";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
			array($model, $criteria),
		);
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[4], $this->ywf->tagFrequencyList);
	}

	public function testAllAddMethods() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1)); 
		$criteria->select = "col1";
		$this->ywf->addSource($this->inputFixture[0]);
		$this->ywf->addSource($this->inputFixture[1]);
		$this->ywf->addDbSource($model, $criteria);
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[4], $this->ywf->tagFrequencyList);
	}

	public function testSortingAsc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortTagList=rand(1, getrandmax());
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[2], $this->ywf->tagFrequencyList);
	}

	public function testSortingDesc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortTagList=rand(1, getrandmax())*-1;
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[3], $this->ywf->tagFrequencyList);
	}

	public function testExplosion() {
		$this->ywf->explosionDelimiter = ',';
		$this->ywf->sourceList = array($this->inputFixture[2]);
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testLowerCase() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->forceCase = rand(1, getrandmax())*-1;
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[5], $this->ywf->tagFrequencyList);
	}

	public function testUpperCase() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->forceCase = rand(1, getrandmax());
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[6], $this->ywf->tagFrequencyList);
	}


	public function testRemoveNumeric() {
		$this->ywf->sourceList = array($this->inputFixture[3]);
		$this->ywf->removeNumeric = true;
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testBlacklist() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blacklist = array('this', 'is');
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[7], $this->ywf->tagFrequencyList);
	}

	public function testBlackListCaseSensitive() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blacklist = array('this', 'is');
		$this->ywf->blacklistIgnoreCase = false;
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[8], $this->ywf->tagFrequencyList);
	}
/*
	public function testBlackListFile() {
		$this->assertTrue(false);
	}

	public function testBlackListRegexp() {
		$this->assertTrue(false);
	}
	
	public function testWhiteList() {
		$this->assertTrue(false);
	}

	public function testWhiteListFile() {
		$this->assertTrue(false);
	}

	public function testWhiteListRegexp() {
		$this->assertTrue(false);
	}
	
	public function testReplacementList() {
		$this->assertTrue(false);
	}

	public function testReplacementFile() {
		$this->assertTrue(false);
	}

	public function testReplacementRegexp() {
		$this->assertTrue(false);
	}

	public function testMethodChaining() {
		$this->assertTrue(false);
	}

	public function testInitializationAtCreate() {
		// Need to delete the ywf object and create a new one with all initializaiton in the create config array
	}
	
	public function testComprehensive() {
		$this->assertTrue(false);
	}
*/
	public function tearDown() {
		unset($this->ywf);
	}
}
?>