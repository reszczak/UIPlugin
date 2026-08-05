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
            'hidden_sectors'      => [],
            'sector_keep'         => [],
            'hidden_help_sectors' => [],
            'hide_dashboards'     => false,
            'hide_find_menu'      => false,
            'hide_my_settings'    => false,
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

        $hiddenSectors = array_merge(
            $tweakConfig['hidden_sectors'] ?? [],
            $tweakConfig['hidden_help_sectors'] ?? []
        );
        foreach ($hiddenSectors as $sector) {
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
        $helpCat        = $catalog['help_sectors'] ?? [];
        $curHiddenHelp  = $tweakConfig['hidden_help_sectors'] ?? [];
        $curHideDash = !empty($tweakConfig['hide_dashboards']);
        $curHideFindMenu = !empty($tweakConfig['hide_find_menu']);
        $curHideMySettings = !empty($tweakConfig['hide_my_settings']);
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

        if (!empty($helpCat)) {
            $html .= "<h5 class='mt-4'>" . htmlescape($t('Self-service sidebar (FAQ, ticket submission...)')) . "</h5>";
            $html .= "<p class='text-muted small'>" . htmlescape($t('Shown on the helpdesk pages (e.g. front/helpdesk.faq.php); a different menu from the one above.')) . "</p>";
            $html .= "<table class='table align-middle'><thead><tr>"
                . "<th style='width:30%'>" . htmlescape($t('Sector')) . "</th>"
                . "<th>" . htmlescape($t('Hide whole')) . "</th>"
                . "</tr></thead><tbody>";
            foreach ($helpCat as $skey => $sdata) {
                $checked = in_array($skey, $curHiddenHelp, true) ? 'checked' : '';
                $html .= "<tr><td>" . htmlescape($sdata['title']) . "</td>";
                $html .= "<td><input type='checkbox' class='form-check-input' name='hidden_help_sectors[]' value='" . htmlescape($skey) . "' {$checked}></td></tr>";
            }
            $html .= "</tbody></table>";
        }

        $html .= "<div class='mb-2'>" . PluginUicustomHtmlHelper::checkbox('hide_dashboards', $curHideDash, $t('Hide all dashboard links')) . "</div>";
        $html .= "<div class='mb-2'>" . PluginUicustomHtmlHelper::checkbox('hide_find_menu', $curHideFindMenu, $t('Hide the "Find menu" button (fuzzy search, Ctrl+Alt+G)')) . "</div>";
        $html .= "<div class='mb-2'>" . PluginUicustomHtmlHelper::checkbox('hide_my_settings', $curHideMySettings, $t('Hide the "My settings" button (user menu)')) . "</div>";
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

        $helpCat = $catalog['help_sectors'] ?? [];
        $hiddenHelp = array_values(array_intersect(array_keys($helpCat), (array) ($post['hidden_help_sectors'] ?? [])));

        return [
            'hidden_sectors'      => $hidden,
            'sector_keep'         => $sectorKeep,
            'hidden_help_sectors' => $hiddenHelp,
            'hide_dashboards'     => isset($post['hide_dashboards']),
            'hide_find_menu'      => isset($post['hide_find_menu']),
            'hide_my_settings'    => isset($post['hide_my_settings']),
        ];
    }
}
