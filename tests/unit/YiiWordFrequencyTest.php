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
		'This is a test 321 string. This is 0 a second test string 47',
		'use über zend angel',
	);

	// Static Class Fixtures
	private $outputFixture = array(
		// 0 - normal count
		array('This' => 2, 'is' => 2, 'a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 1 - double count
		array('This' => 4, 'is' => 4, 'a' => 4, 'test' => 4, 'string.' => 2, 'string' => 2, 'second'=> 2,),
		// 2 - double count sorted ascending by token
		array('This' => 4, 'a' => 4,'is' => 4,'second'=> 2,'string' => 2,'string.' => 2,'test' => 4,),
		// 3 - double count sorted descending by token
		array('test' => 4,'string.' => 2,'string' => 2,'second'=> 2,'is' => 4,'a' => 4,'This' => 4,),
		// 4 - triple count
		array('This' => 6, 'is' => 6, 'a' => 6, 'test' => 6, 'string.' => 3, 'string' => 3, 'second'=> 3,),
		// 5 - normal count lowercase
		array('this' => 2, 'is' => 2, 'a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 6 - normal count UPPERCASE
		array('THIS' => 2, 'IS' => 2, 'A' => 2, 'TEST' => 2, 'STRING.' => 1, 'STRING' => 1, 'SECOND'=> 1,),
		// 7 - normal count - blackList items removed, nocase
		array('a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 8 - normal count - blackList items removed, nocase
		array('This' => 2, 'a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 9 - normal count - blackList items from "blackList_en" removed, nocase
		array('test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 10 - normal count - blackList from "blackList_en" removed case sensitive
		array('This' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 11 - regular expression blackList /^[Tt]/ and /^[Ss]/
		array('is' => 2, 'a' => 2),
		// 12 - regular expression blackList /^[Tt]/ and /^[Ss]/  and /s$/
		array('a' => 2),
		// 13 - whiteList
		array('This' => 2, 'is' => 2),
		// 14 - whiteList, case sensitive
		array('is' => 2),
		// 15 - whiteList, regexp
		array('This' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 16 - whiteList, regexp file
		array('This' => 2, 'is' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		// 17 - whiteList, regexp 
		array('This' => 2, 'is' => 2, 'test' => 2),
		// 18 - substitution (. => X)
		array('This' => 2, 'is' => 2, 'a' => 2, 'test' => 2, 'stringX' => 1, 'string' => 1, 'second'=> 1,),
		// 19 - substitution from file
		array('This' => 2, 'is' => 2, 'a' => 2, 'test' => 2, 'string' => 2, 'second'=> 1,),
		// 20 - substitution from file, case insensitive
		array('XXXhis' => 2, 'is' => 2, 'a' => 2, 'XXXesXXX' => 2, 'sXXXring' => 2, 'second'=> 1,),
		// 21 - sorting with locale DE
		array('angel' => 1, 'über' => 1, 'use' => 1, 'zend' => 1),
		// 22 - double count sorted ascending by frequency, token asc
		array('second' => 2,'string' => 2,'string.'=> 2,'This' => 4,'a' => 4,'is' => 4,'test' => 4,),
		// 23 - double count sorted ascending by frequency, token desc
		array('string.' => 2,'string' => 2,'second'=> 2,'test' => 4,'is' => 4,'a' => 4,'This' => 4,),
		// 24 - double count sorted descending by frequency token asc 
		array('This' => 4, 'a' => 4,'is' => 4,'test' => 4,'second'=> 2,'string' => 2,'string.' => 2,),
		// 25 - double count sorted descending by frequency token desc
		array('test' => 4, 'is' => 4,'a' => 4,'This' => 4,'string.'=> 2,'string' => 2,'second' => 2,),
	);
	
	public function setUp() {
		parent::setUp();
		$this->ywf = Yii::createComponent(array('class' => 'YiiWordFrequency'));
		$this->logger = Yii::getLogger();
		$this->logger->flush(true);
		DebugLogRoute::flushMessages();
	}
	
	public function assertArrayEquals($a1, $a2) {
		$this->assertEquals(serialize($a1), serialize($a2));
	}

	public function testStringInput() {
		$this->ywf->sourceList = $this->inputFixture[0];
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}

	public function testStringFileInput() {
		$path = Yii::app()->basePath . '/tests/fixtures/';
		$this->ywf->sourceFileList = array(
				$path . 'fileTestData_1.txt',
				$path . 'fileTestData_2.txt'
			);
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}


	public function testArrayInput() {
		$this->ywf->sourceList = array($this->inputFixture[1]);
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}

	public function testActiveRecordInput1() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1)); 
		$criteria->select = "col1";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}

	public function testActiveRecordInput2() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(2)); 
		$criteria->select = "col1, col2";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}

	public function testActiveRecordInput3() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(3)); 
		$criteria->select = "col1, col2, col3";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}

	public function testActiveRecordInput4() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1,2)); 
		$criteria->select = "col1, col2, col3";
		$this->ywf->sourceList = array(array($model, $criteria));
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[1], $this->ywf->tokenFrequencyList);
	}

	public function testAllInputSources() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1)); 
		$criteria->select = "col1";
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
			array($model, $criteria),
		);
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[4], $this->ywf->tokenFrequencyList);
	}

	public function testAllAddMethods() {
		$model = new Testdata;	
		$criteria=new CDbCriteria();
		$criteria->addInCondition('id',array(1)); 
		$criteria->select = "col1";
		$this->ywf->addSource($this->inputFixture[0]);
		$this->ywf->addSource($this->inputFixture[1]);
		$this->ywf->addDbSource($model, $criteria);
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[4], $this->ywf->tokenFrequencyList);
	}

	public function testSortingAsc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortByToken=rand(1, getrandmax());
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertArrayEquals($this->outputFixture[2], $this->ywf->tokenFrequencyList);
	}

	public function testSortingDesc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortByToken=rand(1, getrandmax())*-1;
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertArrayEquals($this->outputFixture[3], $this->ywf->tokenFrequencyList);
	}
	
	public function testFreqSortingAscTokenAsc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortByToken=1;
		$this->ywf->sortByFrequency=rand(1, getrandmax());
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertArrayEquals($this->outputFixture[22], $this->ywf->tokenFrequencyList);
	}

	public function testFreqSortingAscTokenDesc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortByToken=-1;
		$this->ywf->sortByFrequency=rand(1, getrandmax());
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertArrayEquals($this->outputFixture[23], $this->ywf->tokenFrequencyList);
	}

	public function testFreqSortingDescTokenAsc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortByToken=1;
		$this->ywf->sortByFrequency=rand(1, getrandmax())*-1;
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertArrayEquals($this->outputFixture[24], $this->ywf->tokenFrequencyList);
	}

	public function testFreqSortingDescTokenDesc() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->sortByToken=-1;
		$this->ywf->sortByFrequency=rand(1, getrandmax())*-1;
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertArrayEquals($this->outputFixture[25], $this->ywf->tokenFrequencyList);
	}

	public function testExplosion() {
		$this->ywf->explosionDelimiter = ',';
		$this->ywf->sourceList = array($this->inputFixture[2]);
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}

	public function testLowerCase() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->forceCase = rand(1, getrandmax())*-1;
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[5], $this->ywf->tokenFrequencyList);
	}

	public function testUpperCase() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->forceCase = rand(1, getrandmax());
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[6], $this->ywf->tokenFrequencyList);
	}

	public function testRemoveNumeric() {
		$this->ywf->sourceList = array($this->inputFixture[3]);
		$this->ywf->removeNumeric = true;
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tokenFrequencyList);
	}

	public function testBlackList() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackList = array('this', 'is');
		$this->ywf->accumulateSources();
		$this->ywf->runBlackListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[7], $this->ywf->tokenFrequencyList);
	}

	public function testBlackListCaseSensitive() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackList = array('this', 'is');
		$this->ywf->blackListCaseSensitive = true;
		$this->ywf->accumulateSources();
		$this->ywf->runBlackListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[8], $this->ywf->tokenFrequencyList);
	}

	public function testBlackListFile() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackListFile = array('blackList_en.txt');
		$this->ywf->accumulateSources();
		$this->ywf->runBlackListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[9], $this->ywf->tokenFrequencyList);
	}

	public function testBlackListFileCaseSensitive() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackListFile = array('blackList_en.txt');
		$this->ywf->blackListCaseSensitive = true;
		$this->ywf->accumulateSources();
		$this->ywf->runBlackListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[10], $this->ywf->tokenFrequencyList);
	}

	public function testBlackListRegexp() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackListRegularExpression = array('#^[Tt]#', '#^[Ss]#');
		$this->ywf->accumulateSources();
		$this->ywf->runBlackListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[11], $this->ywf->tokenFrequencyList);
	}
	
	public function testBlackListRegexpFile() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackListRegularExpressionFile = array(
			'../tests/fixtures/regexp_test_1.txt', 
			'../tests/fixtures/regexp_test_2.txt');
		$this->ywf->accumulateSources();
		$this->ywf->runBlackListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[12], $this->ywf->tokenFrequencyList);
	}

	public function testWhiteList() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteList = array('this', 'is');
		$this->ywf->accumulateSources();
		$this->ywf->runWhiteListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[13], $this->ywf->tokenFrequencyList);
	}

	public function testWhiteListCaseSensitive() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteList = array('this', 'is');
		$this->ywf->whiteListCaseSensitive = true;
		$this->ywf->accumulateSources();
		$this->ywf->runWhiteListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[14], $this->ywf->tokenFrequencyList);
	}

	public function testWhiteListFile() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteListFile = array('../tests/fixtures/whiteList_test.txt');
		$this->ywf->accumulateSources();
		$this->ywf->runWhiteListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[13], $this->ywf->tokenFrequencyList);
	}

	public function testWhiteListFileCaseSensitive() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteListFile = array('../tests/fixtures/whiteList_test.txt');
		$this->ywf->whiteListCaseSensitive = true;
		$this->ywf->accumulateSources();
		$this->ywf->runWhiteListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[14], $this->ywf->tokenFrequencyList);
	}

	public function testWhiteListRegexp() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteListRegularExpression = array('#^T#', '#^t#', '#^[Ss]#', '#^s#');
		$this->ywf->accumulateSources();
		$this->ywf->runWhiteListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[15], $this->ywf->tokenFrequencyList);
	}

	public function testWhiteListRegexp2() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteListRegularExpression = array('#T#', '#is#', '#^[Tt]#');
		$this->ywf->accumulateSources();
		$this->ywf->runWhiteListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[17], $this->ywf->tokenFrequencyList);
	}

	public function testWhiteListRegexpFile() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteListRegularExpressionFile = array(
			'../tests/fixtures/regexp_test_1.txt', 
			'../tests/fixtures/regexp_test_2.txt');
		$this->ywf->accumulateSources();
		$this->ywf->runWhiteListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[16], $this->ywf->tokenFrequencyList);
	}

	public function testSubstitutionList() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->substitutionList = array('.' => 'X');
		$this->ywf->accumulateSources();
		$this->ywf->runSubstitutionListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[18], $this->ywf->tokenFrequencyList);
	}

	public function testSubstitutionListFile() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->substitutionListFile = array('punctuation_en.php');
		$this->ywf->accumulateSources();
		$this->ywf->runSubstitutionListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[19], $this->ywf->tokenFrequencyList);
	}

	public function testSubstitutionListFileNoCase() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->substitutionListFile = array('punctuation_en.php', '../tests/fixtures/testcase.php');
		$this->ywf->substitutionListCaseSensitive = true;
		$this->ywf->accumulateSources();
		$this->ywf->runSubstitutionListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[20], $this->ywf->tokenFrequencyList);
	}

	public function testSubstitutionListRegularExpression() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->substitutionListRegularExpression = array('#[Tt]#' => 'XXX', '#\.#' => '');
		$this->ywf->accumulateSources();
		$this->ywf->runSubstitutionListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[20], $this->ywf->tokenFrequencyList);
	}

	public function testSubstitutionListRegularExpressionFile() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->substitutionListRegularExpressionFile = array(
			'../tests/fixtures/testcase_regexp_1.php', 
			'../tests/fixtures/testcase_regexp_2.php');
		$this->ywf->accumulateSources();
		$this->ywf->runSubstitutionListFilter();
		$this->ywf->generateList();
		$this->assertEquals($this->outputFixture[20], $this->ywf->tokenFrequencyList);
	}


	public function testMethodChaining1() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackList = array('this', 'is');
		$this->ywf->accumulateSources()->runBlackListFilter()->generateList();
		$this->assertEquals($this->outputFixture[7], $this->ywf->tokenFrequencyList);
	}

	public function testMethodChaining2() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteListRegularExpression = array('#T#', '#is#', '#^[Tt]#');
		$this->ywf->accumulateSources()->runWhiteListFilter()->generateList();
		$this->assertEquals($this->outputFixture[17], $this->ywf->tokenFrequencyList);
	}

	public function testMethodChaining3() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->substitutionListRegularExpressionFile = array(
			'../tests/fixtures/testcase_regexp_1.php', 
			'../tests/fixtures/testcase_regexp_2.php');
		$this->ywf->accumulateSources()->runSubstitutionListFilter()->generateList();
		$this->assertEquals($this->outputFixture[20], $this->ywf->tokenFrequencyList);
	}

	public function testInitializationAtCreate() {
		unset($this->ywf);
		$this->ywf = Yii::createComponent(array(
			'class' => 'YiiWordFrequency',
			'sourceList'=> array($this->inputFixture[0]),
			'whiteListFile' => array('../tests/fixtures/whiteList_test.txt'),
			'whiteListCaseSensitive' => true,
			)
		);
		$this->ywf->accumulateSources()->runWhiteListFilter()->generateList();
		$this->assertEquals($this->outputFixture[14], $this->ywf->tokenFrequencyList);
	}

	public function testBadSourceWarning1() {
		$this->ywf->addSource($this); //should produce a warning
 		$this->logger->flush(true);
		//$logs=DebugLogRoute::$messages; //recommended in Yii-wiki, but seems unecessary. Works without it.
		$this->assertTrue(DebugLogRoute::hasMessage('*String or array of strings expected*', 'warning'));
	}

	public function testBadSourceWarning2() {
		$this->ywf->addDbSource('junk','more junk'); //should produce a warning
		$this->logger->flush(true);
		$this->assertTrue(DebugLogRoute::hasMessage('*Active Record Model and CDbCriteria objects expected*', 'warning'));
	}
	
	public function testNonUsageWarning1() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->generateList();
		$this->logger->flush(true);
		$this->assertTrue(DebugLogRoute::hasMessage('*Sources have not been accumulated*', 'warning'));
	}
	
	public function testNonUsageWarning2() {
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->logger->flush(true);
		$this->assertTrue(DebugLogRoute::hasMessage('*No sources defined in*', 'warning'));
	}
	
	public function testNonUsageWarning3() {
		$this->ywf->sourceList = array('');
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->logger->flush(true);
		$this->assertTrue(DebugLogRoute::hasMessage('*Sources have produced no tokens*', 'warning'));
	}

	public function testNonUsageWarning4() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->blackList = array('this', 'is');
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->logger->flush(true);
		$this->assertTrue(DebugLogRoute::hasMessage('*Blacklist defined but not used in*', 'warning'));
	}

	public function testNonUsageWarning5() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->whiteList = array('this', 'is');
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->logger->flush(true);
		$this->assertTrue(DebugLogRoute::hasMessage('*Whitelist defined but not used in*', 'warning'));
	}

	public function testNonUsageWarning6() {
		$this->ywf->sourceList = array($this->inputFixture[0]);
		$this->ywf->substitutionList = array('this' => 'is');
		$this->ywf->accumulateSources();
		$this->ywf->generateList();
		$this->logger->flush(true);
		$this->assertTrue(DebugLogRoute::hasMessage('*Substitution List defined but not used in*', 'warning'));
	}
	
	public function testLocaleSorting() {
		$this->ywf->sourceList = array($this->inputFixture[4]);
		$this->ywf->sortByToken = 1;
		$this->ywf->accumulateSources()->generateList(array('de_DE@euro', 'de_DE', 'de'));
		$this->assertArrayEquals($this->outputFixture[21], $this->ywf->tokenFrequencyList);
	}
	
	public function tearDown() {
		unset($this->ywf);
	}

	/**
	 * Needed to flush log messages
	 * @see http://www.yiiframework.com/wiki/271/how-to-re-enable-logging-during-unit-testing
	 */
	public static function tearDownAfterClass()
	{
		Yii::app()->onEndRequest(new CEvent(null));
	}
}
?>