<?php
/**
 * Public documentation page entry point.
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

(new PluginAboutsccglpiDocumentationPage())->run();
