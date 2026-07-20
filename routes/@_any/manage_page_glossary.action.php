<h1>Manage glossary terms</h1>
<?php

use DigraphCMS\Context;
use DigraphCMS\HTML\Forms\FormWrapper;
use DigraphCMS\HTML\Forms\INPUT;
use DigraphCMS\HTTP\RefreshException;
use DigraphCMS\UI\Pagination\ColumnSortingHeader;
use DigraphCMS\UI\Pagination\PaginatedTable;
use DigraphCMS\UI\Toolbars\ToolbarLink;
use DigraphCMS\URL\URL;
use DigraphCMS_Plugins\unmous\glossary\Glossary;
use DigraphCMS_Plugins\unmous\glossary\GlossaryTerm;

echo "<div id='manage-glossary-terms' class='navigation-frame navigation-frame--stateless' data-no-glossary='true'>";

$terms = Glossary::selectTerms()
    ->where('page_uuid = ?', [Context::pageUUID()])
    ->order('name ASC');
$table = new PaginatedTable(
    $terms,
    function (GlossaryTerm $term): array {
        $addPatternForm = new FormWrapper('add_term_' . $term->uuid());
        $pattern = new INPUT('pattern');
        $pattern->setRequired(true);
        $addPatternForm->addChild($pattern);
        $addPatternForm->addClass('inline-autoform');
        $addPatternForm->setData('target', '_frame');
        $addPatternForm->addCallback(function () use ($pattern, $term) {
            $term->addPattern($pattern->value());
            throw new RefreshException();
        });
        return [
            implode(PHP_EOL, [
                (new ToolbarLink(
                    'Edit',
                    'edit',
                    null,
                    new URL('_edit_term.html?id=' . $term->uuid()),
                ))->setData('target', '_top'),
                (new ToolbarLink(
                    'Delete',
                    'delete',
                    function () use ($term) {
                        $term->delete();
                    },
                    null,
                    'delete_' . $term->uuid()
                ))->setData('target', '_frame'),
            ]),
            $term->cardContent(),
            '<div id="patterns-edit--' . $term->uuid() . '" class="navigation-frame navigation-frame--stateless">' .
            implode('<br>', array_map(
                function (array $p) use ($term) {
                    return sprintf(
                        "%s<code>%s</code>",
                        (new ToolbarLink(
                            'Delete',
                            'delete',
                            function () use ($term, $p) {
                                $term->deletePattern($p['pattern']);
                            },
                            null,
                            'delete_' . $term->uuid() . '_' . md5($p['pattern'])
                        ))->setData('target', '_frame'),
                        $p['pattern'],
                    );
                },
                $term->patterns()->fetchAll(),
            ))
            . $addPatternForm
            . '</div>'
        ];
    },
    [
        '',
        new ColumnSortingHeader('Term', 'name'),
        'Patterns',
    ],
);

echo '<a href="' . new URL('_add_term.html') . '" class="button" target="_top">Add glossary term</a>';
if ($terms->count())
    echo $table;

echo "</div>";
