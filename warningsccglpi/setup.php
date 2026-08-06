<?php

define('PLUGIN_WARNINGSCCGLPI_VERSION', '1.0.0');
define('PLUGIN_WARNINGSCCGLPI_MIN_GLPI', '11.0.0');
define('PLUGIN_WARNINGSCCGLPI_MAX_GLPI', '11.99.99');

function plugin_init_warningsccglpi(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['warningsccglpi'] = true;

    $lang = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? '');
    $phpfile = __DIR__ . '/locales/' . $lang . '.php';
    if ($lang !== '' && is_file($phpfile) && isset($GLOBALS['TRANSLATE'])) {
        $GLOBALS['TRANSLATE']->addTranslationFile('phparray', $phpfile, 'warningsccglpi', $lang);
    }

    $PLUGIN_HOOKS['config_page']['warningsccglpi'] = 'front/config.form.php';

    $PLUGIN_HOOKS['add_javascript']['warningsccglpi'] = 'js/banner.js';
    $PLUGIN_HOOKS['add_css']['warningsccglpi'] = 'css/banner.css';
}

function plugin_version_warningsccglpi(): array
{
    return [
        'name'         => 'warningSCCGLPI',
        'version'      => PLUGIN_WARNINGSCCGLPI_VERSION,
        'author'       => 'reszcdaw',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_WARNINGSCCGLPI_MIN_GLPI, 'max' => PLUGIN_WARNINGSCCGLPI_MAX_GLPI],
        ],
    ];
}

function plugin_warningsccglpi_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_WARNINGSCCGLPI_MIN_GLPI, 'lt')) {
        echo 'warningSCCGLPI requires GLPI >= ' . PLUGIN_WARNINGSCCGLPI_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_warningsccglpi_check_config(): bool
{
    return true;
}
