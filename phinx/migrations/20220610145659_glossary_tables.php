<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class GlossaryTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('glossary_term')
            ->addColumn('uuid', 'uuid')
            ->addColumn('page_uuid', 'uuid')
            ->addColumn('name', 'string', ['length' => 250])
            ->addColumn('link', 'string', ['length' => 250, 'null' => true])
            ->addColumn('body', 'text', ['limit' => MysqlAdapter::TEXT_REGULAR])
            ->addColumn('created', 'integer')
            ->addColumn('created_by', 'uuid')
            ->addColumn('updated', 'integer')
            ->addColumn('updated_by', 'uuid')
            ->addForeignKey(['page_uuid'], 'page', ['uuid'])
            ->addForeignKey(['created_by'], 'user', ['uuid'])
            ->addForeignKey(['updated_by'], 'user', ['uuid'])
            ->addIndex('uuid', ['unique' => true])
            ->addIndex('name')
            ->create();
        $this->table('glossary_pattern')
            ->addColumn('glossary_term_uuid', 'uuid')
            ->addColumn('pattern', 'string', ['length' => 250])
            ->addColumn('regex', 'boolean')
            ->addForeignKey(['glossary_term_uuid'], 'glossary_term', ['uuid'])
            ->addIndex(['glossary_term_uuid', 'pattern'], ['unique' => true])
            ->create();
    }
}
