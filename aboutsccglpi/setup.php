<?php

define('PLUGIN_ABOUTSCCGLPI_VERSION', '1.0.0');
define('PLUGIN_ABOUTSCCGLPI_MIN_GLPI', '11.0.0');
define('PLUGIN_ABOUTSCCGLPI_MAX_GLPI', '11.99.99');

function plugin_init_aboutsccglpi(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['aboutsccglpi'] = true;

    $lang    = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? '');
    $phpfile = __DIR__ . '/locales/' . $lang . '.php';
    if ($lang !== '' && is_file($phpfile) && isset($GLOBALS['TRANSLATE'])) {
        $GLOBALS['TRANSLATE']->addTranslationFile('phparray', $phpfile, 'aboutsccglpi', $lang);
    }

    $PLUGIN_HOOKS['config_page']['aboutsccglpi'] = 'front/config.form.php';
    $PLUGIN_HOOKS['add_javascript']['aboutsccglpi'] = 'js/aboutsccglpi.js';

    if (Session::getLoginUserID()) {
        $PLUGIN_HOOKS['menu_toadd']['aboutsccglpi'] = [
            'aboutsccglpi' => [PluginAboutsccglpiMenu::class],
        ];
    }
}

function plugin_version_aboutsccglpi(): array
{
    return [
        'name'         => 'aboutSCCGLPI',
        'version'      => PLUGIN_ABOUTSCCGLPI_VERSION,
        'author'       => 'reszcdaw',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_ABOUTSCCGLPI_MIN_GLPI, 'max' => PLUGIN_ABOUTSCCGLPI_MAX_GLPI],
        ],
    ];
}

function plugin_aboutsccglpi_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_ABOUTSCCGLPI_MIN_GLPI, 'lt')) {
        echo "aboutSCCGLPI requires GLPI >= " . PLUGIN_ABOUTSCCGLPI_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_aboutsccglpi_check_config(): bool
{
    return true;
}
