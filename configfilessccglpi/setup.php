<?php

define('PLUGIN_CONFIGFILESSCCGLPI_VERSION', '1.0.0');
define('PLUGIN_CONFIGFILESSCCGLPI_MIN_GLPI', '11.0.0');
define('PLUGIN_CONFIGFILESSCCGLPI_MAX_GLPI', '11.99.99');

function plugin_init_configfilessccglpi(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['configfilessccglpi'] = true;

    $lang = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? '');
    $phpfile = __DIR__ . '/locales/' . $lang . '.php';
    if ($lang !== '' && is_file($phpfile) && isset($GLOBALS['TRANSLATE'])) {
        $GLOBALS['TRANSLATE']->addTranslationFile('phparray', $phpfile, 'configfilessccglpi', $lang);
    }

    $PLUGIN_HOOKS['add_javascript']['configfilessccglpi'] = 'js/frame-resize.js';

    Plugin::registerClass('PluginConfigfilessccglpiComputertab', ['addtabon' => ['Computer']]);
    Plugin::registerClass('PluginConfigfilessccglpiLogbookTab', ['addtabon' => ['Computer']]);
}

function plugin_version_configfilessccglpi(): array
{
    return [
        'name'         => 'configFilesSCCGLPI',
        'version'      => PLUGIN_CONFIGFILESSCCGLPI_VERSION,
        'author'       => 'reszcdaw',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_CONFIGFILESSCCGLPI_MIN_GLPI, 'max' => PLUGIN_CONFIGFILESSCCGLPI_MAX_GLPI],
        ],
    ];
}

function plugin_configfilessccglpi_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_CONFIGFILESSCCGLPI_MIN_GLPI, 'lt')) {
        echo 'configFilesSCCGLPI requires GLPI >= ' . PLUGIN_CONFIGFILESSCCGLPI_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_configfilessccglpi_check_config(): bool
{
    return true;
}
