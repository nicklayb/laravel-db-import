<?php

namespace Nicklayb\LaravelDbImport;

use DB;
use Illuminate\Support\Str;

/**
 * Class for Import process
 *
 * @author Nicolas Boisvert (nicklay@me.com)
 *
 * Extends this class to match your needs. Don't forget to add it in your
 * dbimport.php config file so you will be able to call it
 */
abstract class Import
{
    /**
     * The key of the source connection created in the database config file
     *
     * @var string
     */
    protected $sourceConnection = 'source';

    /**
     * The key of the destination connection created in the database config file
     *
     * @var string
     */
    protected $destinationConnection = 'destination';

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

    /**
     * Specify table by table the select statement of which column to load
     *
     * @var array
     */
    protected $selects = [];

    /**
     * Show table command, it may change depending on your database server
     *
     * @var string
     */
    protected $showTablesCommand = 'SHOW TABLES';

    /**
     * Key for default password when using reset passwords
     *
     * @var string
     */
    protected $defaultPasswordColumn = 'password';

    /**
     * Checks if provided table has specific selected columns
     *
     * @param string $table
     * @return bool
     */
    public function hasSelects($table)
    {
        return isset($this->selects[$table]);
    }

    /**
     * Gets specific selects for defined table
     *
     * @param string $table
     * @return array
     */
    public function getSelects($table)
    {
        if ($this->hasSelects($table)) {
            return $this->selects[$table];
        }
        return ['*'];
    }

    /**
     * Return the qualified column name for table select
     *
     * @return string
     */
    public function getQualifiedTableColumnName()
    {
        return 'Tables_in_'.$this->getSourceDatabaseName();
    }

    /**
     * Return the database name from the configuration for the source
     *
     * @return string
     */
    public function getSourceDatabaseName()
    {
        return config('database.connections.'.$this->sourceConnection.'.database');
    }

    /**
     * Load all tables from the source connection
     *
     * @return array
     */
    public function loadSourceTables()
    {
        return DB::connection($this->sourceConnection)->select($this->showTablesCommand);
    }

    /**
     * Get a collection of only the table names from the the source connection
     *
     * @return Collection
     */
    public function getSourceTables()
    {
        return collect($this->loadSourceTables())->pluck($this->getQualifiedTableColumnName());
    }

    /**
     * Return the count of the pre/post tasks of the import
     *
     * @return int
     */
    public function countImportTasks()
    {
        return count($this->preImport()) + count($this->postImport());
    }

    /**
     * Return all rows from specified table in the source connection with
     * the selected columns
     *
     * @return Collection
     */
    public function getSourceRows($table)
    {
        $query = DB::connection($this->sourceConnection)
            ->table($table)
            ->select($this->getSelects($table));
        return $this->queryTable($query, $table)->get();
    }

    /**
     * Filter query with specific table filters
     *
     * @param  QueryBuilder $query
     * @param  String $table
     * @return QueryBuilder
     */
    public function queryTable($query, $table)
    {
        if ($this->hasTableFilter($table)) {
            $filterName = $this->getFilterName($table);
            return $this->{$filterName}($query);
        }
        return $query;
    }

    /**
     * Validates if a specific table has a custom filter
     *
     * @param  String  $table
     * @return boolean
     */
    public function hasTableFilter($table)
    {
        return method_exists($this, $this->getFilterName($table));
    }

    /**
     * Returns the qualified method name for a table filter
     *
     * @param  String $table
     * @return String
     */
    public function getFilterName($table)
    {
        return 'filter'. Str::studly($table);
    }

    /**
     * Delete the content of the destination connection table
     *
     * @return int
     */
    public function clearDestinationTable($table)
    {
        return DB::connection($this->destinationConnection)
            ->table($table)
            ->delete();
    }

    /**
     * Insert specific data into the destination connection
     *
     * @return int
     */
    public function insertInDestination($table, $row)
    {
        if ($this->hasPasswordResets()) {
            $passwords = $this->getPasswordResetValues($table);
            foreach ($passwords as $column => $password) {
                $row->{$column} = $this->hashPassword($password);
            }
        }
        return DB::connection($this->destinationConnection)
            ->table($table)
            ->insert((array) $this->executeManipulation($table, $row));
    }

    /**
     * Method that hashes password
     *
     * @param string $password
     * @return string
     */
    public function hashPassword($password)
    {
        return bcrypt($password);
    }

    /**
     * Sort the sources tables by ordering last tables and removing the ingored
     *
     * @return Collection
     */
    public function getSortedSourceTables()
    {
        $filteredTables = collect([]);
        $holds = collect([]);

        foreach ($this->getSourceTables() as $table) {
            $index = $this->hasLastTable($table);
            if ($index >= 0) {
                $holds->put($index, $table);
            } elseif (!$this->hasIgnoreTable($table)) {
                $filteredTables->push($table);
            }
        }
        $arrayHolds = $holds->toArray();
        ksort($arrayHolds);
        $holds = collect($arrayHolds);

        return $filteredTables->merge($holds);
    }

    /**
     * Check if a specified table should be ignored
     *
     * @return bool
     */
    public function hasIgnoreTable($table)
    {
        return in_array($table, $this->ignoreTables);
    }

    /**
     * Gets the index of a table in the last tables array
     *
     * @return int
     */
    public function hasLastTable($table)
    {
        $index = array_search($table, $this->lastTables);
        return ($index !== false) ? $index : -1;
    }

    /**
     * Check if it has password resets registered
     *
     * @return boolean [description]
     */
    public function hasPasswordResets()
    {
        return count($this->resetPassword) > 0;
    }

    /**
     * Get password reset values
     *
     * @return bool
     */
    public function getPasswordResetValues($table)
    {
        $columns = [];
        foreach ($this->resetPassword as $key => $password) {
            $pos = strpos($key, ':');
            $tableName = ($pos !== false) ? substr($key, 0, $pos) : $key;
            $column = ($pos !== false) ? substr($key, ($pos + 1)) : 'password';
            if ($table == $tableName) {
                $columns[$column] = $password;
            }
        }
        return $columns;
    }

    /**
     * Check if the import requires a refresh before importing
     *
     * @return bool
     */
    public function needsRefresh()
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
        return 'manipulate'.Str::studly($table);
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
