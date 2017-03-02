<?php

namespace Walkin\Console\Commands\Exceptions;

class UnregisteredImportException extends \Exception
{
    public function __construct($importName, $code = 0)
    {
        $this->message = 'The import "'.$importName.'" is not registered in the config file';
    }
}
