<?php

Session::checkRight('config', UPDATE);

(new PluginCalculatorsccglpiAdminPanel())->run();
