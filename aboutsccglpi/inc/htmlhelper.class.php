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

    /** Root-relative URL of the public documentation page (home). */
    public static function viewUrl(): string
    {
        return self::pluginDir() . 'front/documentation.php';
    }

    /** Root-relative URL of a documentation page (home or subpage). */
    public static function pageUrl(int $id): string
    {
        if ($id === PluginAboutsccglpiDocumentation::HOME_ID) {
            return self::viewUrl();
        }
        return self::viewUrl() . '?id=' . $id;
    }

    /** Absolute URL of a documentation page (home or subpage), for copy/share links. */
    public static function pageUrlAbsolute(int $id): string
    {
        global $CFG_GLPI;
        return rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/') . self::pageUrl($id);
    }

    /** Root-relative URL of the config panel for a given page (home or subpage). */
    public static function editUrl(int $id): string
    {
        if ($id === PluginAboutsccglpiDocumentation::HOME_ID) {
            return self::selfUrl();
        }
        return self::selfUrl() . '?tab=subpages&edit=' . $id;
    }

    /** Root-relative plugin directory, with trailing slash. */
    public static function pluginDir(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/aboutsccglpi/';
    }
}
