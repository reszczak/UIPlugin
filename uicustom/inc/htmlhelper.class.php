<?php
/**
 * Shared HTML helpers for the admin panel.
 */
class PluginUicustomHtmlHelper
{
    public static function checkbox(string $name, bool $checked, string $label): string
    {
        $c = $checked ? 'checked' : '';
        return "<label class='form-check'><input type='checkbox' class='form-check-input' name='{$name}' value='1' {$c}> "
            . "<span class='form-check-label'>" . htmlescape($label) . "</span></label>";
    }

    public static function csrfAndProfileHidden(int $profilesId): string
    {
        return Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()])
            . Html::hidden('profiles_id', ['value' => $profilesId]);
    }

    /** Translated label in the 'uicustom' domain. */
    public static function t(string $s): string
    {
        return __($s, 'uicustom');
    }

    /** Root-relative URL of the config panel. */
    public static function selfUrl(): string
    {
        return self::pluginDir() . 'front/config.form.php';
    }

    /** Root-relative plugin directory, with trailing slash. */
    public static function pluginDir(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/uicustom/';
    }
}
