<?php

class PluginAboutsccglpiMenu extends CommonGLPI
{
    public static function getMenuName()
    {
        return __('Information', 'aboutsccglpi');
    }

    public static function getIcon()
    {
        return 'ti ti-book-2';
    }

    public static function getMenuContent()
    {
        return [
            'is_multi_entries' => true,
            'title' => self::getMenuName(),
            'documentation' => [
                'title' => __('About the Application', 'aboutsccglpi'),
                'page'  => '/plugins/aboutsccglpi/front/documentation.php',
                'icon'  => self::getIcon(),
            ],
            'help' => [
                'title' => __('Help', 'aboutsccglpi'),
                'page'  => '/front/helpdesk.faq.php',
                'icon'  => 'ti ti-help',
            ],
        ];
    }
}
