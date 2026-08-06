<?php

class PluginAboutsccglpiMenu extends CommonGLPI
{
    public static function getMenuName()
    {
        return __('About the Application', 'aboutsccglpi');
    }

    public static function getIcon()
    {
        return 'ti ti-book-2';
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/aboutsccglpi/front/documentation.php',
            'icon'  => self::getIcon(),
        ];
    }
}
