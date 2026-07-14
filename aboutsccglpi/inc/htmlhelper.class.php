<?php
/**
 * Shared HTML helpers for the admin panel and documentation page.
 */
class PluginAboutsccglpiHtmlHelper
{
    /** Translated label in the 'aboutsccglpi' domain. */
    public static function t(string $s): string
    {
        return __($s, 'aboutsccglpi');
    }

    /** CSRF hidden input for POST forms. */
    public static function csrfHidden(): string
    {
        return Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    }

    /** Root-relative URL of the config panel. */
    public static function selfUrl(): string
    {
        return self::pluginDir() . 'front/config.form.php';
    }

    /** Root-relative URL of the public documentation page. */
    public static function viewUrl(): string
    {
        return self::pluginDir() . 'front/documentation.php';
    }

    /** Root-relative plugin directory, with trailing slash. */
    public static function pluginDir(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/aboutsccglpi/';
    }
}
