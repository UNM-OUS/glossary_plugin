<?php

namespace DigraphCMS_Plugins\unmous\glossary;

use DateTime;
use DigraphCMS\Content\AbstractPage;
use DigraphCMS\Content\Pages;
use DigraphCMS\DB\DB;
use DigraphCMS\Digraph;
use DigraphCMS\RichContent\RichContent;
use DigraphCMS\Session\Session;
use DigraphCMS\Users\User;
use DigraphCMS\Users\Users;
use Envms\FluentPDO\Queries\Select;

class GlossaryTerm
{

    protected $uuid;

    protected $page_uuid;

    protected $name;

    protected $link;

    protected $body;

    protected $created;

    protected $created_by;

    protected $updated;

    protected $updated_by;

    protected bool $global_term;

    public function __construct(
        string $page_uuid,
        string $name,
        string $body,
        string|null $link = null,
        int|null $created = null,
        string|null $created_by = null,
        string|null $updated = null,
        string|null $updated_by = null,
        string|null $uuid = null,
        bool $global_term = false,
    )
    {
        $this->name = $name;
        $this->uuid = $uuid ?? Digraph::uuid();
        $this->page_uuid = $page_uuid;
        $this->link = $link ? $link : null;
        $this->body = $body;
        $this->created = $created ?? time();
        $this->created_by = $created_by ?? Session::uuid();
        $this->updated = $updated ?? time();
        $this->updated_by = $updated_by ?? Session::uuid();
        $this->global_term = $global_term;
    }

    public function global(): bool
    {
        return $this->global_term;
    }

    public function patterns(): Select
    {
        return DB::query()->from('glossary_pattern')
            ->where('glossary_term_uuid = ?', [$this->uuid()]);
    }

    public function pageUUID(): string
    {
        return $this->page_uuid;
    }

    public function page(): AbstractPage
    {
        return Pages::get($this->pageUUID());
    }

    public function cardContent(bool $extra_context = false): string
    {
        $out = $this->link()
            ? '<strong><a href="' . $this->link() . '" target="_blank">' . $this->name() . '</a></strong>'
            : '<strong>' . $this->name() . '</strong>';
        $out .= new RichContent($this->body());
        if ($extra_context) {
            $out .= '<p><small><strong>From:</strong> ' . $this->page()->url()->html() . '</small></p>';
        }
        return $out;
    }

    /**
     * Set Page
     */
    public function setPage(AbstractPage $page): static
    {
        $this->page_uuid = $page->uuid();
        return $this;
    }

    /**
     * Add a pattern, overwriting if it already exists
     */
    public function addPattern(string $pattern): static
    {
        DB::beginTransaction();
        static::deletePattern($pattern);
        DB::query()->insertInto(
            'glossary_pattern',
            [
                'glossary_term_uuid' => $this->uuid(),
                'pattern'            => $pattern,
            ],
        )->execute();
        DB::commit();
        return $this;
    }

    /**
     * Delete a pattern matching the given pattern
     */
    public function deletePattern(string $pattern): static
    {
        DB::query()->delete('glossary_pattern')
            ->where('glossary_term_uuid = ?', [$this->uuid()])
            ->where('pattern = ?', [$pattern])
            ->execute();
        return $this;
    }

    public function delete()
    {
        DB::beginTransaction();
        DB::query()->delete('glossary_pattern')
            ->where('glossary_term_uuid = ?', [$this->uuid()])
            ->execute();
        DB::query()->delete('glossary_term')
            ->where('uuid = ?', [$this->uuid()])
            ->execute();
        DB::commit();
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set display name
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    /**
     * Set page UUID
     */
    public function setUUID(string $uuid): static
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function link(): ?string
    {
        return $this->link;
    }

    /**
     * Set link URL
     */
    public function setLink(string|null $link = null): static
    {
        $this->link = $link ? $link : null;
        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Set body text
     */
    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function createdBy(): User
    {
        return Users::user($this->created_by);
    }

    public function updatedBy(): User
    {
        return Users::user($this->updated_by);
    }

    public function createdByUUID(): ?string
    {
        return $this->created_by;
    }

    public function updatedByUUID(): ?string
    {
        return $this->updated_by;
    }

    public function created(): DateTime
    {
        return (new DateTime)->setTimestamp($this->created);
    }

    public function updated(): DateTime
    {
        return (new DateTime)->setTimestamp($this->updated);
    }

}
