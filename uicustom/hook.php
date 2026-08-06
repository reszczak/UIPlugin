<?php

function plugin_uicustom_install() {
    global $DB;

    $table = PLUGIN_UICUSTOM_TABLE;
    if (!$DB->tableExists($table)) {
        $sql = "CREATE TABLE `{$table}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `profiles_id` INT UNSIGNED NOT NULL,
            `is_active` TINYINT NOT NULL DEFAULT 1,
            `config` LONGTEXT NULL,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `profiles_id` (`profiles_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->doQuery($sql);
    }

    return true;
}

function plugin_uicustom_uninstall() {
    global $DB;
    if ($DB->tableExists(PLUGIN_UICUSTOM_TABLE)) {
        $DB->doQuery('DROP TABLE `' . PLUGIN_UICUSTOM_TABLE . '`');
    }
    return true;
}
