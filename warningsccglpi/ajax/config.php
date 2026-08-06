<?php

header('Content-Type: application/json; charset=utf-8');

if (!Session::getLoginUserID() || !Session::haveRight('config', UPDATE)) {
    echo json_encode(new stdClass());
    return;
}

$cfg = (new PluginWarningsccglpiSettings())->get();

echo json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
