<?php 
class YiiWordFrequencyTest extends WUnitTestCase {
	
	private $inputFixture = array(
		array('This is a test string. This is a second test string'),
		array(array('This is a test string.'), array('This is a second test string')),
	);

	private $outputFixture = array(
		array('This' => 2, 'is' => 2, 'a' => 2, 'test' => 2, 'string.' => 1, 'string' => 1, 'second'=> 1,),
		array('This' => 4, 'is' => 4, 'a' => 4, 'test' => 4, 'string.' => 2, 'string' => 2, 'second'=> 2,),
		array('a' => 4,'is' => 4,'second'=> 2,'string' => 2,'string.' => 2,'test' => 4,'This' => 4),
		array('This' => 4,'test' => 4,'string.' => 2,'string' => 2,'second'=> 2,'is' => 4,'a' => 4,),
	);
	
	public function setUp() {
		parent::setUp();
		$this->ywf = Yii::createComponent(array(
			'class' => 'YiiWordFrequency',
//	public $blackList = array();
//	public $blackListFile = array();
//	public $substitutionList = array();
//	public $stringSource = '';
//	public $arraySourceList = array();
//	public $activeRecordSourceList = array();
			)
		);
	}

	public function testStringInput() {
		$this->ywf->sourceList = $this->inputFixture[0];
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testArrayInput() {
		$this->ywf->sourceList = $this->inputFixture[1];
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[0], $this->ywf->tagFrequencyList);
	}

	public function testActiveRecordInput() {
		$this->assertTrue(false);
	}

	public function testAllInputSources() {
		$this->ywf->sourceList = array(
			$this->inputFixture[0],
			$this->inputFixture[1],
		);
		$this->ywf->generateTagList();
		$this->assertEquals($this->outputFixture[1], $this->ywf->tagFrequencyList);
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

	public function tearDown() {
		unset($this->ywf);
	}
}
?>