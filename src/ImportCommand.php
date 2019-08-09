<?php

namespace Nicklayb\LaravelDbImport;

use DB;
use Artisan;
use Illuminate\Console\Command;
use Nicklayb\LaravelDbImport\Exceptions\UnregisteredImportException;

/**
 * Class for Import command
 *
 * @author Nicolas Boisvert (nicklay@me.com)
 *
 * Artisan command that will do an extended import process. Extends the Import
 * class and then register the namespace in importdb.php config file. Then
 * you pass the key in parameters when calling the command
 */

class ImportCommand extends Command
{
    /**
     * Command signature to display in Artisan
     *
     * @var string
     */
    protected $signature = 'db:import {database}';

    /**
     * Command description to display in Artisan
     *
     * @var string
     */
    protected $description = 'Import data from a source database to a destination database';

    /**
     * Import to execute
     *
     * @var Nicklayb\LaravelDbImport\Import
     */
    protected $import = null;

    /**
     * Name of the import
     *
     * @var string
     */
    protected $importName;

    /**
     * All the source tables to import
     *
     * @var array
     */
    protected $sourceTables = [];

    /**
     * Count of all the tasks required to complete de command
     *
     * @var int
     */
    protected $tasksCount = 0;

    /**
     * List of errors that happens during import
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Checks if the provided import is in the config file
     *
     * @return bool
     */
    private function importIsRegistered()
    {
        return isset(config('dbimport.imports')[$this->importName]);
    }

    /**
     * Create the actual import instance
     *
     * @return bool
     */
    private function createImport()
    {
        if ($this->importIsRegistered()) {
            try {
                $import = config('dbimport.imports.'.$this->importName);
                $this->import = new $import();
                return true;
            } catch (Exception $e) {
            }
        } else {
            throw new UnregisteredImportException($this->importName);
        }
        return false;
    }

    /**
     * Setup the tasks count for the progress bar
     *
     * @return void
     */
    public function setTasksCount()
    {
        $this->tasksCount = $this->sourceTables->count() + $this->import->countImportTasks();
        $this->bar = $this->output->createProgressBar($this->tasksCount);
    }

    /**
     * Handle the command process. This is overriden from Laravel's command
     *
     * @return void
     */
    public function handle()
    {
        if ($this->boot()) {
            $this->bar->start();
            if ($this->import->needsRefresh()) {
                $this->bar->setMessage('Refreshing');
                Artisan::call('migrate:refresh');
            }
            $this->executeTasks($this->import->preImport());
            $this->handleImport();
            $this->executeTasks($this->import->postImport());
        }
    }

    /**
     * Handle the tables imports process
     *
     * @return void
     */
    private function handleImport()
    {
        foreach ($this->sourceTables as $table) {
            $this->handleTableImport($table);
            $this->bar->advance();
        }
        $this->bar->finish();
        $this->info("\nAll done!");

        if (count($this->errors) > 0) {
            $this->info("\nSome errors occured (".count($this->errors).") : ");
            foreach ($this->errors as $key => $error) {
                $this->info("\n\t".$value);
            }
        }
    }

    /**
     * Handle specific table import
     *
     * @return void
     */
    public function handleTableImport($table)
    {
        $this->bar->setMessage('Importing '.$table);
        $count = 0;
        $rows = $this->import->getSourceRows($table);
        $this->import->clearDestinationTable($table);
        foreach ($rows as $key => $row) {
            try {
                $count += $this->import->insertInDestination($table, $row);
            } catch (Exception $e) {
                $this->errors[] = 'Error importing table '.$table;
            }
            $this->bar->setMessage('Importing '.$table." (".$count."/".count($rows).")");
        }
        return $count;
    }

    /**
     * Executes array of tasks
     *
     * @return void
     */
    private function executeTasks($tasks)
    {
        foreach ($tasks as $key => $task) {
            $this->bar->setMessage('Executing '.$key);
            $task($this);
            $this->bar->advance();
        }
    }

    /**
     * Bootup the command process and return if the command can proceed or not
     *
     * @return true
     */
    private function boot()
    {
        $this->importName = $this->argument('database');
        if ($this->createImport()) {
            $this->sourceTables = $this->import->getSortedSourceTables();
            $this->setTasksCount();
            $this->info("Database import in progress...\n");
            $this->bar->setMessage('');
            $this->bar->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%");
            return true;
        }
        return false;
    }
}
