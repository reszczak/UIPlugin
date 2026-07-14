<?php
/**
 * UI Customizer – GLPI 11.x plugin to simplify the UI per profile.
 * Bootstraps GLPI hooks; business logic lives in inc/ classes.
 */

define('PLUGIN_UICUSTOM_VERSION', '1.0.0');
define('PLUGIN_UICUSTOM_MIN_GLPI', '11.0.0');
define('PLUGIN_UICUSTOM_MAX_GLPI', '11.99.99');
define('PLUGIN_UICUSTOM_TABLE', 'glpi_plugin_uicustom_profileconfigs');

function plugin_init_uicustom(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['uicustom'] = true;

    // Translations for the active language, phparray format.
    $lang = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? '');
    $phpfile = __DIR__ . '/locales/' . $lang . '.php';
    if ($lang !== '' && is_file($phpfile) && isset($GLOBALS['TRANSLATE'])) {
        $GLOBALS['TRANSLATE']->addTranslationFile('phparray', $phpfile, 'uicustom', $lang);
    }

    // Config panel (guarded by config/UPDATE).
    $PLUGIN_HOOKS['config_page']['uicustom'] = 'front/config.form.php';

    // Server-side menu filtering per active profile's rule.
    $PLUGIN_HOOKS['redefine_menus']['uicustom'] = 'plugin_uicustom_redefine_menus';

    // CSS/JS assets, declared by each tweak for the current context.
    $context  = new PluginUicustomContext(new PluginUicustomProfileConfigRepository());
    $registry = (new PluginUicustomAssetRegistry())->collect($context);
    $registry->applyToHooks($PLUGIN_HOOKS);

    // Do not register add_css_anonymous_page -> ajax/branding.css.php: it
    // breaks the login form's CSRF token.
}

function plugin_version_uicustom(): array
{
    return [
        'name'         => 'UI Customizer',
        'version'      => PLUGIN_UICUSTOM_VERSION,
        'author'       => 'reszcdaw',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_UICUSTOM_MIN_GLPI, 'max' => PLUGIN_UICUSTOM_MAX_GLPI],
        ],
    ];
}

function plugin_uicustom_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_UICUSTOM_MIN_GLPI, 'lt')) {
        echo "UI Customizer requires GLPI >= " . PLUGIN_UICUSTOM_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_uicustom_check_config(): bool
{
    return true;
}

/**
 * REDEFINE_MENUS hook entry point. Delegates to tweaks that filter the menu.
 */
function plugin_uicustom_redefine_menus(array $menu): array
{
    $context = new PluginUicustomContext(new PluginUicustomProfileConfigRepository());
    $cfg = $context->activeConfig();
    if ($cfg === null) {
        return $menu;
    }
    foreach (PluginUicustomTweakRegistry::all() as $tweak) {
        $tweakConfig = $cfg[$tweak->getKey()] ?? $tweak->getDefaultConfig();
        $menu = $tweak->filterMenu($menu, $tweakConfig);
    }
    return $menu;
}
