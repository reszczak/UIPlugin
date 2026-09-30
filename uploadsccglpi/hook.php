<?php

function plugin_uploadsccglpi_install()
{
    global $DB;

    $table = PLUGIN_UPLOADSCCGLPI_TABLE;
    if (!$DB->tableExists($table)) {
        $sql = "CREATE TABLE `{$table}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_recursive` TINYINT NOT NULL DEFAULT 0,
            `name` VARCHAR(255) NOT NULL DEFAULT '',
            `filename` VARCHAR(255) NOT NULL DEFAULT '',
            `filepath` VARCHAR(255) NOT NULL DEFAULT '',
            `storage_dir` VARCHAR(255) NOT NULL DEFAULT '',
            `pair_key` VARCHAR(255) NOT NULL DEFAULT '',
            `extension` VARCHAR(32) NOT NULL DEFAULT '',
            `mime` VARCHAR(255) NULL,
            `filesize` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `sha256` CHAR(64) NOT NULL DEFAULT '',
            `users_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `comment` TEXT NULL,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `filepath` (`filepath`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `sha256` (`sha256`),
            KEY `date_creation` (`date_creation`),
            KEY `pair_key` (`pair_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->doQuery($sql);
    } else {
        if (!$DB->fieldExists($table, 'storage_dir', false)) {
            $DB->doQuery(
                "ALTER TABLE `{$table}` ADD COLUMN `storage_dir` VARCHAR(255) NOT NULL DEFAULT '' AFTER `filepath`"
            );
            $DB->clearSchemaCache();
        }

        $unique = false;
        $indexes = $DB->doQuery("SHOW INDEX FROM `{$table}` WHERE Key_name = 'filepath'");
        while ($indexes && ($index = $DB->fetchAssoc($indexes))) {
            $unique = $unique || (string) $index['Non_unique'] === '0';
        }
        if ($unique) {
            $DB->doQuery("ALTER TABLE `{$table}` DROP INDEX `filepath`, ADD KEY `filepath` (`filepath`)");
            $DB->clearSchemaCache();
        }

        if (!$DB->fieldExists($table, 'pair_key', false)) {
            $DB->doQuery(
                "ALTER TABLE `{$table}` ADD COLUMN `pair_key` VARCHAR(255) NOT NULL DEFAULT '' AFTER `storage_dir`,
                 ADD KEY `pair_key` (`pair_key`)"
            );
            $DB->clearSchemaCache();
        }
    }

    $versions = PLUGIN_UPLOADSCCGLPI_VERSIONS_TABLE;
    if (!$DB->tableExists($versions)) {
        $DB->doQuery("CREATE TABLE `{$versions}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `platform` VARCHAR(32) NOT NULL DEFAULT '',
            `version` VARCHAR(32) NOT NULL DEFAULT '',
            `knowbaseitems_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            `date_sync` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `platform` (`platform`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    PluginUploadsccglpiAgentVersions::sync();

    $current = Config::getConfigurationValues(PluginUploadsccglpiConfig::CONTEXT);
    $missing = [];
    foreach (PluginUploadsccglpiConfig::DEFAULTS as $key => $value) {
        if (!isset($current[$key])) {
            $missing[$key] = $value;
        }
    }
    if ($missing !== []) {
        Config::setConfigurationValues(PluginUploadsccglpiConfig::CONTEXT, $missing);
    }

    Config::deleteConfigurationValues(
        PluginUploadsccglpiConfig::CONTEXT,
        PluginUploadsccglpiConfig::OBSOLETE_KEYS
    );

    (new PluginUploadsccglpiStorage())->ensureRootDir();

    return true;
}

function plugin_uploadsccglpi_uninstall()
{
    global $DB;

    foreach ([PLUGIN_UPLOADSCCGLPI_TABLE, PLUGIN_UPLOADSCCGLPI_VERSIONS_TABLE] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery('DROP TABLE `' . $table . '`');
        }
    }

    Config::deleteConfigurationValues(
        PluginUploadsccglpiConfig::CONTEXT,
        array_merge(
            array_keys(PluginUploadsccglpiConfig::DEFAULTS),
            PluginUploadsccglpiConfig::OBSOLETE_KEYS
        )
    );

    return true;
}
