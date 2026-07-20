<?php

namespace DigraphCMS_Plugins\unmous\glossary;

use DigraphCMS\Context;

/**
 * @internal
 */
class Migrator
{

    public static function migrate(): void
    {
        require_once __DIR__ . '/../phinx.php';
        Context::sentry()->migrateDB();
    }

}
