<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

(new PluginAboutsccglpiAdminPanel())->run();
