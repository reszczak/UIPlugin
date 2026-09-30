<?php

class PluginUploadsccglpiUploadedFile extends CommonDBTM
{
    public static $rightname = 'document';

    public static function getTable($classname = null)
    {
        return PLUGIN_UPLOADSCCGLPI_TABLE;
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1
            ? __('Uploaded files', 'uploadsccglpi')
            : __('Uploaded file', 'uploadsccglpi');
    }

    public static function getMenuName()
    {
        return __('Upload', 'uploadsccglpi');
    }

    public static function getIcon()
    {
        return 'ti ti-cloud-upload';
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/uploadsccglpi/front/upload.form.php',
            'icon'  => self::getIcon(),
        ];
    }

    public static function canUpload(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canSeeEveryUpload(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    private function isOwnedByCurrentUser(): bool
    {
        return (int) ($this->fields['users_id'] ?? 0) === (int) Session::getLoginUserID();
    }

    public function canViewItem(): bool
    {
        return parent::canViewItem()
            && (self::canSeeEveryUpload() || $this->isOwnedByCurrentUser());
    }

    public function canPurgeItem(): bool
    {
        return parent::canPurgeItem()
            && (self::canSeeEveryUpload() || $this->isOwnedByCurrentUser());
    }

    public static function siblingIds(array $fields): array
    {
        global $DB;

        $key = (string) ($fields['pair_key'] ?? '');
        if ($key === '') {
            return [(int) $fields['id']];
        }

        $ids = [];
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['pair_key' => $key] + self::visibilityCriteria(),
        ]) as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids !== [] ? $ids : [(int) $fields['id']];
    }

    private static function visibilityCriteria(): array
    {
        $criteria = getEntitiesRestrictCriteria(self::getTable(), '', '', true);

        if (!self::canSeeEveryUpload()) {
            $criteria[self::getTable() . '.users_id'] = (int) Session::getLoginUserID();
        }

        return $criteria;
    }

    public static function findVisible(int $limit = 100): array
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => self::visibilityCriteria(),
            'ORDER'  => ['date_creation DESC', 'id DESC'],
            'LIMIT'  => $limit,
        ]);

        return iterator_to_array($iterator, false);
    }

    public static function findVisiblePairs(int $limit = 100): array
    {
        $groups = [];

        foreach (self::findVisible($limit * 2) as $row) {
            $key = (string) ($row['pair_key'] ?? '');
            $key = $key !== '' ? 'pair:' . $key : 'row:' . $row['id'];

            $groups[$key]['key']      ??= (string) ($row['pair_key'] ?? '') ?: (string) $row['filename'];
            $groups[$key]['date']     ??= (string) $row['date_creation'];
            $groups[$key]['users_id'] ??= (int) $row['users_id'];
            $groups[$key]['rows'][]     = $row;
        }

        return array_slice(array_values($groups), 0, $limit);
    }

    public static function countVisiblePairs(): int
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['pair_key'],
            'FROM'   => self::getTable(),
            'WHERE'  => self::visibilityCriteria(),
            'GROUPBY' => ['pair_key'],
        ]);

        return count($iterator);
    }
}
