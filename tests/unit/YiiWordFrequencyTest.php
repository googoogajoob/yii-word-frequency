<?php 
class YiiWordFrequencyTest extends WUnitTestCase {
	public function setUp() {
		$this->ywf = new YiiWordFrequency();
	}
   
	public function testGeneral() {
		$this->ywf->init();
		$this->assertTrue($this->ywf->initTestFlag);
	}
}
?>