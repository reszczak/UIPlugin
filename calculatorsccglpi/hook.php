<?php

function plugin_calculatorsccglpi_install()
{
    $current = Config::getConfigurationValues(PluginCalculatorsccglpiConfig::CONTEXT);
    if (!isset($current['disallowed_filesystems'])) {
        Config::setConfigurationValues(PluginCalculatorsccglpiConfig::CONTEXT, [
            'disallowed_filesystems' => '[]',
        ]);
    }
    return true;
}

function plugin_calculatorsccglpi_uninstall()
{
    Config::deleteConfigurationValues(PluginCalculatorsccglpiConfig::CONTEXT, [
        'disallowed_filesystems',
        'allowed_filesystems',
    ]);
    return true;
}
