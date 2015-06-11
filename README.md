# GaeSupport

Google App Engine(GAE) Support package for Lumen.

Currently supported features:
- Generation of general configuration files,
- Mail service provider,
- Queue service provider,
- Database connection,
- Filesystem.

## Installation

Pull in 'shpasser/gae-support-lumen' and 'illuminate/mail' packages via Composer.

```js
"require": {
    "shpasser/gae-support-lumen": "~1.0",
    "illuminate/mail": "~5.0"
}
```

Within `bootstrap/app.php`:

- Uncomment the following line in order to use the `Facades`

```php
$app->withFacades();
```


- Include the service providers

```php
$app->register('Shpasser\GaeSupportLumen\GaeSupportServiceProvider');
```

## Usage

Generate the GAE related files / entries.

```bash
php artisan gae:setup --config --bucket="your-bucket-id" --db-socket="cloud-sql-instance-socket-connection-string" --db-name="cloud-sql-database-name" --db-host="cloud-sql-instance-ipv4-address" your-app-id
```

The default GCS bucket is configured unless a custom bucket id is defined using
the `--bucket` option.

When `--db-name` option is defined at least one of `--db-socket` or `--db-host` should be defined also.

### Mail

The mail driver configuration can be found in `config/mail.php` and `.env.production`,
these configuration files are modified / generated by the artisan command. There is
no need in any kind of custom configuration. All the outgoing mail messages are sent
with sender address of an administrator of the application, i.e. `admin@your-app-id.appspotmail.com`.
The `sender`, `to`, `cc`, `bcc`, `replyTo`, `subject`, `body` and `attachment`
parts of email message are supported.

### Queues

The modified queue configuration file `config/queue.php` should contain:

```php
return array(

	...

	/*
	|--------------------------------------------------------------------------
	| GAE Queue Connection
	|--------------------------------------------------------------------------
	|
	*/

	'connections' => array(

		'gae' => array(
			'driver'	=> 'gae',
			'queue'		=> 'default',
			'url'		=> '/tasks',
			'encrypt'	=> true,
		),

		...

	),

);
```

The 'default' queue and encryption are used by default.
In order to use the queue your `app/Http/routes.php` file should contain the following route:

```php
Route::post('tasks', array('as' => 'tasks',
function()
{
	return Queue::marshal();
}));
```

This route will be used by the GAE queue to push the jobs. Please notice that the route
and the GAE Queue Connection 'url' parameter point to the same URL.
For more information on the matter please see http://laravel.com/docs/master/queues#push-queues.

### Cache, Session and Log

Cache, Session and Log components are supported via the use of specific drivers / handlers:

- Cache     - using the 'memcached' driver,
- Session   - using the 'memcached' driver,
- Log       - using 'syslog' handler.

The configuration options for the mentioned drivers / handlers are generated by the artisan command
and can be found in `.env.production` configuration file.

### Database

Google Cloud SQL is supported via Laravel's MySql driver. The connection configuration is added by
the artisan command to `config/database.php` under `cloudsql`. The connection parameters can be
configured using `--db-socket`, `--db-name` and `--db-host` options via the artisan command.

The database related environment variables are set in `.env.production` and `.env.local` files.

The `production` environment is configured to use the socket connection while the `local` configured
to connect via the IPv4 address of the Google Cloud SQL instance. Use Google Developers Console in
order to obtain the socket connection string and enable the IPv4 address of your database instance.

The migrations are supported while working in `local` environment only.

To use either the `production` or the `local` environment rename the appropriate file to `.env`.

### Filesystem

In order to support Laravel filesystem on GAE the artisan command modifies `config/filesystem.php`
to include an additional disk:

```php
'gae' => [
    'driver' => 'gae',
    'root'   => storage_path().'/app',
],
```

and adds the following line to `.env.production` file:

```php
FILESYSTEM = gae
```

### Optimizations

The optimizations allow the application to reduce the use of GCS, which is the only read-write
storage available on GAE platform as of now.

In order to optimize view compilation the included `cachefs` filesystem can be used to store
compiled views using `memcached` service. `cachefs` does not provide the application with a
reliable storage solution, information stored using `memcached` is managed according to
`memcached` rules and may be deleted when `memcached` decides to. Since the views can
be compiled again without any information loss it is appropriate to store compiled
views using `cachefs`.

'cachefs' has the following structure:

<pre>
/
+-- framework
    +-- views
</pre>

'/framework/views' is used to store the compiled views.

Use the following option to enable the feature in `.env.production` file:
```php
COMPILED_PATH = cachefs://framework/views
```

Additionally the initialization of GSC bucket can be skipped to boost the performance:
```yml
env_variables:
        GAE_SKIP_GCS_INIT: true
```
the storage path will be set to `/storage` directory of the GCS bucket and storage
directory structure creation will be skipped.

If not used the filesystem initialization can be removed to minimize GCS usage. In order to
do so, remove the following line from `.env.production` file:

```php
FILESYSTEM = gae
```

## Deploy

Backup the existing `.env` file if needed and rename the generated `.env.production` to `.env`
before deploying your app.

Download and install GAE SDK for PHP and deploy your app.
