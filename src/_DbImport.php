<?php

namespace Nicklayb\LaravelDbImport;

use DB;
use Artisan;
use Illuminate\Console\Command;

class ImportProdDatabase extends Command
{
    protected $signature = 'db:importprod';

    protected $description = 'Import data from production database';

    protected $except_tables = [];
    protected $last_tables = [];

    protected $development_connection = 'mysql';
    protected $production_connection = 'prod';

    protected $manipulations = [];
    protected $afterAll = [];
    protected $beforeAll = [];
    protected $onEachTables = [];

    protected $offline = false;
    protected $resetPassword = false;


    public function __construct()
    {
        parent::__construct();
    }

    private function setup()
    {
        $this->off = $this->option('off');
        $this->resetPassword = $this->option('reset-password');
        $this->setManipulations();
        if (!$this->off) {
            $tables = collect(DB::connection($this->production_connection)->select('SHOW TABLES'))->pluck('Tables_in_'.env('PROD_DATABASE'));
            $hold = [];
            $this->tables = collect([]);
            foreach ($tables as $key => $value) {
                if (in_array($value, $this->last_tables)) {
                    $hold[] = $value;
                } else {
                    $this->tables->push($value);
                }
            }
            foreach ($this->last_tables as $key => $value) {
                $this->tables->push($value);
            }
        }
        $this->errors = [];
    }

    private function setManipulations()
    {
        $this->beforeAll = [
            'refresh'=>function () {
                Artisan::call('migrate:refresh');
            },
        ];
        if ($this->resetPassword) {
            $this->manipulations = [
                'users' => function ($user) {
                    $user->password = bcrypt(env('DEFAULT_PASSWORD', '1234567890'));
                    return $user;
                }
            ];
        }
    }

    public function handle()
    {
        if (config('app.debug')) {
            $this->setup();
            $total_tables = ($this->off) ? 0 : $this->tables->count();
            $except_tables = ($this->off) ? 0 : count($this->except_tables);
            $total = $total_tables+1-$except_tables+count($this->beforeAll)+count($this->afterAll);
            $this->info("Database import in progress...\n");
            $this->bar = $this->output->createProgressBar($total);
            $this->bar->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%");
            $this->bar->setMessage('php artisan migrate:refresh');
            $this->bar->start();
            $this->executeManipulation($this->beforeAll);
            $this->bar->advance();
            if (!$this->off) {
                foreach ($this->tables as $key => $value) {
                    if (!in_array($value, $this->except_tables)) {
                        $this->bar->setMessage('Importing '.$value);
                        $this->importTable($value);
                        $this->bar->advance();
                    }
                }
            }
            $this->executeManipulation($this->afterAll);
            $this->bar->finish();
            $this->info("\nAll done!");

            if (count($this->errors) > 0) {
                $this->info("\nSome errors occured (".count($this->errors).") : ");
                foreach ($this->errors as $key => $value) {
                    $this->info("\n\t".$value);
                }
            }
        } else {
            dd("Server in production, operation aborted.");
        }
    }

    private function importTable($table)
    {
        $count = 0;
        $prod_table = DB::connection($this->production_connection)->table($table)->select('*')->get();
        DB::connection($this->development_connection)->table($table)->delete();
        foreach ($prod_table as $key => $value) {
            try {
                $exist = DB::connection($this->development_connection)->table($table)->where('id', $value->id)->count();
                if ($exist == 0) {
                    DB::connection($this->development_connection)->table($table)->insert((array)$this->manipulate($table, $value));
                    $count++;
                }
            } catch (Exception $e) {
                $this->errors[] = 'Error importing table '.$table;
            }
            $this->bar->setMessage('Importing '.$table." (".$count."/".count($prod_table).")");
        }
    }

    private function executeManipulation($closures)
    {
        foreach ($closures as $key => $value) {
            $this->bar->setMessage('Executing '.$key);
            $value($this);
            $this->bar->advance();
        }
    }

    private function manipulate($table, $record)
    {
        if (isset($this->manipulations[$table])) {
            $record = $this->manipulations[$table]($record);
        }
        foreach ($this->onEachTables as $key => $value) {
            $record = $value($record);
        }
        return $record;
    }
}
