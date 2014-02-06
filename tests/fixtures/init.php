<?php
// This code snippet was copied from "CDbFixtureManager->prepare()"
// It is here so that it only gets executed once at the beginning af all tests
// In addition the $fixtures array in the test class must be defined but empty.
foreach($this->getFixtures() as $tableName=>$fixturePath)
{	
	$this->resetTable($tableName);
	$this->loadFixture($tableName);
}
?>