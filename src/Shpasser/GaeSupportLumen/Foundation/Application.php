<?php namespace Shpasser\GaeSupportLumen\Foundation;

use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Filesystem\Filesystem;
use Shpasser\GaeSupportLumen\Storage\CacheFs;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;


class Application extends LumenApplication {

    /**
     * AppIdentityService class instantiation is done using the class
     * name string so we can first check if the class exists and only then
     * instantiate it.
     */
    const GAE_ID_SERVICE = 'google\appengine\api\app_identity\AppIdentityService';

    /**
     * The GAE app ID.
     *
     * @var string
     */
    protected $appId;

    /**
     * 'true' if running on GAE.
     * @var boolean
     */
    protected $runningOnGae;

    /**
     * GAE storage bucket path.
     * @var string
     */
    protected $gaeBucketPath;

    /**
     * Create a new GAE supported application instance.
     */
    public function __construct()
    {
        $this->gaeBucketPath = null;

        // Load the 'realpath()' function replacement
        // for GAE storage buckets.
        require_once(__DIR__ . '/gae_realpath.php');

        $this->detectGae();

        if ($this->isRunningOnGae())
        {
            $this->initializeGaeBucket();
            $this->replaceDefaultSymfonyLineDumpers();
            $this->initializeCacheFs();
        }

        parent::__construct();
    }

    /**
     * Initializes the GCS Bucket.
     */
    protected function initializeGaeBucket()
    {
        if ( ! is_null($this->gaeBucketPath))
        {
            return;
        }

        $buckets = ini_get('google_app_engine.allow_include_gs_buckets');
        // Get the first bucket in the list.
        $bucket = current(explode(', ', $buckets));

        if ($bucket)
        {
            $this->gaeBucketPath = "gs://{$bucket}/storage";

            if (env('GAE_SKIP_GCS_INIT') === true)
            {
                return $this->gaeBucketPath;
            }

            if ( ! file_exists($this->gaeBucketPath))
            {
                mkdir($this->gaeBucketPath);
                mkdir($this->gaeBucketPath.'/app');
                mkdir($this->gaeBucketPath.'/framework');
                mkdir($this->gaeBucketPath.'/framework/views');
            }

            $this->useStoragePath($this->gaeBucketPath);
        }
    }

    /**
     * Initializes the Cache Filesystem.
     */
    protected function initializeCacheFs()
    {
        CacheFs::register();
    }

    /**
     * Adds the requested file to cache.
     *
     * @param string $path path to the file to be cached.
     * @param string $cachefsPath path for the cached file(under 'cachefs://').
     */
    protected function cacheFile($path, $cachefsPath)
    {
        if (file_exists($path))
        {
            $contents = file_get_contents($path);
            file_put_contents($cachefsPath, $contents);
        }
    }

    /**
     * Detect if the application is running on GAE.
     */
    protected function detectGae()
    {
        if ( ! class_exists(self::GAE_ID_SERVICE))
        {
            $this->runningOnGae = false;
            $this->appId = null;

            return;
        }

        $AppIdentityService = self::GAE_ID_SERVICE;
        $this->appId = $AppIdentityService::getApplicationId();
        $this->runningOnGae = ! preg_match('/dev~/', getenv('APPLICATION_ID'));
    }

    /**
     * Replaces the default output stream of Symfony's
     * CliDumper and HtmlDumper classes in order to
     * be able to run on Google App Engine.
     *
     * 'php://stdout' is used by CliDumper,
     * 'php://output' is used by HtmlDumper,
     * both are not supported on GAE.
     */
    protected function replaceDefaultSymfonyLineDumpers()
    {
        HtmlDumper::$defaultOutput =
        CliDumper::$defaultOutput =
            function($line, $depth, $indentPad)
            {
                if (-1 !== $depth)
                {
                    echo str_repeat($indentPad, $depth).$line."\n";
                }
            };
    }

    /**
     * Returns 'true' if running on GAE.
     *
     * @return bool
     */
    public function isRunningOnGae()
    {
        return $this->runningOnGae;
    }

    /**
     * Returns the GAE app ID.
     *
     * @return string
     */
    public function getGaeAppId()
    {
        return $this->appId;
    }

    /**
     * Overrides the default implementation in order to
     * return a Syslog Monolog handler when running on GAE.
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    protected function getMonologHandler()
    {
        if ($this->isRunningOnGae())
        {
            return new SyslogHandler('intranet', 'user', Logger::DEBUG, false, LOG_PID);
        }

        return parent::getMonologHandler();
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerMailBindings()
    {
        $this->singleton('mailer', function () {
            $this->configure('services');

            return $this->loadComponent('mail',
                                        'Shpasser\GaeSupportLumen\Mail\MailServiceProvider',
                                        'mailer');
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerQueueBindings()
    {
        $this->singleton('queue', function () {
            return $this->loadComponent('queue',
                                        'Shpasser\GaeSupportLumen\Queue\QueueServiceProvider',
                                        'queue');
        });

        $this->singleton('queue.connection', function () {
            return $this->loadComponent('queue',
                                        'Shpasser\GaeSupportLumen\Queue\QueueServiceProvider',
                                        'queue.connection');
        });
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * (Overriding Container::bound)
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->availableBindings[$abstract]) || parent::bound($abstract);
    }

}