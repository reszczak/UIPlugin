<?php

Session::checkRight('config', UPDATE);

(new PluginUploadsccglpiAdminPanel())->run();
