<?php
/**
 * Global branding settings (colors, logo, interface toggles), stored in
 * Config under context 'plugin:uicustom'. Rendered as CSS by
 * ajax/branding.css.php.
 */
class PluginUicustomBrandingSettings
{
    private const CONTEXT = 'plugin:uicustom';

    /** Config key => GLPI CSS variable. */
    public const CSS_VARS = [
        'brand_primary'    => '--tblr-primary',
        'brand_link'       => '--tblr-link-color',
        'brand_sidebar_bg' => '--glpi-mainmenu-bg',
        'brand_sidebar_fg' => '--glpi-mainmenu-fg',
    ];

    /** GLPI CSS variables overridden by the company logo. */
    private const LOGO_CSS_VARS = [
        '--glpi-logo-light',
        '--glpi-logo-dark',
        '--glpi-logo-light-reduced',
        '--glpi-logo-dark-reduced',
        '--glpi-logo-light-login',
        '--glpi-logo-dark-login',
    ];

    private const LOGO_MAX_BYTES = 300 * 1024;
    private const LOGO_ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /** GLPI default colors. */
    public static function defaults(): array
    {
        return [
            'brand_primary'    => '#fec95c',
            'brand_link'       => '#3a5693',
            'brand_sidebar_bg' => '#2f3f64',
            'brand_sidebar_fg' => '#f4f6fa',
        ];
    }

    /** Color field labels for the admin form. */
    public static function labels(): array
    {
        return [
            'brand_primary'    => 'Primary / accent color (buttons, "+", highlights)',
            'brand_link'       => 'Link color (item names in lists/forms)',
            'brand_sidebar_bg' => 'Sidebar / top bar background color',
            'brand_sidebar_fg' => 'Sidebar / top bar text color',
        ];
    }

    public static function logoCssVars(): array
    {
        return self::LOGO_CSS_VARS;
    }

    /** "#rrggbb" -> "r, g, b". */
    public static function hexToRgbTriple(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return "{$r}, {$g}, {$b}";
    }

    /** Best-contrast foreground color (white or dark) for a background hex. */
    public static function contrastingForeground(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.6 ? '#1e293b' : '#ffffff';
    }

    /** Darkens a hex color by $percent, for link hover state. */
    public static function darken(string $hex, float $percent): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $factor = 1 - $percent;
        $r = (int) round($r * $factor);
        $g = (int) round($g * $factor);
        $b = (int) round($b * $factor);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /* ------------------------------ colors ------------------------------ */

    /** Current color values, defaults applied where missing/invalid. */
    public function get(): array
    {
        $stored = Config::getConfigurationValues(self::CONTEXT, array_keys(self::CSS_VARS));
        $out = self::defaults();
        foreach (self::CSS_VARS as $key => $cssVar) {
            $value = (string) ($stored[$key] ?? '');
            if ($value !== '' && self::isValidHexColor($value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    public function save(array $post): void
    {
        $clean = [];
        foreach (self::CSS_VARS as $key => $cssVar) {
            $value = (string) ($post[$key] ?? '');
            if (self::isValidHexColor($value)) {
                $clean[$key] = $value;
            }
        }
        if (!empty($clean)) {
            Config::setConfigurationValues(self::CONTEXT, $clean);
        }
    }

    public function reset(): void
    {
        Config::deleteConfigurationValues(self::CONTEXT, array_keys(self::CSS_VARS));
    }

    public static function isValidHexColor(string $v): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $v);
    }

    /* ------------------------------- logo -------------------------------- */

    public function hasLogo(): bool
    {
        $stored = Config::getConfigurationValues(self::CONTEXT, ['brand_logo_data']);
        return !empty($stored['brand_logo_data']);
    }

    /** @return array{mime:string,data:string}|null Decoded logo bytes. */
    public function getLogo(): ?array
    {
        $stored = Config::getConfigurationValues(self::CONTEXT, ['brand_logo_data', 'brand_logo_mime']);
        if (empty($stored['brand_logo_data']) || empty($stored['brand_logo_mime'])) {
            return null;
        }
        $data = base64_decode((string) $stored['brand_logo_data'], true);
        if ($data === false) {
            return null;
        }
        return ['mime' => (string) $stored['brand_logo_mime'], 'data' => $data];
    }

    /** Timestamp of the last logo change, for cache-busting. */
    public function logoUpdatedAt(): int
    {
        $stored = Config::getConfigurationValues(self::CONTEXT, ['brand_logo_updated']);
        return (int) ($stored['brand_logo_updated'] ?? 0);
    }

    /**
     * Validates and stores an uploaded logo file.
     *
     * @throws RuntimeException on validation failure.
     */
    public function saveLogo(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(__('File upload failed.', 'uicustom'));
        }
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException(__('File upload failed.', 'uicustom'));
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::LOGO_MAX_BYTES) {
            throw new RuntimeException(sprintf(
                __('Logo file is too large (max %d KB).', 'uicustom'),
                (int) (self::LOGO_MAX_BYTES / 1024)
            ));
        }
        $mime = Toolbox::getMime($tmpPath);
        if (!in_array($mime, self::LOGO_ALLOWED_MIMES, true)) {
            throw new RuntimeException(__('Unsupported image format. Use PNG, JPEG, GIF or WebP.', 'uicustom'));
        }

        $data = file_get_contents($tmpPath);
        if ($data === false) {
            throw new RuntimeException(__('File upload failed.', 'uicustom'));
        }

        Config::setConfigurationValues(self::CONTEXT, [
            'brand_logo_data'    => base64_encode($data),
            'brand_logo_mime'    => $mime,
            'brand_logo_updated' => (string) time(),
        ]);
    }

    public function removeLogo(): void
    {
        Config::deleteConfigurationValues(self::CONTEXT, ['brand_logo_data', 'brand_logo_mime', 'brand_logo_updated']);
    }
}
