<?php

class PluginUploadsccglpiUploadedFile extends CommonDBTM
{
    public static $rightname = 'document';

    public const SCOPE_ALL = 'all';

    private const LIST_SCOPE_KEY = 'plugin_uploadsccglpi_list_scope';

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
        if (!self::canUpload()) {
            return false;
        }

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

    public static function computerEntities(string $uuid): array
    {
        global $DB;

        $entities = [];
        foreach ($DB->request([
            'SELECT'   => ['entities_id'],
            'DISTINCT' => true,
            'FROM'     => Computer::getTable(),
            'WHERE'    => [
                'uuid'        => $uuid,
                'is_deleted'  => 0,
                'is_template' => 0,
            ],
        ]) as $row) {
            $entities[] = (int) $row['entities_id'];
        }

        return $entities;
    }

    public static function canSeeEveryUpload(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canPurge(): bool
    {
        return self::canSeeEveryUpload() && parent::canPurge();
    }

    public static function listedUserId(): ?int
    {
        if (!self::canSeeEveryUpload()) {
            return (int) Session::getLoginUserID();
        }

        $stored = (int) ($_SESSION[self::LIST_SCOPE_KEY] ?? 0);

        return $stored > 0 ? $stored : null;
    }

    public static function listsEveryUpload(): bool
    {
        return self::listedUserId() === null;
    }

    public static function setListScope(string $scope): void
    {
        if (!self::canSeeEveryUpload()) {
            return;
        }

        if ($scope === self::SCOPE_ALL) {
            unset($_SESSION[self::LIST_SCOPE_KEY]);
        } elseif (ctype_digit($scope) && self::isSelectableUser((int) $scope)) {
            $_SESSION[self::LIST_SCOPE_KEY] = (int) $scope;
        }
    }

    private static function userQuery(array $conditions, int $limit): array
    {
        global $DB;

        $users    = User::getTable();
        $profiles = Profile_User::getTable();

        $found = [];
        foreach ($DB->request([
            'SELECT'     => ["{$users}.id"],
            'DISTINCT'   => true,
            'FROM'       => $users,
            'INNER JOIN' => [
                $profiles => ['ON' => [$users => 'id', $profiles => 'users_id']],
            ],
            'WHERE'      => array_merge(
                ["{$users}.is_deleted" => 0, getEntitiesRestrictCriteria($profiles, '', '', true)],
                $conditions
            ),
            'ORDER'      => ["{$users}.name"],
            'LIMIT'      => $limit,
        ]) as $row) {
            $found[] = (int) $row['id'];
        }

        return $found;
    }

    public static function isSelectableUser(int $userId): bool
    {
        return $userId > 0 && self::userQuery([User::getTable() . '.id' => $userId], 1) !== [];
    }

    public static function searchUsers(string $query, int $limit = 20): array
    {
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return [];
        }

        $users      = User::getTable();
        $conditions = [];
        foreach (array_slice($words, 0, 5) as $word) {
            $like         = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word) . '%';
            $conditions[] = ['OR' => [
                "{$users}.name"      => ['LIKE', $like],
                "{$users}.realname"  => ['LIKE', $like],
                "{$users}.firstname" => ['LIKE', $like],
            ]];
        }

        return self::userQuery($conditions, $limit + 1);
    }

    private function isOwnedByCurrentUser(): bool
    {
        return (int) ($this->fields['users_id'] ?? 0) === (int) Session::getLoginUserID();
    }

    public function canViewItem(): bool
    {
        return self::canSeeEveryUpload() ? parent::canViewItem() : $this->isOwnedByCurrentUser();
    }

    public function canPurgeItem(): bool
    {
        return self::canSeeEveryUpload() && parent::canPurgeItem();
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
        $userId = self::listedUserId();
        $own    = [self::getTable() . '.users_id' => (int) $userId];

        if (!self::canSeeEveryUpload() || $userId === (int) Session::getLoginUserID()) {
            return $own;
        }

        $entities = getEntitiesRestrictCriteria(self::getTable(), '', '', true);

        return $userId === null ? $entities : $entities + $own;
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
