<?php

use PHPUnit\Framework\TestCase;

require __DIR__.'/../src/Import.php';

class ImportExtended extends Nicklayb\LaravelDbImport\Import
{
    public function filterUsers($query)
    {
        return $query;
    }

    public function manipulateUsers($user)
    {
        $user['key'] = 'value';
        return $user;
    }
}

class ImportTest extends TestCase
{
    protected $import;

    public function __construct()
    {
        parent::__construct();
        $this->import = new ImportExtended;
    }

    public function testQualifiedManipulationName()
    {
        $expected = 'manipulateUsers';
        $this->assertEquals($expected, $this->import->getManipulationName('users'));
    }

    /**
     * @depends testQualifiedManipulationName
     */
    public function testHasTableManipulation()
    {
        $this->assertTrue($this->import->hasManipulation('users'));
    }

    /**
     * @depends testQualifiedManipulationName
     */
    public function testHasInexistantTableManipulation()
    {
        $this->assertFalse($this->import->hasManipulation('products'));
    }

    public function testQualifiedFilterName()
    {
        $expected = 'filterUsers';
        $this->assertEquals($expected, $this->import->getFilterName('users'));
    }

    /**
     * @depends testQualifiedFilterName
     */
    public function testHasTableFilter()
    {
        $this->assertTrue($this->import->hasTableFilter('users'));
    }

    /**
     * @depends testQualifiedFilterName
     */
    public function testHasInexistantTableFilter()
    {
        $this->assertFalse($this->import->hasTableFilter('products'));
    }

    /**
     * @depends testQualifiedFilterName
     * @depends testHasTableFilter
     * @depends testHasInexistantTableFilter
     */
    public function testExecuteManipulation()
    {
        $table = 'users';
        $base = [ 'root' => 'element' ];
        $expected = [
            'root' => 'element',
            'key' => 'value'
        ];

        $this->assertEquals($expected, $this->import->executeManipulation($table, $base));
    }

    /**
     * @depends testQualifiedFilterName
     * @depends testHasTableFilter
     * @depends testHasInexistantTableFilter
     */
    public function testExecuteInexistantManipulation()
    {
        $table = 'products';
        $base = [ 'root' => 'element' ];
        $expected = [ 'root' => 'element' ];

        $this->assertEquals($expected, $this->import->executeManipulation($table, $base));
    }
}
