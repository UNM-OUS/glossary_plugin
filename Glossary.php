<?php

namespace DigraphCMS_Plugins\unmous\glossary;

use DigraphCMS\Cache\Cache;
use DigraphCMS\Config;
use DigraphCMS\Context;
use DigraphCMS\DB\DB;
use DigraphCMS\HTTP\Response;
use DigraphCMS\Plugins\AbstractPlugin;
use DigraphCMS\URL\URL;
use DigraphCMS\Users\User;
use DOMComment;
use DOMElement;
use DOMNode;
use DOMText;
use Masterminds\HTML5;
use s9e\RegexpBuilder\Builder;

class Glossary extends AbstractPlugin
{

    const HIGHLIGHT_PAGE_ACTIONS = [
        'policy' => ['index'],
        'page'   => ['index'],
    ];

    const GLOSSARY_PAGE_EDITOR_ROUTES = [
        '_add_term',
        '_edit_term',
        '_link_glossaries',
        'included_glossaries',
        'manage_page_glossary',
    ];

    const GLOSSARY_PAGE_PUBLIC_ROUTES = [];

    protected static bool $parsing_active = false;

    public function onPageUrlPermissions(URL $url, User $user): bool|null
    {
        if ($url->actionPrefix() === 'glossary')
            return true;
        elseif (in_array($url->action(), self::GLOSSARY_PAGE_PUBLIC_ROUTES))
            return true;
        elseif (in_array($url->action(), self::GLOSSARY_PAGE_EDITOR_ROUTES))
            return $url->page()?->isEditor($user);
        else
            return null;
    }

    public function onTemplateWrapResponse(Response $response): void
    {
        // only run if response is the context response
        if ($response !== Context::response())
            return;
        // only operate on pages
        $page = Context::page();
        if (!$page)
            return;
        // only operate on specific filenames for a given page class
        $request = Context::request();
        $classes = $page->routeClasses();
        $highlight = false;
        foreach ($classes as $class) {
            if (!array_key_exists($class, self::HIGHLIGHT_PAGE_ACTIONS))
                continue;
            if (in_array($request->url()->action(), self::HIGHLIGHT_PAGE_ACTIONS[$class])) {
                $highlight = true;
                break;
            }
        }
        if (!$highlight)
            return;
        // extract additional page/term UUIDs to include from the page
        $page_uuids = $page['glossary.include_additional_pages'] ?? [];
        $page_uuids[$page->uuid()] = $page->uuid(); // add current page UUID
        assert(is_array($page_uuids));
        $term_uuids = $page['glossary.include_additional_terms'] ?? [];
        assert(is_array($term_uuids));
        // replace content with parsed HTML
        $response->content(
            static::parseHTML($response->content(), $page_uuids, $term_uuids),
        );
    }

    /**
     * Parse the given HTML and replace any glossary terms with links to the glossary
     *
     * @param string $html 
     * @param array<string> $page_uuids 
     * @param array<string> $term_uuids 
     * @return string 
     */
    public static function parseHTML(string $html, array $page_uuids, array $term_uuids): string
    {
        return Cache::get(
            'glossary/html/' . md5(serialize([$html, $page_uuids, $term_uuids])),
            function () use ($html, $page_uuids, $term_uuids) {
                static::$parsing_active = false;
                $patterns = static::allPatterns($page_uuids, $term_uuids);
                if (!$patterns)
                    return $html;
                $html5 = new HTML5();
                $fragment = $html5->parseFragment($html);
                $matched = [];
                static::parseElement($fragment, $patterns, $matched);
                static::$parsing_active = false;
                return $html5->saveHTML($fragment);
            },
            3600,
        );
    }

    public static function selectTerms(): TermSelect
    {
        return new TermSelect(DB::query()->from('glossary_term'));
    }

    public static function get(string|null $uuid): ?GlossaryTerm
    {
        if (!$uuid)
            return null;
        $result = static::selectTerms()->where('uuid = ?', [$uuid])->fetch();
        return $result ? $result : null;
    }

    /**
     * Replace the text within the given element that matches any of the given patterns with the term definitions
     *
     * @param string $text 
     * @param array<array{0:string,1:string}> $patterns 
     * @param array<string> &$matched 
     */
    protected static function parseText(string $text, array $patterns, array &$matched = []): string
    {
        return preg_replace_callback(
            static::completeRegexPattern($patterns),
            function ($m) use (&$matched, $patterns) {
                // check if we've already matched this term
                if (in_array(strtolower($m[0]), $matched))
                    return $m[0];
                $matched[] = strtolower($m[0]);
                // determine that one or more term defintions exist
                $terms = static::matchingTerms($m[0], $patterns);
                if (!$terms)
                    return $m[0];
                $terms = array_map(fn(GlossaryTerm $term) => $term->uuid(), $terms);
                $terms = implode(',', $terms);
                $url = new URL('/~glossary_terms/term:' . $terms);
                if ($page = Context::pageUUID())
                    $url->setArg('from', $page);
                return sprintf(
                    '<a class="glossary-term" href="%s" target="_popover" rel="nofollow">%s</a>',
                    $url,
                    $m[0],
                );
            },
            $text,
        );
    }

    /**
     * Retrieve the terms matching a given string
     *
     * @param string $term
     * @param array<array{0:string,1:string}> $patterns
     * @return GlossaryTerm[]
     */
    protected static function matchingTerms(string $term, array $patterns): array
    {
        $terms = [];
        foreach ($patterns as list($pattern, $termID)) {
            if (preg_match('/\b' . $pattern . '\b/i', $term))
                if ($found = static::get($termID))
                    $terms[] = $found;
        }
        return $terms;
    }

    /**
     * Generate a shorter regex pattern that matches any of the given patterns
     *
     * @param array<array{0:string,1:string}> $patterns
     * @return string
     */
    protected static function completeRegexPattern(array $patterns): string
    {
        $hash = md5(serialize($patterns));
        return Cache::get(
            'glossary/patterns/' . $hash,
            function () use ($patterns): string {
                $builder = new Builder();
                $pattern = $builder->build(array_map(
                    function ($e) {
                        return strtolower($e[0]);
                    },
                    $patterns,
                ));
                $pattern = "/\\b$pattern\\b/i";
                return $pattern;
            },
            3600,
        );
    }

    /**
     * Generate a list of all patterns and the terms they are associated with, including all global terms as well as all terms from the specified page UUIDs in $page_uuids, and including any extra terms specified in $term_uuids.
     * 
     * @param array<string> $page_uuids
     * @param array<string> $term_uuids
     * @return array<array{0:string,1:string}>
     */
    protected static function allPatterns(array $page_uuids = [], array $term_uuids = []): array
    {
        $hash = md5(serialize([$page_uuids, $term_uuids]));
        return Cache::get(
            'glossary/allpatterns/' . $hash,
            function () use ($page_uuids, $term_uuids) {
                // set up basic query to get all global terms
                $query = DB::query()
                    ->from('glossary_pattern')
                    ->disableSmartJoin()
                    ->select('*', true)
                    ->leftJoin('glossary_term ON glossary_pattern.glossary_term_uuid = glossary_term.uuid')
                    ->where('glossary_term.global_term = 1');
                // also include anything from the specified term uuids
                foreach ($term_uuids as $term_uuid) {
                    $query->whereOr('uuid', $term_uuid);
                }
                // also include anything from the specified page uuids
                foreach ($page_uuids as $page_uuid) {
                    $query->whereOr('page_uuid', $page_uuid);
                }
                // turn into an array of patterns and term UUIDs
                $patterns = array_map(
                    /**
                     * @return array{0:string,1:string}
                     */
                    function ($row): array {
                        return [
                            $row['pattern'],
                            $row['glossary_term_uuid'],
                        ];
                    },
                    $query->fetchAll() // @phpstan-ignore-line an error here is good
                );
                // sort by length
                usort(
                    $patterns,
                    /**
                     * @param  array{0:string,1:string} $a
                     * @param  array{0:string,1:string} $b
                     */
                    function (array $a, array $b): int {
                        return strlen($b[0]) - strlen($a[0]);
                    }
                );
                // return
                return $patterns;
            },
            3600,
        );
    }

    /**
     * @param array<array{0:string,1:string}> $patterns
     * @param array<string> &$matched
     */
    public static function parseElement(DOMNode $element, array $patterns, array &$matched = []): void
    {
        // do nothing if config disables glossary highlighting
        if (!Config::get('glossary.highlights_enabled'))
            return;
        if ($element instanceof DOMText) {
            // do parsing of text
            $newText = static::parseText($element->textContent, $patterns, $matched);
            if ($newText != $element->textContent) {
                $newChild = $element->ownerDocument->createDocumentFragment();
                $newChild->appendXML($newText);
                $element->parentNode->replaceChild($newChild, $element);
            }
        } elseif ($element instanceof DOMElement) {
            // allow data-no-glossary attribute to skip glossary searching in an element
            if ($element->getAttribute('data-no-glossary'))
                return;
            // reset matches on some elements
            if (in_array($element->tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']))
                $matched = [];
            // don't search inside some elements
            if (in_array($element->tagName, ['a', 'select', 'pre', 'textarea', 'form', 'code', 'input', 'h1', 'h2', 'h3']))
                return;
            // don't search in some classes
            $classes = explode(' ', $element->getAttribute('class'));
            if (array_intersect($classes, ['form-wrapper', 'form-field', 'menubar', 'notification']))
                return;
        } elseif ($element instanceof DOMComment) {
            // allow using comments to start/stop glossary highlighting
            $text = trim(strtolower($element->textContent));
            if ($text == 'glossary_highlight_start')
                static::$parsing_active = true;
            elseif ($text == 'glossary_highlight_end')
                static::$parsing_active = false;
            return;
        }
        // recurse if possible
        if ($element->hasChildNodes()) {
            $children = [];
            foreach ($element->childNodes as $child) {
                $children[] = $child;
            }
            //loop through new array of child nodes
            foreach ($children as $child) {
                static::parseElement($child, $patterns, $matched);
            }
        }
    }
}
