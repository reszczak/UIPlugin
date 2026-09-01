<?php
class PluginConfigfilessccglpiComputertab extends CommonDBTM
{
    public static function canView(): bool
    {
        return Computer::canView() && Document::canView();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof Computer) || !self::canView()) {
            return '';
        }

        $count = count((new PluginConfigfilessccglpiDocumentLocator())->findForComputer($item));

        return self::createTabEntry(
            __('Configuration Files', 'configfilessccglpi'),
            $count,
            $item->getType(),
            'ti ti-file-code'
        );
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof Computer) || !self::canView()) {
            return false;
        }

        (new PluginConfigfilessccglpiRenderer())->render($item);

        return true;
    }
}
