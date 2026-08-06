<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

(new PluginAboutsccglpiDocumentationPage())->run();
