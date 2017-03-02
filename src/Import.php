<?php

namespace Nicklayb\LaravelDbImport;

abstract class Import
{
    /**
     * The key of the source connection created in the database config file
     *
     * @var string
     */
    protected $sourceConnection;

    /**
     * The key of the destination connection created in the database config file
     *
     * @var string
     */
    protected $destinationConnection = null;

    /**
     * Password reset option, yout must specify the table of the users as
     * key and specify the new password as the value. Default column
     * is 'password' but override it by adding :column
     * 'users:column_password' => 'superpassword'
     *
     * @var array
     */
    protected $resetPassword = [];

    /**
     * Specify tables you don't want to import during the upload by specifying
     * the table name
     *
     * @var array
     */
    protected $ignoreTables = [];

    /**
     * Set the tables to import after all the others, this is useful when you
     * are dealing with foreign key constraints
     *
     * @var array
     */
    protected $lastTables = [];

    /**
     * Set this property to true to execute a php artisan migrate:refresh
     * before importing your database
     *
     * @var bool
     */
    protected $refresh = false;

    protected $selects = [];

    protected $showTablesCommand = 'SHOW TABLES';

    public function getShowTablesCommand()
    {
        return $this->showTablesCommand;
    }

    public function getSourceConnection()
    {
        return $this->sourceConnection;
    }

    public function hasSelects($table)
    {
        return isset($this->selects[$table]);
    }

    public function getSelects($table)
    {
        if ($this->hasSelects($table)) {
            return $this->selects[$table];
        }
        return ['*'];
    }

    public function getDestinationConnection()
    {
        return $this->destinationConnection;
    }

    public function getQualifiedTableColumnName()
    {
        return 'Tables_in_'.$this->sourceConnection;
    }

    public function getSourceDatabaseName()
    {
        return config('database.connections.'.$this->sourceConnection.'.database');
    }

    public function loadSourceTables()
    {
        return DB::connection($this->sourceConnection)->select($this->showTablesCommand);
    }

    public function getSourceTables()
    {
        return collect($this->getTableSelect())->pluck($this->getQualifiedTableColumnName())
    }

    public function getIgnoreTables()
    {
        return $this->ignoreTables;
    }

    public function getLastTables()
    {
        return $this->lastTables;
    }

    public function countImportTasks()
    {
        return count($this->preImport()) + count($this->postImport());
    }

    public function getSourceRows($table)
    {
        return DB::connection($this->sourceConnection)
            ->table($table)
            ->select($this->getSelects())
            ->get();
    }

    public function clearDestinationTable($table)
    {
        return DB::connection($this->destinationConnection)
            ->table($table)
            ->delete();
    }

    public function insertInDestination($table, $row)
    {
        return DB::connection($this->destinationConnection)
            ->table($table)
            ->insert((array) $this->executeManipulation($table, $row));
    }

    public function getSortedSourceTables()
    {
        $tables = $this->getSourceTables();
        $filteredTables = collect([]);
        $holds = collect([]);
        foreach ($tables as $table) {
            if ($this->hasLastTable($table)) {
                $hold->push($table);
            } else {
                $filteredTables->push($table);
            }
        }
        return $filteredTables->merge($holds);
    }

    public function hasIgnoreTable($table)
    {
        return in_array($table, $this->ignoreTables);
    }

    public function hasLastTable($table)
    {
        return in_array($table, $this->lastTables);
    }

    /**
     * Check if any ignore table is registered in the property
     *
     * @return bool
     */
    public function hasIgnoreTables()
    {
        return count($this->ignoreTables) > 0;
    }

    /**
     * Check if there is a password reset for specified table
     *
     * @return bool
     */
    public function hasPasswordReset($table)
    {
        foreach ($this->resetPassword as $key => $password) {
            $tableName = $key;
            $pos = strpos($tableName, ':');
            $tableName = ($pos !== false) ? substr($table, 0, $pos) : $tableName;
            if ($table == $tableName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the import requires a refresh before importing
     *
     * @return bool
     */
    public function needsRefrseh()
    {
        return $this->refresh;
    }

    /**
     * Check if any last table is registered in the property
     *
     * @return bool
     */
    public function hasLastTables()
    {
        return count($this->lastTables) > 0;
    }

    /**
     * Return a qualified name for a table manipulation
     *
     * @param string $table
     * @return string
     */
    public function getManipulationName($table)
    {
        return 'manipulate'.camel_case($table);
    }

    /**
     * Check if a manipulation method exists
     *
     * @param string $table
     * @return bool
     */
    public function hasManipulation($table)
    {
        return method_exists($this, $this->getManipulationName($table));
    }

    /**
     * Call a manipulation for a given table by passing each instance of
     * the table rows
     *
     * @param string $table
     * @param StdClass|array $instance
     * @return StdClass|array
     */
    public function executeManipulation($table, $instance)
    {
        if ($this->hasManipulation($table)) {
            return $this->{$this->getManipulationName($table)}($instance);
        }
        return $instance;
    }

    /**
     * Fill the array with Closures to execute before starting the import
     *
     * @return array
     */
    public function preImport()
    {
        return [];
    }

    /**
     * Fill the array with Closures to execute after the import is done
     *
     * @return array
     */
    public function postImport()
    {
        return [];
    }
}
