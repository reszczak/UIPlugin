<?php

class PluginUploadsccglpiLastReport
{
    public static function save(int $userId, array $rows): void
    {
        global $DB;

        $DB->updateOrInsert(
            PLUGIN_UPLOADSCCGLPI_REPORT_TABLE,
            [
                'report'        => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ],
            ['users_id' => $userId]
        );
    }

    public static function load(int $userId): ?array
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => PLUGIN_UPLOADSCCGLPI_REPORT_TABLE,
            'WHERE' => ['users_id' => $userId],
        ])->current();
        if ($row === null) {
            return null;
        }

        $rows = json_decode((string) $row['report'], true);
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        return ['rows' => $rows, 'date' => (string) $row['date_creation']];
    }
}
