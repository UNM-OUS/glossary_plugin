<h1>Edit glossary term</h1>
<?php

use DigraphCMS\Context;
use DigraphCMS\DB\DB;
use DigraphCMS\HTML\Forms\Field;
use DigraphCMS\HTML\Forms\Fields\CheckboxField;
use DigraphCMS\HTML\Forms\FormWrapper;
use DigraphCMS\HTTP\HttpError;
use DigraphCMS\HTTP\RedirectException;
use DigraphCMS\RichContent\RichContentField;
use DigraphCMS\Session\Session;
use DigraphCMS\UI\Breadcrumb;
use DigraphCMS\UI\Notifications;
use DigraphCMS\URL\URL;
use DigraphCMS_Plugins\unmous\glossary\Glossary;

Breadcrumb::parent(new URL('_manage_page_glossary'));

$term = Glossary::get(Context::arg_string('id'));
if (!$term || $term->pageUUID() != Context::pageUUID())
    throw new HttpError(404);

$form = new FormWrapper();
$form->button()->setText('Save term');

$name = (new Field('Glossary term'))
    ->setDefault($term->name())
    ->setRequired(true)
    ->addForm($form);

$body = (new RichContentField('Card content', Context::pageUUID(), true))
    ->setDefault($term->body())
    ->setRequired(true)
    ->addForm($form);

$url = (new Field('Link URL'))
    ->setDefault($term->link())
    ->addTip('Matching text will automatically be linked to this URL, if provided.')
    ->addForm($form);

$global = (new CheckboxField('Global term'))
    ->setDefault($term->global())
    ->addTip('If checked, this term will be matched site-wide without this page needing to be specified as a glossary source anywhere')
    ->addForm($form);

if ($form->ready()) {
    try {
        DB::beginTransaction();
        // update term record
        DB::query()->update(
            'glossary_term',
            [
                'name'        => $name->value(),
                'link'        => $url->value() ? $url->value() : null,
                'body'        => $body->value()->source(),
                'updated'     => time(),
                'updated_by'  => Session::uuid(),
                'global_term' => $global->value(),
            ],
        )
            ->where('uuid', $term->uuid())
            ->execute();
        // add a pattern for the straight term name if it doesn't already exist
        $existing_term_query = DB::query()
            ->from('glossary_pattern')
            ->where('glossary_term_uuid', $term->uuid())
            ->where('pattern', strtolower($name->value()));
        if ($existing_term_query->count() == 0)
            DB::query()->insertInto(
                'glossary_pattern',
                [
                    'glossary_term_uuid' => $term->uuid(),
                    'pattern'            => strtolower($name->value()),
                ],
            )->execute();
        // commit and flash confirmation
        DB::commit();
        Notifications::flashConfirmation('Added glossary term');
    }
    catch (\Throwable $th) {
        DB::rollback();
        Notifications::flashError('Error: ' . $th->getMessage());
    }
    throw new RedirectException(new URL('manage_page_glossary.html'));
}

echo $form;
