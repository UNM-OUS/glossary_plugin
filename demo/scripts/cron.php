<?php
/**
 * This script should be run automatically in the background to enable cron and
 * background jobs. If you are using the included Dev Container config to run
 * the demo in a container it should be pre-configured in the container.
 * 
 * If you are using Digraph in production a similar script should be used and
 * similar cron entries used to execute it.
 */

use DigraphCMS\Cache\CacheableState;
use DigraphCMS\Cache\CachedInitializer;
use DigraphCMS\Config;
use DigraphCMS\Context;
use DigraphCMS\Cron\Cron;
use DigraphCMS\ExceptionLog;
use DigraphCMS\HTTP\Request;
use DigraphCMS\HTTP\RequestHeaders;
use DigraphCMS\URL\URL;
use DigraphCMS\URL\WaybackMachine;

// no error reporting
error_reporting(0);

try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    // load config
    CachedInitializer::config(
        function (CacheableState $state) {
            $state->mergeConfig(Config::parseYamlFile(__DIR__ . '/../config.yaml'), true);
            $state->mergeConfig(Config::parseYamlFile(__DIR__ . '/../env.yaml'), true);
            $state->config('paths.base', __DIR__ . '/..');
            $state->config('paths.web', __DIR__ . '/..');
        }
    );
    // run cron/background jobs
    WaybackMachine::deactivate();
    Context::beginUrlContext(new URL('/'));
    Context::request(new Request(new URL('/~cron/'), 'get', new RequestHeaders(), []));
    set_time_limit(65);
    Cron::runJobs(time() + 60);
    Context::end();
} catch (\Throwable $th) {
    // try to record errors in fancy log
    try {
        ExceptionLog::log($th);
    } catch (\Throwable $th) {
        // do nothing
    }
    // also record errors in log file
    $f = fopen(__DIR__ . '/../cron_error.log', 'w+');
    fwrite(
        $f,
        sprintf(
            'CRON ERROR: %s: %s' . PHP_EOL,
            time(),
            $th->getMessage(),
        )
    );
    fwrite($f, $th->getTraceAsString());
    fwrite($f, PHP_EOL . PHP_EOL);
    fclose($f);
}