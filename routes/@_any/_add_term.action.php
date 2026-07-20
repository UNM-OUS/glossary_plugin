<h1>Add glossary term</h1>
<?php

use DigraphCMS\Context;
use DigraphCMS\DB\DB;
use DigraphCMS\Digraph;
use DigraphCMS\HTML\Forms\Field;
use DigraphCMS\HTML\Forms\Fields\CheckboxField;
use DigraphCMS\HTML\Forms\FormWrapper;
use DigraphCMS\HTTP\RedirectException;
use DigraphCMS\RichContent\RichContentField;
use DigraphCMS\Session\Session;
use DigraphCMS\UI\Breadcrumb;
use DigraphCMS\UI\Notifications;
use DigraphCMS\URL\URL;

Breadcrumb::parent(new URL('_manage_page_glossary'));

$form = new FormWrapper();
$form->button()->setText('Add term');

$name = (new Field('Glossary term'))
    ->setRequired(true)
    ->addForm($form);

$body = (new RichContentField('Card content', Context::pageUUID(), true))
    ->setRequired(true)
    ->addForm($form);

$url = (new Field('Link URL'))
    ->addTip('Matching text will automatically be linked to this URL, if provided.')
    ->addForm($form);

$global = (new CheckboxField('Global term'))
    ->addTip('If checked, this term will be matched site-wide without this page needing to be specified as a glossary source anywhere')
    ->addForm($form);

if ($form->ready()) {
    try {
        DB::beginTransaction();
        DB::query()->insertInto(
            'glossary_term',
            [
                'uuid'        => $uuid = Digraph::uuid(),
                'page_uuid'   => Context::pageUUID(),
                'name'        => $name->value(),
                'link'        => $url->value() ? $url->value() : null,
                'body'        => $body->value()->source(),
                'created'     => time(),
                'created_by'  => Session::uuid(),
                'updated'     => time(),
                'updated_by'  => Session::uuid(),
                'global_term' => $global->value(),
            ],
        )->execute();
        DB::query()->insertInto(
            'glossary_pattern',
            [
                'glossary_term_uuid' => $uuid,
                'pattern'            => strtolower($name->value()),
            ],
        )->execute();
        DB::commit();
        Notifications::flashConfirmation('Added glossary term');
    }
    catch (\Throwable $th) {
        DB::rollback();
        Notifications::flashError('Error: ' . $th->getMessage());
    }
    throw new RedirectException(new URL(url: 'manage_page_glossary.html'));
}

echo $form;
