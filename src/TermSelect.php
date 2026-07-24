<?php

namespace DigraphCMS_Plugins\unmous\glossary;

use DigraphCMS\DB\AbstractMappedSelect;

/**
 * Maps DB rows to GlossaryTerm objects
 * @extends AbstractMappedSelect<GlossaryTerm>
 */
class TermSelect extends AbstractMappedSelect
{

    protected function doRowToObject(array $row): ?object
    {
        return new GlossaryTerm(
            $row['page_uuid'],
            $row['name'],
            $row['body'],
            $row['link'],
            $row['created'],
            $row['created_by'],
            $row['updated'],
            $row['updated_by'],
            $row['uuid'],
            $row['global_term'],
        );
    }
}
