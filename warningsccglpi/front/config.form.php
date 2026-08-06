<?php

Session::checkRight('config', UPDATE);

(new PluginWarningsccglpiAdminPanel())->run();
