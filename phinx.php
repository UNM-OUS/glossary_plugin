<?php

use DigraphCMS\Cache\CacheableState;
use DigraphCMS\Cache\CachedInitializer;
use DigraphCMS\Config;
use DigraphCMS\DB\DB;

require_once __DIR__ . '/vendor/autoload.php';

// run initial configuration
CachedInitializer::run(
    'initialization',
    function (CacheableState $state) {
        $state->mergeConfig(Config::parseJsonFile(__DIR__ . '/env.json'), true);
        $state->config('paths.base', __DIR__ . '/demo');
        $state->config('paths.web', __DIR__ . '/demo');
    }
);

return [
    'paths'         => [
        'migrations' => array_merge(
            // note that for this project migrations have an extra demo path
            // this is where some extra migrations go that set up things
            // like the demo administrator account
            [__DIR__ . '/demo/migrations', __DIR__ . '/phinx/migrations'],
            DB::migrationPaths(),
        ),
        'seeds'      => DB::seedPaths(),
    ],
    'environments'  => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'current',
        'current'                 => [
            'name'       => 'Current environment',
            'connection' => DB::pdo(),
        ],
    ],
    'version_order' => 'creation',
];
