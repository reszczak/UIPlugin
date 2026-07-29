<?php
/**
 * Plugin lifecycle: creates/maintains the documentation table.
 */

function plugin_aboutsccglpi_install() {
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $sign               = DBConnection::getDefaultPrimaryKeySignOption();

    $table = PluginAboutsccglpiDocumentation::getTable();

    // Migrate data from the plugin's previous key/table name ("oaplikacji").
    if (!$DB->tableExists($table) && $DB->tableExists('glpi_plugin_oaplikacji_documentations')) {
        $DB->doQuery("RENAME TABLE `glpi_plugin_oaplikacji_documentations` TO `$table`");
    }

    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id`        int {$sign} NOT NULL AUTO_INCREMENT,
            `name`      varchar(255) DEFAULT NULL,
            `content`   longtext,
            `date_mod`  timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($query);
    }

    if (countElementsInTable($table) === 0) {
        $DB->insert($table, [
            'id'       => 1,
            'name'     => 'O Aplikacji',
            'content'  => '# Wklej tu opis aplikacji w formacie Markdown',
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    return true;
}

function plugin_aboutsccglpi_uninstall() {
    // Table is intentionally kept: it holds hand-written documentation
    // content, which should survive an uninstall/reinstall cycle.
    return true;
}
