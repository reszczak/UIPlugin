<?php
class PluginUicustomProfileConfigRepository
{
    public function __construct(private ?\DBmysql $db = null)
    {
        $this->db = $db ?? $GLOBALS['DB'];
    }

    public static function defaultConfig(): array
    {
        return [
            'menu' => [
                'hidden_sectors'  => [],
                'sector_keep'     => [],
                'hide_dashboards' => false,
            ],
            'forms' => [],
        ];
    }

    public function get(int $profilesId, bool $onlyActive = true): ?array
    {
        $raw = $this->fetchRow($profilesId);
        if ($raw === null) {
            return null;
        }
        if ($onlyActive && (int) $raw['is_active'] !== 1) {
            return null;
        }
        return $this->decode($raw['config']);
    }

    public function getRaw(int $profilesId): ?array
    {
        $raw = $this->fetchRow($profilesId);
        return $raw === null ? null : $this->decode($raw['config']);
    }

    public function isActive(int $profilesId): bool
    {
        $raw = $this->fetchRow($profilesId);
        return $raw !== null && (int) $raw['is_active'] === 1;
    }

    public function save(int $profilesId, array $config, bool $isActive): void
    {
        $now  = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $data = [
            'is_active' => $isActive ? 1 : 0,
            'config'    => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'date_mod'  => $now,
        ];

        $existingId = $this->fetchId($profilesId);
        if ($existingId !== null) {
            $this->db->update(PLUGIN_UICUSTOM_TABLE, $data, ['id' => $existingId]);
            return;
        }

        $data['profiles_id']   = $profilesId;
        $data['date_creation'] = $now;
        $this->db->insert(PLUGIN_UICUSTOM_TABLE, $data);
    }

    public function delete(int $profilesId): void
    {
        $this->db->delete(PLUGIN_UICUSTOM_TABLE, ['profiles_id' => $profilesId]);
    }

    public static function adminProfileIds(): array
    {
        global $DB;
        $out = [];
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => 'config']]) as $row) {
            if (((int) $row['rights'] & UPDATE) === UPDATE) {
                $out[(int) $row['profiles_id']] = true;
            }
        }
        return $out;
    }

    public function allMeta(): array
    {
        $out = [];
        if (!$this->db->tableExists(PLUGIN_UICUSTOM_TABLE)) {
            return $out;
        }
        foreach ($this->db->request(['FROM' => PLUGIN_UICUSTOM_TABLE]) as $row) {
            $out[(int) $row['profiles_id']] = ['is_active' => (int) $row['is_active'] === 1];
        }
        return $out;
    }

    private function fetchRow(int $profilesId): ?array
    {
        if ($profilesId <= 0 || !$this->db->tableExists(PLUGIN_UICUSTOM_TABLE)) {
            return null;
        }
        foreach ($this->db->request(['FROM' => PLUGIN_UICUSTOM_TABLE, 'WHERE' => ['profiles_id' => $profilesId]]) as $row) {
            return $row;
        }
        return null;
    }

    private function fetchId(int $profilesId): ?int
    {
        foreach ($this->db->request(['FROM' => PLUGIN_UICUSTOM_TABLE, 'WHERE' => ['profiles_id' => $profilesId]]) as $row) {
            return (int) $row['id'];
        }
        return null;
    }

    private function decode(?string $json): array
    {
        $cfg = json_decode((string) $json, true);
        return is_array($cfg) ? $cfg : [];
    }
}
