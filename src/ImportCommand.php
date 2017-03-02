<?php

namespace Nicklayb\LaravelDbImport;

use DB;
use Artisan;
use Illuminate\Console\Command;

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
    protected $description = 'Import data from a source database to a destionation database';

    /**
     * Import to execute
     *
     * @var Nicklayb\LaravelDbImport\Import
     */
    protected $import = null;

    protected $importName;

    protected $sourceTables = [];

    protected $tasksCount = 0;

    protected $errors = [];

    public function __construct()
    {
        parent::__construct();
    }

    private function importIsRegistered()
    {
        return in_array($this->importName, config('dbimport.imports'));
    }

    private function createImport()
    {
        if ($this->importIsRegistered()) {
            try {
                $import = config('dbimport.imports'.$this->importName);
                $this->import = new $import();
                return true;
            } catch (Exception $e) {
                echo 'ERROR';
            }
        }
        return false;
    }

    public function setTasksCount()
    {
        $this->tasksCount = $this->sourceTables->count() + $this->import->countImportTasks();
        $this->bar = $this->output->createProgressBar($this->tasksCount);
    }

    public function handle()
    {
        if ($this->boot()) {
            $this->executeTasks($this->import->preImport());
            $this->handleImport();
            $this->executeTasks($this->import->postImport());
        }
    }

    private function handleImport()
    {
        foreach ($this->sourceTables as $table) {
            $this->handleTableImport($table);
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

    public function handleTableImport($table)
    {
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

    private function executeTasks($tasks)
    {
        foreach ($tasks as $key => $task) {
            $this->bar->setMessage('Executing '.$key);
            $task($this);
            $this->bar->advance();
        }
    }

    private function boot()
    {
        $this->importName = $this->argument('database');
        if ($this->createImport()) {
            $this->sourceTables = $this->import->getSortedSourceTables();
            $this->setTasksCount();
            $this->info("Database import in progress...\n");
            $this->bar->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%");
            $this->bar->start();
            return true;
        }
        return false;
    }
}
