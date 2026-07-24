<?php

use DigraphCMS\Content\Pages;
use DigraphCMS\Context;
use DigraphCMS\HTTP\HttpError;
use DigraphCMS\UI\Breadcrumb;
use DigraphCMS_Plugins\unmous\glossary\Glossary;
use DigraphCMS_Plugins\unmous\glossary\GlossaryTerm;

Context::response()->enableCache();

$from = Context::arg_string('from', true);
if ($from !== null) {
    $from = Pages::get($from);
    if (!$from)
        throw new HttpError(404);
    Breadcrumb::parent($from->url());
}

$terms = explode(',', Context::url()->actionSuffix());
$terms = array_map(
    fn(string $uuid): GlossaryTerm => Glossary::get($uuid) ?? throw new HttpError(404),
    $terms,
);
$terms = array_filter($terms);

if (!$terms)
    throw new HttpError(404);

Context::response()->template('minimal.php');

echo '<div id="_popover_content">';

foreach ($terms as $term) {
    echo '<div class="card glossary-term glossary-term--' . $term->uuid() . '">';
    echo $term->cardContent($term->pageUUID() !== $from?->uuid());
    echo '</div>';
}

echo '</div>';
