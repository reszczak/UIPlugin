<?php
/**
 * Returns the "forms" rule of the active profile as JSON.
 * Consumed by public/js/profile-ui.js.
 */

header('Content-Type: application/json; charset=utf-8');

if (!Session::getLoginUserID()) {
    http_response_code(403);
    echo '{}';
    return;
}

$repo = new PluginUicustomProfileConfigRepository();

// Admin (config/UPDATE) can fetch a given profile's rule (used by live edit mode).
$pid = (int) ($_GET['profiles_id'] ?? 0);
if ($pid > 0) {
    if (!Session::haveRight('config', UPDATE)) {
        http_response_code(403);
        echo '{}';
        return;
    }
    $cfg = $repo->getRaw($pid);
} else {
    $context = new PluginUicustomContext($repo);
    $cfg = $context->activeConfig();
}
$forms = is_array($cfg) ? ($cfg['forms'] ?? []) : [];

echo json_encode($forms ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
