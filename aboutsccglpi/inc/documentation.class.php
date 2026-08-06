<?php

use Glpi\Toolbox\MarkdownRenderer;

class PluginAboutsccglpiDocumentation extends CommonDBTM
{
    public const HOME_ID = 1;

    public static $rightname = 'config';

    public static function canUpdatePages(): bool
    {
        return (bool) Session::haveRight('config', UPDATE);
    }

    public static function getHome(): self
    {
        $doc = new self();
        if (!$doc->getFromDB(self::HOME_ID)) {
            $doc->getEmpty();
            $doc->fields['id']   = self::HOME_ID;
            $doc->fields['name'] = __('About the Application', 'aboutsccglpi');
        }
        return $doc;
    }

    public static function getPage(int $id): ?self
    {
        if ($id === self::HOME_ID) {
            return self::getHome();
        }
        $doc = new self();
        return $doc->getFromDB($id) ? $doc : null;
    }

    public static function getSubpages(): array
    {
        return (new self())->find(['id' => ['>', self::HOME_ID]], ['date_mod DESC']);
    }

    public function saveHome(string $name, string $content): bool
    {
        if (!self::canUpdatePages()) {
            return false;
        }

        $input = [
            'id'       => self::HOME_ID,
            'name'     => $name !== '' ? $name : __('About the Application', 'aboutsccglpi'),
            'content'  => $content,
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ];

        if ($this->getFromDB(self::HOME_ID)) {
            return (bool) $this->update($input);
        }
        return (bool) $this->add($input);
    }

    public function addSubpage(string $name, string $content): int
    {
        if (!self::canUpdatePages() || trim($name) === '') {
            return 0;
        }

        $id = $this->add([
            'name'     => $name,
            'content'  => $content,
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
        return $id !== false ? (int) $id : 0;
    }

    public function updateSubpage(int $id, string $name, string $content): bool
    {
        if (!self::canUpdatePages() || $id <= self::HOME_ID || trim($name) === '') {
            return false;
        }
        if (!$this->getFromDB($id)) {
            return false;
        }

        return (bool) $this->update([
            'id'       => $id,
            'name'     => $name,
            'content'  => $content,
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteSubpage(int $id): bool
    {
        if (!self::canUpdatePages() || $id <= self::HOME_ID) {
            return false;
        }
        if (!$this->getFromDB($id)) {
            return false;
        }
        return $this->delete(['id' => $id]);
    }

    public function getRenderedHtml(): string
    {
        $markdown = (string) ($this->fields['content'] ?? '');
        if (trim($markdown) === '') {
            return '';
        }
        return (new MarkdownRenderer())->render($markdown);
    }
}
