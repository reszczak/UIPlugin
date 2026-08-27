<?php

define('PLUGIN_CALCULATORSCCGLPI_VERSION', '1.0.0');
define('PLUGIN_CALCULATORSCCGLPI_MIN_GLPI', '11.0.0');
define('PLUGIN_CALCULATORSCCGLPI_MAX_GLPI', '11.99.99');

function plugin_init_calculatorsccglpi(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['calculatorsccglpi'] = true;

    $lang = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? '');
    $phpfile = __DIR__ . '/locales/' . $lang . '.php';
    if ($lang !== '' && is_file($phpfile) && isset($GLOBALS['TRANSLATE'])) {
        $GLOBALS['TRANSLATE']->addTranslationFile('phparray', $phpfile, 'calculatorsccglpi', $lang);
    }

    $PLUGIN_HOOKS['config_page']['calculatorsccglpi'] = 'front/config.form.php';

    $PLUGIN_HOOKS['pre_show_tab']['calculatorsccglpi'] = 'plugin_calculatorsccglpi_pre_show_tab';
}

function plugin_version_calculatorsccglpi(): array
{
    return [
        'name'         => 'calculatorSCCGLPI',
        'version'      => PLUGIN_CALCULATORSCCGLPI_VERSION,
        'author'       => 'reszcdaw',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_CALCULATORSCCGLPI_MIN_GLPI, 'max' => PLUGIN_CALCULATORSCCGLPI_MAX_GLPI],
        ],
    ];
}

function plugin_calculatorsccglpi_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_CALCULATORSCCGLPI_MIN_GLPI, 'lt')) {
        echo "calculatorSCCGLPI requires GLPI >= " . PLUGIN_CALCULATORSCCGLPI_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_calculatorsccglpi_check_config(): bool
{
    return true;
}

function plugin_calculatorsccglpi_pre_show_tab(array $params): void
{
    $item    = $params['item'] ?? null;
    $options = $params['options'] ?? [];

    if (!($item instanceof CommonDBTM) || (int) ($options['withtemplate'] ?? 0) !== 0 || $item->getID() <= 0) {
        return;
    }

    switch ($options['itemtype'] ?? '') {
        case Item_Disk::class:
            (new PluginCalculatorsccglpiSummary())->render($item);
            break;
        case Item_Devices::class:
            (new PluginCalculatorsccglpiComponentsSummary())->render($item);
            break;
    }
}
