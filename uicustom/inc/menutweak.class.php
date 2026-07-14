<?php
/**
 * Tweak "menu": hides menu sectors/items and dashboard links per profile.
 * Server-side only (redefine_menus hook), no JS/CSS assets.
 */
class PluginUicustomMenuTweak implements PluginUicustomPanelConfigurableTweakInterface
{
    public function getKey(): string
    {
        return 'menu';
    }

    public function getDefaultConfig(): array
    {
        return [
            'hidden_sectors'  => [],
            'sector_keep'     => [],
            'hide_dashboards' => false,
        ];
    }

    public function getAssets(PluginUicustomContext $context): array
    {
        return ['css' => [], 'js' => []];
    }

    public function filterMenu(array $menu, array $tweakConfig): array
    {
        $sectorKeep = $tweakConfig['sector_keep'] ?? [];
        foreach ($sectorKeep as $sector => $keep) {
            if (isset($menu[$sector]['content']) && is_array($menu[$sector]['content'])) {
                foreach (array_keys($menu[$sector]['content']) as $key) {
                    if (!in_array($key, $keep, true)) {
                        unset($menu[$sector]['content'][$key]);
                    }
                }
            }
        }

        if (!empty($tweakConfig['hide_dashboards'])) {
            foreach (array_keys($menu) as $s) {
                unset($menu[$s]['default_dashboard']);
            }
        }

        foreach ($tweakConfig['hidden_sectors'] ?? [] as $sector) {
            unset($menu[$sector]);
        }

        return $menu;
    }

    public function getProfileSaveButtonName(): string
    {
        return 'save_menu';
    }

    public function renderProfileSection(array $tweakConfig, int $profilesId, array $catalog): string
    {
        $t = [PluginUicustomHtmlHelper::class, 't'];
        $sectorsCat = $catalog['sectors'] ?? [];
        $curHidden  = $tweakConfig['hidden_sectors'] ?? [];
        $curKeep    = $tweakConfig['sector_keep'] ?? [];
        $curHideDash = !empty($tweakConfig['hide_dashboards']);
        $self = PluginUicustomHtmlHelper::selfUrl();

        $html  = "<form method='post' action='" . htmlescape($self) . "'>";
        $html .= PluginUicustomHtmlHelper::csrfAndProfileHidden($profilesId);
        $html .= "<div class='card' style='max-width:1050px;margin:1rem auto'><div class='card-body'>";
        $html .= "<h5>" . htmlescape($t('Menu')) . "</h5>";
        $html .= "<table class='table align-middle'><thead><tr>"
            . "<th style='width:22%'>" . htmlescape($t('Sector')) . "</th>"
            . "<th style='width:14%'>" . htmlescape($t('Hide whole')) . "</th>"
            . "<th>" . htmlescape($t('Keep only these items (empty = all)')) . "</th>"
            . "</tr></thead><tbody>";
        foreach ($sectorsCat as $skey => $sdata) {
            $hiddenChecked = in_array($skey, $curHidden, true) ? 'checked' : '';
            $html .= "<tr><td>" . htmlescape($sdata['title']) . "</td>";
            $html .= "<td><input type='checkbox' class='form-check-input' name='hidden_sectors[]' value='" . htmlescape($skey) . "' {$hiddenChecked}></td>";
            $html .= "<td>";
            if (!empty($sdata['items'])) {
                ob_start();
                Dropdown::showFromArray("sk_{$skey}", $sdata['items'], [
                    'values' => $curKeep[$skey] ?? [], 'multiple' => true, 'width' => '100%',
                ]);
                $html .= ob_get_clean();
            } else {
                $html .= "<span class='text-muted small'>—</span>";
            }
            $html .= "</td></tr>";
        }
        $html .= "</tbody></table>";
        $html .= "<div class='mb-2'>" . PluginUicustomHtmlHelper::checkbox('hide_dashboards', $curHideDash, $t('Hide all dashboard links')) . "</div>";
        $html .= "<div class='d-flex justify-content-end'>";
        $html .= "<button type='submit' name='" . $this->getProfileSaveButtonName() . "' value='1' class='btn btn-primary'>" . htmlescape($t('Save menu')) . "</button>";
        $html .= "</div>";
        $html .= "</div></div></form>";

        return $html;
    }

    public function handleProfileSave(array $post, array $tweakConfig, array $catalog): array
    {
        $sectorsCat = $catalog['sectors'] ?? [];
        $hidden = array_values(array_intersect(array_keys($sectorsCat), (array) ($post['hidden_sectors'] ?? [])));

        $sectorKeep = [];
        foreach ($sectorsCat as $skey => $sdata) {
            $sel = array_values(array_intersect(array_keys($sdata['items']), (array) ($post["sk_{$skey}"] ?? [])));
            if (!empty($sel)) {
                $sectorKeep[$skey] = $sel;
            }
        }

        return [
            'hidden_sectors'  => $hidden,
            'sector_keep'     => $sectorKeep,
            'hide_dashboards' => isset($post['hide_dashboards']),
        ];
    }
}
