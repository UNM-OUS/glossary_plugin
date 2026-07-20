<?php

use DigraphCMS\Cache\CacheableState;
use DigraphCMS\Cache\CachedInitializer;
use DigraphCMS\Config;
use DigraphCMS\DB\DB;
use DigraphCMS\Digraph;
use DigraphCMS\Media\Media;
use DigraphCMS\Plugins\Plugins;
use DigraphCMS\URL\URLs;
use Mimey\MimeTypes;

// display all errors in demo site, for development purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// autoload and CacheInitializer::configureCache must be called absolutely first
// if you want Config to be able to cache its setup process
require_once __DIR__ . "/../vendor/autoload.php";

// special case for running in PHP's built-in server, to pass through static files
if (php_sapi_name() === 'cli-server') {
    URLs::$sitePath = '';
    $url = Digraph::actualUrl();
    if ($url->path() == '/favicon.ico' || substr($url->path(), 0, 7) == '/files/') {
        $filePath = __DIR__ . $url->path();
        if (file_exists($filePath)) {
            header(sprintf(
                "Content-Type: %s",
                (new MimeTypes)->getMimeType(strtolower(pathinfo($filePath, FILEINFO_EXTENSION))),
            ));
            header("Content-Length: " . filesize($filePath));
            readfile($filePath);
            exit;
        }
        else {
            http_response_code(404);
            header("Content-Type: text/plain");
            echo "Not found";
            exit;
        }
    }
}

// run initial configuration
CachedInitializer::config(
    function (CacheableState $state) {
        $state->mergeConfig(Config::parseYamlFile(__DIR__ . '/../../global.yaml'), true);
        $state->mergeConfig(Config::parseYamlFile(__DIR__ . '/config.yaml'), true);
        $state->mergeConfig(Config::parseYamlFile(__DIR__ . '/env.yaml'), true);
        $state->config('paths.base', __DIR__);
        $state->config('paths.web', __DIR__);
    }
);

// load composer plugins
Plugins::loadFromComposer(__DIR__ . '/../composer.lock');

// set demo site's media directory
Media::addSource(__DIR__ . '/media');

// load main repo as a plugin
Plugins::load(realpath(__DIR__ . '/..'), false);
DB::addPhinxPath(realpath(__DIR__ . '/../phinx'));

// build and render response
Digraph::renderActualRequest();
