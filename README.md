# Laravel DB Import
###### By Nicolas Boisvert :: nicklay@me.com

### Laravel database import package for easy manipulations

## Purposes

This package will help you import your production database. It can be use to migrate current production server to another server or the checkout the production database to test with the same datas, for example.

## Installation

Install it via composer
```
composer require nicklayb/laravel-db-import
```

Register the service provider in `app.php`
```
Nicklayb\LaravelDbImport\ServiceProvider::class,
```

And then you have to publish it.
```
php artisan vendor:publish
```

## Configuration

### Creating import
First of all you need to create your import class. You create a class in any namespace you want that extends the `Nicklayb\LaravelDbImport\Import` class. Here is a an example file, I created it in my commands namespace but feel free to put where you want to. See API part for more parameters
```php
<?php

namespace Foo\Console\Commands;

use Nicklayb\LaravelDbImport\Import;

class ProdImport extends Import
{
    protected $sourceConnection = 'source';
    protected $destinationConnection = 'mysql';
}
```
You must precise at least these two parameters. The must refer to registered databases in your `database.php` config file. Put your production database connection name in the `$sourceConnection` and the destination, guess what, in the `$destinationConnection` property. **I highly suggest connecting your source database with a read-only user to prevent mistake. Because if you put the source in the destination, you're gonnna have a bad time**.

#### Manipulations

If you want a table to be manipulated on import like, for instance, setting the timestamp to now, you can create a manipulation. You just define a method that begins with `manipulate` and with your table name in camel case format. The method receives an instance of the table row, you must return it but manipulated

```php
<?php

namespace Foo\Console\Commands;

use Nicklayb\LaravelDbImport\Import;

class ProdImport extends Import
{
    protected $sourceConnection = 'source';
    protected $destinationConnection = 'mysql';

    public function manipulateUsers($user)
    {
        $user->created_at = Carbon::now();
        return $user;
    }
}
```

#### Table filter

Sometimes you may need to filter tables to get only certain items like, for instance, only the last 6 months of work. This may be achieved by adding table filters.

Let say I have a table called `orders` where I only need the last 6 months items.

```
namespace Foo\Console\Commands;

use Nicklayb\LaravelDbImport\Import;

class ProdImport extends Import
{
    protected $sourceConnection = 'source';
    protected $destinationConnection = 'mysql';

    public function filterUsers($query)
    {
        return $query->where('created_at', '>', Carbon::now()->subMonths(6));
    }
}
```

You will receive the base query in parameter and you should return the modified query.

### Registering import

Since you published the vendor's file, you'll notice that you have a brand new `dbimport.php` in your config file. In this file you will register all of you import classes you want to use.
```php
<?php

return [
    /**
     * Register your databases imports class here by specifying a key
     * and the class to manage this import
     *
     *  'production' => Namespace\ProductionImport::class
     */
    'imports' => [
        'prod' => Foo\Console\Commands\ProdImport::class
    ]
];
```

### Executing import

**Before trying it, make sure you registered the good source and the good destination**

You will be able to call it with artisan using the following command
```
php artisan db:import prod
```

The parameter `prod` of the command should match the key you registered in `dbimport.php`.

## API

Here's a list of properties and methods you can override matching your needs

```php
<?php

class MyImport extends Import
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
     * Specify table by table the select statement of which column to load like
     * ['users' => ['firstname', 'lastname']]
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
```

## Conclusion

Thank you for using, testing and improving it and feel free to contact me for any question.

Ending joke :
>I don't see women as objects, I consider each to be in a class of her own

## License
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
