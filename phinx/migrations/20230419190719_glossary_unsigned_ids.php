<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class GlossaryUnsignedIds extends AbstractMigration
{
    public function change(): void
    {
        $tables = ['glossary_term', 'glossary_pattern'];
        foreach ($tables as $table) {
            $this->table($table)
                ->changeColumn('id', 'integer', ['signed' => false, 'null' => false, 'identity' => true])
                ->save();
        }
    }
}
