<?php
class PluginUicustomMenuCatalog
{
    public static function build(): array
    {
        $menu    = Html::generateMenuSession(true);
        $sectors = [];

        foreach ($menu as $skey => $sdata) {
            if (!is_array($sdata) || !isset($sdata['title'])) {
                continue;
            }
            if (!array_key_exists('types', $sdata) || in_array($skey, ['preference'], true)) {
                continue;
            }
            $items = [];
            foreach (($sdata['content'] ?? []) as $ikey => $idata) {
                if (is_array($idata) && isset($idata['title'])) {
                    $items[$ikey] = trim(strip_tags((string) $idata['title']));
                }
            }

            $sectors[$skey] = [
                'title' => trim(strip_tags((string) $sdata['title'])),
                'items' => $items,
            ];
        }

        $helpSectors = [];
        foreach (Html::generateHelpMenu() as $skey => $sdata) {
            if (!is_array($sdata) || !isset($sdata['title'])) {
                continue;
            }
            $helpSectors[$skey] = [
                'title' => trim(strip_tags((string) $sdata['title'])),
            ];
        }

        return ['sectors' => $sectors, 'help_sectors' => $helpSectors];
    }
}
