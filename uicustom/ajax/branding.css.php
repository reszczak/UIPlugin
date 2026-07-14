<?php
/**
 * Generates :root { --glpi-mainmenu-*; --glpi-logo-* } from branding settings.
 * Registered by AssetRegistry under add_css, for logged-in users only.
 */

global $CFG_GLPI;

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$settings = new PluginUicustomBrandingSettings();
$colors   = $settings->get();

echo ":root {\n";
foreach ($colors as $key => $value) {
    $cssVar = PluginUicustomBrandingSettings::CSS_VARS[$key] ?? null;
    if ($cssVar !== null) {
        echo "  {$cssVar}: {$value};\n";
    }
}

echo "  --tblr-primary-rgb: " . PluginUicustomBrandingSettings::hexToRgbTriple($colors['brand_primary']) . ";\n";
echo "  --tblr-primary-fg: " . PluginUicustomBrandingSettings::contrastingForeground($colors['brand_primary']) . ";\n";

$linkHover = PluginUicustomBrandingSettings::darken($colors['brand_link'], 0.2);
echo "  --tblr-link-color-rgb: " . PluginUicustomBrandingSettings::hexToRgbTriple($colors['brand_link']) . ";\n";
echo "  --tblr-link-hover-color: {$linkHover};\n";
echo "  --tblr-link-hover-color-rgb: " . PluginUicustomBrandingSettings::hexToRgbTriple($linkHover) . ";\n";

if ($settings->hasLogo()) {
    $logoUrl = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/uicustom/ajax/logo.php?t=' . $settings->logoUpdatedAt();
    foreach (PluginUicustomBrandingSettings::logoCssVars() as $cssVar) {
        echo "  {$cssVar}: url('{$logoUrl}');\n";
    }
}

echo "}\n";

if ($settings->hideFindMenu()) {
    echo ".trigger-fuzzy { display: none !important; }\n";
}

if ($settings->hasLogo()) {
    echo ".glpi-logo { background-size: contain !important; background-position: center !important; }\n";
}
