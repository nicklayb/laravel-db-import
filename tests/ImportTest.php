<?php

use PHPUnit\Framework\TestCase;

class ImportTest extends TestCase
{
    public function testImport()
    {
        $stub = $this->getMockForAbstractClass('Nicklayb\LaravelDbImport\Import');
        $stub->expects($this->any())
             ->method('countImportTasks')
             ->will($this->returnValue(0));

        $this->assertTrue($stub->concreteMethod());
    }
}
