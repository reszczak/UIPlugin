<?php
/**
 * Admin-only: saves a rule fragment composed in live edit mode.
 * Merges into the existing profile rule.
 *
 * POST JSON:
 * {
 *   "profiles_id": 6,
 *   "itemtype": "computer",
 *   "tabs_keep": ["Computer", ...],
 *   "devices_hide": ["DeviceNetworkCard", ...],
 *   "columns_hide": { "Item_SoftwareVersion": [2,4] },
 *   "cleanup": { "hide_all_tab": true, "hide_qr": false, "hide_device_actions": true },
 *   "fields": { "computer.form.php": {"mode":"keep","list":[...]} }
 * }
 */

Session::checkRight('config', UPDATE);
header('Content-Type: application/json; charset=utf-8');

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    echo '{"error":"bad_json"}';
    return;
}

$pid = (int) ($in['profiles_id'] ?? 0);
$key = strtolower((string) ($in['itemtype'] ?? ''));
if ($pid <= 0 || !preg_match('/^[a-z0-9_]+$/', $key)) {
    http_response_code(400);
    echo '{"error":"bad_target"}';
    return;
}

$repo = new PluginUicustomProfileConfigRepository();
$cfg  = $repo->getRaw($pid) ?? PluginUicustomProfileConfigRepository::defaultConfig();
if (!isset($cfg['forms'][$key])) {
    $cfg['forms'][$key] = ['tabs_keep' => [], 'fields' => [], 'cleanup' => []];
}

if (array_key_exists('tabs_keep', $in) && is_array($in['tabs_keep'])) {
    $cfg['forms'][$key]['tabs_keep'] = PluginUicustomFormsTweak::sanitizeTabsList($in['tabs_keep']);
}

if (array_key_exists('devices_hide', $in) && is_array($in['devices_hide'])) {
    $cfg['forms'][$key]['devices_hide'] = PluginUicustomFormsTweak::sanitizeTabsList($in['devices_hide']);
}

if (array_key_exists('columns_hide', $in) && is_array($in['columns_hide'])) {
    $san = PluginUicustomFormsTweak::sanitizeColumnsMap($in['columns_hide']);
    foreach ($in['columns_hide'] as $tabCls => $_ignore) {
        if (!is_string($tabCls) || !preg_match('/^[A-Za-z0-9_\\\\]+$/', $tabCls)) {
            continue;
        }
        if (!empty($san[$tabCls])) {
            $cfg['forms'][$key]['columns_hide'][$tabCls] = $san[$tabCls];
        } else {
            unset($cfg['forms'][$key]['columns_hide'][$tabCls]);
        }
    }
}

if (isset($in['cleanup']) && is_array($in['cleanup'])) {
    foreach (['hide_qr', 'hide_all_tab', 'hide_device_actions'] as $flag) {
        if (array_key_exists($flag, $in['cleanup'])) {
            $cfg['forms'][$key]['cleanup'][$flag] = (bool) $in['cleanup'][$flag];
        }
    }
}

if (isset($in['fields']) && is_array($in['fields'])) {
    foreach ($in['fields'] as $action => $r) {
        if (!is_array($r)) {
            continue;
        }
        $rule = PluginUicustomFormsTweak::sanitizeFieldRule((string) $action, $r);
        if ($rule !== null) {
            $cfg['forms'][$key]['fields'][strtolower((string) $action)] = $rule;
        }
    }
}

$active = $repo->allMeta()[$pid]['is_active'] ?? true;
$repo->save($pid, $cfg, $active);

echo json_encode(['ok' => true, 'profiles_id' => $pid, 'itemtype' => $key]);
