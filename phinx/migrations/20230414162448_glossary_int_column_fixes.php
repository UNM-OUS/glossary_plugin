<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class GlossaryIntColumnFixes extends AbstractMigration
{
    public function change(): void
    {
        $update = [
            'glossary_term' => ['created', 'updated'],
        ];
        foreach ($update as $table => $columns) {
            $table = $this->table($table);
            foreach ($columns as $column) {
                $table->changeColumn($column, 'biginteger', ['null' => false, 'signed' => false]);
            }
            $table->save();
        }
    }
}
