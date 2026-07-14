<?php
/**
 * Admin-only: profile list for the live edit mode toolbar.
 * Returns [{id, name, has_rule}].
 */

Session::checkRight('config', UPDATE);
header('Content-Type: application/json; charset=utf-8');

$configured = (new PluginUicustomProfileConfigRepository())->allMeta();
$out = [];
foreach ((new Profile())->find([], ['name']) as $row) {
    $out[] = [
        'id'       => (int) $row['id'],
        'name'     => $row['name'],
        'has_rule' => isset($configured[(int) $row['id']]),
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
