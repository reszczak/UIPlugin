<?php

Session::checkRight('config', UPDATE);
header('Content-Type: application/json; charset=utf-8');

$adminProfiles = PluginUicustomProfileConfigRepository::adminProfileIds();
$configured = (new PluginUicustomProfileConfigRepository())->allMeta();
$out = [];
foreach ((new Profile())->find([], ['name']) as $row) {
    $id = (int) $row['id'];
    if (isset($adminProfiles[$id])) {
        continue;
    }
    $out[] = [
        'id'       => $id,
        'name'     => $row['name'],
        'has_rule' => isset($configured[$id]),
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
