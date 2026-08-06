<?php

function plugin_warningsccglpi_install()
{
    return true;
}

function plugin_warningsccglpi_uninstall()
{
    Config::deleteConfigurationValues('plugin:warningsccglpi', ['label', 'color']);
    return true;
}
