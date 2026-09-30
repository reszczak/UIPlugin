<?php

define('PLUGIN_UPLOADSCCGLPI_VERSION', '1.1.0');
define('PLUGIN_UPLOADSCCGLPI_MIN_GLPI', '11.0.0');
define('PLUGIN_UPLOADSCCGLPI_MAX_GLPI', '11.99.99');
define('PLUGIN_UPLOADSCCGLPI_TABLE', 'glpi_plugin_uploadsccglpi_uploadedfiles');
define('PLUGIN_UPLOADSCCGLPI_VERSIONS_TABLE', 'glpi_plugin_uploadsccglpi_agentversions');

function plugin_init_uploadsccglpi(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['uploadsccglpi'] = true;

    $lang = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? '');
    $phpfile = __DIR__ . '/locales/' . $lang . '.php';
    if ($lang !== '' && is_file($phpfile) && isset($GLOBALS['TRANSLATE'])) {
        $GLOBALS['TRANSLATE']->clearCache('uploadsccglpi', $lang);
        $GLOBALS['TRANSLATE']->addTranslationFile('phparray', $phpfile, 'uploadsccglpi', $lang);
    }

    $PLUGIN_HOOKS['config_page']['uploadsccglpi'] = 'front/config.form.php';

    Plugin::registerClass('PluginUploadsccglpiUploadedFile');

    if (Session::getLoginUserID()) {
        if (
            isset($_SESSION['glpimenu'])
            && ($_SESSION['glpimenu']['uploadsccglpi']['title'] ?? null)
                !== PluginUploadsccglpiUploadedFile::getMenuName()
        ) {
            unset($_SESSION['glpimenu']);
        }

        $PLUGIN_HOOKS['menu_toadd']['uploadsccglpi'] = [
            'uploadsccglpi' => [PluginUploadsccglpiUploadedFile::class],
        ];
    }
}

function plugin_version_uploadsccglpi(): array
{
    return [
        'name'         => 'uploadSCCGLPI',
        'version'      => PLUGIN_UPLOADSCCGLPI_VERSION,
        'author'       => 'reszcdaw',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_UPLOADSCCGLPI_MIN_GLPI, 'max' => PLUGIN_UPLOADSCCGLPI_MAX_GLPI],
        ],
    ];
}

function plugin_uploadsccglpi_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_UPLOADSCCGLPI_MIN_GLPI, 'lt')) {
        echo 'uploadSCCGLPI requires GLPI >= ' . PLUGIN_UPLOADSCCGLPI_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_uploadsccglpi_check_config(): bool
{
    return true;
}
