<?php
/**
 * Config panel entry point. Admin-only.
 */

Session::checkRight('config', UPDATE);

(new PluginUicustomAdminPanel())->run();
