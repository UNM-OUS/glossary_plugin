<h1>Manage included glossaries</h1>
<p>
    Use this interface to include additional terms from other pages' glossaries. You can either include the entire glossary of another page, or individual terms.
</p>
<?php

use DigraphCMS\Content\Pages;
use DigraphCMS\Context;
use DigraphCMS\UI\CallbackLink;
use DigraphCMS\UI\Notifications;
use DigraphCMS\UI\Pagination\PaginatedList;
use DigraphCMS_Plugins\unmous\glossary\Glossary;

$current_page = Context::page();

printf('<p><a href="%s">Link more glossaries to this page</a><p>', $current_page->url('_link_glossaries'));

echo '<h2>Included entire page glossaries</h2>';

echo new PaginatedList(
    $current_page['glossary.include_additional_pages'] ?? [],
    function (string $uuid) use ($current_page): string {
        $page = Pages::get($uuid);
        if (!$page)
            $output = '[unknown page ' . $uuid . ']';
        else
            $output = sprintf('<a href="%s">%s</a>', $page->url('manage_page_glossary'), $page->name());
        $remove = new CallbackLink(
            function () use ($current_page, $uuid) {
                $current_page->unset('glossary.include_additional_pages.' . $uuid);
                $current_page->update();
                Notifications::flashConfirmation('Removed glossary page');
            },
            null,
        );
        $remove->addChild('[remove]');
        return "$output <small>$remove</small>";
    }
);

echo '<h2>Included individual terms</h2>';

echo new PaginatedList(
    $current_page['glossary.include_additional_terms'] ?? [],
    function (string $uuid) use ($current_page): string {
        $term = Glossary::get($uuid);
        if (!$term)
            $output = '[unknown term ' . $uuid . ']';
        else {
            $page = $term->page();
            $output = sprintf('%s: %s', $page->url()->html(), $term->name());
        }
        $remove = new CallbackLink(
            function () use ($current_page, $uuid) {
                $current_page->unset('glossary.include_additional_terms.' . $uuid);
                $current_page->update();
                Notifications::flashConfirmation('Removed glossary term');
            },
            null,
        );
        $remove->addChild('[remove]');
        return "$output <small>$remove</small>";
    }
);
