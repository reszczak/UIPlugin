<?php
class PluginConfigfilessccglpiLogbookTab extends CommonDBTM
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

        $count = count((new PluginConfigfilessccglpiDocumentLocator())->findLogbookForComputer($item));
        if ($count === 0) {
            return '';
        }

        return self::createTabEntry(
            __('Logbook', 'configfilessccglpi'),
            $count,
            $item->getType(),
            'ti ti-notebook'
        );
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof Computer) || !self::canView()) {
            return false;
        }

        (new PluginConfigfilessccglpiRenderer())->renderLogbook($item);

        return true;
    }
}
