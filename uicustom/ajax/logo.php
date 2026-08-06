<?php

$logo = (new PluginUicustomBrandingSettings())->getLogo();
if ($logo === null) {
    http_response_code(404);
    return;
}

header('Content-Type: ' . $logo['mime']);
header('Cache-Control: public, max-age=604800, immutable');
echo $logo['data'];
