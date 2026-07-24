<h1>Link outside glossary terms to this page</h1>
<?php

use DigraphCMS\Content\AbstractPage;
use DigraphCMS\Content\Pages;
use DigraphCMS\Context;
use DigraphCMS\HTML\Forms\Field;
use DigraphCMS\HTML\Forms\Fields\Autocomplete\PageInput;
use DigraphCMS\HTML\Forms\Fields\CheckboxListField;
use DigraphCMS\HTML\Forms\FormWrapper;
use DigraphCMS\HTTP\HttpError;
use DigraphCMS\HTTP\RedirectException;
use DigraphCMS\UI\CallbackLink;
use DigraphCMS\UI\Notifications;
use DigraphCMS\URL\URL;
use DigraphCMS_Plugins\unmous\glossary\Glossary;

/** @var AbstractPage $current_page */
$current_page = Context::page();
$current_step = Context::arg_string('step', true);

if (is_null($current_step)) {
    // step one is to pick the page to add terms from
    echo '<h2>Pick a page to add terms from</h2>';
    $add_page = new FormWrapper('add_page');
    $add_page->button()->setText('Add page');
    $page_field = (new Field('Search pages', new PageInput()))
        ->setRequired(true)
        ->addForm($add_page);
    if ($add_page->ready()) {
        throw new RedirectException(new URL('?step=pick&page=' . $page_field->value()));
    }
    echo $add_page;
} elseif ($current_step == 'pick' && $page = Pages::get(Context::arg_string('page'))) {
    // step two is to either add all terms from this page, or select the ones to add
    echo '<h2>Select terms to add from ' . $page->name() . '</h2>';
    $add_all = new CallbackLink(
        function () use ($current_page, $page) {
            $current_page['glossary.include_additional_pages.' . $page->uuid()] = $page->uuid();
            $current_page->update();
            Notifications::flashConfirmation('Full glossary linked');
            throw new RedirectException($current_page->url('included_glossaries'));
        }
    );
    $add_all->addClass('button');
    $add_all->addChild('Link all current and future terms from this page');
    echo "<p>$add_all</p>";
    $terms = Glossary::selectTerms()
        ->where('page_uuid = ?', [$page->uuid()])
        ->order('name ASC');
    if ($terms->count() === 0) {
        Notifications::printNotice("This page currently has no glossary terms defined");
    } else {
        $select_form = new FormWrapper();
        $select_form->button()->setText("Link selected terms");
        $options = [];
        foreach ($terms as $term) {
            $options[$term->uuid()] = $term->name();
        }
        $select_field = (new CheckboxListField("", $options))
            ->setRequired(true)
            ->addForm($select_form);
        if ($select_form->ready()) {
            foreach ($select_field->value() as $uuid) {
                $current_page['glossary.include_additional_terms.' . $uuid] = $uuid;
            }
            $current_page->update();
            Notifications::printNotice('Linked additional terms');
            throw new RedirectException($current_page->url('included_glossaries'));
        }
        echo $select_form;
    }
} else {
    throw new HttpError(404);
}
