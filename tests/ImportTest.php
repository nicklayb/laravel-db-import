<?php

use PHPUnit\Framework\TestCase;

require __DIR__.'/../src/Import.php';

class BasicImport extends Nicklayb\LaravelDbImport\Import
{
}

class ExtendedImport extends Nicklayb\LaravelDbImport\Import
{
    protected $ignoreTables = [ 'migrations' ];
    protected $lastTables = [ 'relation_one', 'relation_two' ];
    protected $selects = [
        'users' => [
            'id', 'firstname', 'lastname'
        ]
    ];
    protected $resetPassword = [
        'users' => 'test',
        'users:super_secret_password' => 'new secret'
    ];

    public function filterUsers($query)
    {
        return $query;
    }

    public function manipulateUsers($user)
    {
        $user['key'] = 'value';
        return $user;
    }

    public function preImport()
    {
        return [
            'pre_task' => function () {
                //
            }
        ];
    }

    public function postImport()
    {
        return [
            'post_task' => function () {
                //
            }
        ];
    }
}

class ImportTest extends TestCase
{
    protected $basicImport;
    protected $extendedImport;

    public function __construct()
    {
        parent::__construct();
        $this->basicImport = new BasicImport;
        $this->extendedImport = new ExtendedImport;
    }

    public function testHasIgnoreTable()
    {
        $this->assertTrue($this->extendedImport->hasIgnoreTable('migrations'));
    }

    public function testHasIgnoreTableInexistant()
    {
        $this->assertFalse($this->extendedImport->hasIgnoreTable('products'));
    }

    public function testHasLastTable()
    {
        $this->assertTrue($this->extendedImport->hasLastTable('relation_one'));
    }

    public function testHasLastTableInexistant()
    {
        $this->assertFalse($this->extendedImport->hasLastTable('products'));
    }

    public function testHasPasswordReset()
    {
        $this->assertTrue($this->extendedImport->hasPasswordResets());
    }

    public function testHasPasswordResetInexistant()
    {
        $this->assertFalse($this->basicImport->hasPasswordResets());
    }

    public function testQualifiedManipulationName()
    {
        $expected = 'manipulateUsers';
        $this->assertEquals($expected, $this->extendedImport->getManipulationName('users'));
    }

    public function testGetPasswordResetValues()
    {
        $expected = [
            'super_secret_password' => 'new secret',
            'password' => 'test'
        ];
        $this->assertEquals($expected, $this->extendedImport->getPasswordResetValues('users'));
    }

    public function testHasSelects()
    {
        $this->assertTrue($this->extendedImport->hasSelects('users'));
    }

    public function testHasSelectsInexistant()
    {
        $this->assertFalse($this->extendedImport->hasSelects('products'));
    }

    /**
     * @depends testHasSelects
     * @depends testHasSelectsInexistant
     */
    public function testGetSelects()
    {
        $expected = [
            'id', 'firstname', 'lastname'
        ];
        $this->assertEquals($expected, $this->extendedImport->getSelects('users'));
    }

    /**
     * @depends testHasSelects
     * @depends testHasSelectsInexistant
     */
    public function testGetSelectsInexistant()
    {
        $expected = [ '*' ];
        $this->assertEquals($expected, $this->extendedImport->getSelects('products'));
    }

    /**
     * @depends testQualifiedManipulationName
     */
    public function testHasTableManipulation()
    {
        $this->assertTrue($this->extendedImport->hasManipulation('users'));
    }

    /**
     * @depends testQualifiedManipulationName
     */
    public function testHasInexistantTableManipulation()
    {
        $this->assertFalse($this->extendedImport->hasManipulation('products'));
    }

    public function testQualifiedFilterName()
    {
        $expected = 'filterUsers';
        $this->assertEquals($expected, $this->extendedImport->getFilterName('users'));
    }

    /**
     * @depends testQualifiedFilterName
     */
    public function testHasTableFilter()
    {
        $this->assertTrue($this->extendedImport->hasTableFilter('users'));
    }

    /**
     * @depends testQualifiedFilterName
     */
    public function testHasInexistantTableFilter()
    {
        $this->assertFalse($this->extendedImport->hasTableFilter('products'));
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

        $this->assertEquals($expected, $this->extendedImport->executeManipulation($table, $base));
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

        $this->assertEquals($expected, $this->extendedImport->executeManipulation($table, $base));
    }

    public function testCountImportTasks()
    {
        $expected = 2;
        $this->assertEquals($expected, $this->extendedImport->countImportTasks());
    }
}
