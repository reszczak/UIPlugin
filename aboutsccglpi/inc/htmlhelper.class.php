<?php

class PluginAboutsccglpiHtmlHelper
{
    public static function t(string $s): string
    {
        return __($s, 'aboutsccglpi');
    }

    public static function csrfHidden(): string
    {
        return Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    }

    public static function selfUrl(): string
    {
        return self::pluginDir() . 'front/config.form.php';
    }

    public static function viewUrl(): string
    {
        return self::pluginDir() . 'front/documentation.php';
    }

    public static function pageUrl(int $id): string
    {
        if ($id === PluginAboutsccglpiDocumentation::HOME_ID) {
            return self::viewUrl();
        }
        return self::viewUrl() . '?id=' . $id;
    }

    public static function pageUrlAbsolute(int $id): string
    {
        global $CFG_GLPI;
        return rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/') . self::pageUrl($id);
    }

    public static function editUrl(int $id): string
    {
        if ($id === PluginAboutsccglpiDocumentation::HOME_ID) {
            return self::selfUrl();
        }
        return self::selfUrl() . '?tab=subpages&edit=' . $id;
    }

    public static function pluginDir(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/aboutsccglpi/';
    }
}
