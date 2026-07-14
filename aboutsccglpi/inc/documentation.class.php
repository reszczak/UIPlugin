<?php
/**
 * Single documentation record: Markdown source + rendered HTML.
 */

use Glpi\Toolbox\MarkdownRenderer;

class PluginAboutsccglpiDocumentation extends CommonDBTM
{
    /** Id of the single document row. */
    public const SINGLETON_ID = 1;

    public static $rightname = 'config';

    public static function canUpdatePages(): bool
    {
        return (bool) Session::haveRight('config', UPDATE);
    }

    /** Loads the single document row, creating it in memory if missing. */
    public static function getSingleton(): self
    {
        $doc = new self();
        if (!$doc->getFromDB(self::SINGLETON_ID)) {
            $doc->getEmpty();
            $doc->fields['id']   = self::SINGLETON_ID;
            $doc->fields['name'] = __('About the Application', 'aboutsccglpi');
        }
        return $doc;
    }

    /** Persists new Markdown content (super-admin only). */
    public function saveContent(string $name, string $content): bool
    {
        if (!self::canUpdatePages()) {
            return false;
        }

        $input = [
            'id'       => self::SINGLETON_ID,
            'name'     => $name !== '' ? $name : __('About the Application', 'aboutsccglpi'),
            'content'  => $content,
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ];

        if ($this->getFromDB(self::SINGLETON_ID)) {
            return (bool) $this->update($input);
        }
        return (bool) $this->add($input);
    }

    /** Renders the stored Markdown as HTML (GitHub-flavored, with headings/TOC). */
    public function getRenderedHtml(): string
    {
        $markdown = (string) ($this->fields['content'] ?? '');
        if (trim($markdown) === '') {
            return '';
        }
        return (new MarkdownRenderer())->render($markdown);
    }
}
