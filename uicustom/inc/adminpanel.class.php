<?php
class PluginUicustomAdminPanel
{
    private PluginUicustomProfileConfigRepository $repo;

    public function __construct(?PluginUicustomProfileConfigRepository $repo = null)
    {
        $this->repo = $repo ?? new PluginUicustomProfileConfigRepository();
    }

    public function run(): void
    {
        $self    = PluginUicustomHtmlHelper::selfUrl();
        $catalog = PluginUicustomMenuCatalog::build();

        $this->handlePost($self, $catalog);

        $allProfiles = $this->allProfiles();

        Html::header('UI Customizer', $self, 'config', 'plugins');

        $this->renderProfilesList($self, $allProfiles);
        $this->renderBranding($self);

        $editPid = (int) ($_GET['profiles_id'] ?? 0);
        if ($editPid > 0 && isset($allProfiles[$editPid])) {
            $this->renderProfileLevel($editPid, $catalog, $allProfiles);
        }

        Html::footer();
    }


    private function handlePost(string $self, array $catalog): void
    {
        if (isset($_POST['delete'])) {
            $pid = (int) ($_POST['profiles_id'] ?? 0);
            if ($pid > 0) {
                $this->repo->delete($pid);
                Session::addMessageAfterRedirect(__('Item successfully deleted'));
            }
            Html::redirect($self);
        }

        if (isset($_POST['save_branding'])) {
            $settings = new PluginUicustomBrandingSettings();
            $settings->save($_POST);
            if (!empty($_FILES['logo']['name'])) {
                try {
                    $settings->saveLogo($_FILES['logo']);
                } catch (RuntimeException $e) {
                    Session::addMessageAfterRedirect(htmlescape($e->getMessage()), true, ERROR);
                    Html::redirect($self);
                }
            }
            Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            Html::redirect($self);
        }

        if (isset($_POST['reset_branding'])) {
            (new PluginUicustomBrandingSettings())->reset();
            Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            Html::redirect($self);
        }

        if (isset($_POST['remove_logo'])) {
            (new PluginUicustomBrandingSettings())->removeLogo();
            Session::addMessageAfterRedirect(__('Item successfully deleted'));
            Html::redirect($self);
        }

        if (isset($_POST['save_status'])) {
            $pid = (int) ($_POST['profiles_id'] ?? 0);
            if ($pid > 0) {
                $cfg = $this->repo->getRaw($pid) ?? PluginUicustomProfileConfigRepository::defaultConfig();
                $this->repo->save($pid, $cfg, isset($_POST['is_active']));
                Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            }
            Html::redirect($self . '?profiles_id=' . $pid);
        }

        if (isset($_POST['copy_rule'])) {
            $target = (int) ($_POST['profiles_id'] ?? 0);
            $source = (int) ($_POST['copy_from'] ?? 0);
            if ($target > 0 && $source > 0 && $source !== $target) {
                $src = $this->repo->getRaw($source);
                if ($src !== null) {
                    $this->repo->save($target, $src, true);
                    Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
                } else {
                    Session::addMessageAfterRedirect(__('No item found'), true, ERROR);
                }
            }
            Html::redirect($self . '?profiles_id=' . $target);
        }

        foreach (PluginUicustomTweakRegistry::all() as $tweak) {
            if ($tweak instanceof PluginUicustomPanelConfigurableTweakInterface) {
                $this->handleTweakProfileSave($tweak, $self, $catalog);
            }
        }
    }

    private function handleTweakProfileSave(PluginUicustomPanelConfigurableTweakInterface $tweak, string $self, array $catalog): void
    {
        if (!isset($_POST[$tweak->getProfileSaveButtonName()])) {
            return;
        }
        $pid = (int) ($_POST['profiles_id'] ?? 0);
        if ($pid > 0) {
            $wasNew = $this->repo->getRaw($pid) === null;
            $cfg    = $this->repo->getRaw($pid) ?? PluginUicustomProfileConfigRepository::defaultConfig();
            $key    = $tweak->getKey();
            $cfg[$key] = $tweak->handleProfileSave($_POST, $cfg[$key] ?? $tweak->getDefaultConfig(), $catalog);
            $this->repo->save($pid, $cfg, $wasNew ? true : $this->repo->isActive($pid));
            Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
        }
        Html::redirect($self . '?profiles_id=' . $pid);
    }


    private function renderProfilesList(string $self, array $allProfiles): void
    {
        $t = fn(string $s) => PluginUicustomHtmlHelper::t($s);
        $configured = $this->repo->allMeta();
        $editPid = (int) ($_GET['profiles_id'] ?? 0);
        $card = "style='max-width:1050px;margin:1rem auto'";

        echo "<div class='card' {$card}><div class='card-body'>";
        echo "<h2 class='mb-3'>UI Customizer</h2>";
        echo "<h4>" . htmlescape($t('Configured profiles')) . "</h4>";
        if (empty($configured)) {
            echo "<p class='text-muted'>" . htmlescape($t('None – pick a profile below.')) . "</p>";
        } else {
            echo "<table class='table table-hover'><thead><tr><th>" . __('Profile') . "</th><th>" . __('Status') . "</th><th>" . htmlescape($t('Rule')) . "</th><th></th></tr></thead><tbody>";
            foreach ($configured as $pid => $meta) {
                $name  = $allProfiles[$pid] ?? ('#' . $pid);
                $badge = $meta['is_active']
                    ? "<span class='badge bg-success'>" . htmlescape($t('active')) . "</span>"
                    : "<span class='badge bg-secondary'>" . htmlescape($t('disabled')) . "</span>";
                $summary = $this->describeRule($this->repo->getRaw($pid) ?? []);
                echo "<tr><td>" . htmlescape($name) . "</td><td>{$badge}</td><td class='text-muted small'>" . htmlescape($summary) . "</td><td class='text-end'><a class='btn btn-sm btn-outline-primary' href='" . htmlescape($self . '?profiles_id=' . $pid) . "'>" . _x('button', 'Edit') . "</a></td></tr>";
            }
            echo "</tbody></table>";
        }
        echo "<div class='mt-3'><div class='mb-1 text-muted'>" . htmlescape($t('Add / edit profile:')) . "</div>";
        foreach ($allProfiles as $pid => $name) {
            $clsBtn = isset($configured[$pid]) ? 'btn-primary' : 'btn-outline-secondary';
            if ($pid === $editPid) {
                $clsBtn .= ' active border-2 border-dark';
                $name = '✎ ' . $name;
            }
            echo "<a class='btn btn-sm {$clsBtn} me-1 mb-1' href='" . htmlescape($self . '?profiles_id=' . $pid) . "'>" . htmlescape($name) . "</a>";
        }
        echo "</div></div></div>";
    }

    private function renderBranding(string $self): void
    {
        $t = fn(string $s) => PluginUicustomHtmlHelper::t($s);
        $settings = new PluginUicustomBrandingSettings();
        $branding = $settings->get();
        $card = "style='max-width:1050px;margin:1rem auto'";

        echo "<form method='post' action='" . htmlescape($self) . "' enctype='multipart/form-data'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<div class='card' {$card}><div class='card-body'>";
        echo "<h4 class='mb-2'>" . htmlescape($t('Branding')) . "</h4>";

        echo "<h6 class='mt-3'>" . htmlescape($t('Company logo')) . "</h6>";
        echo "<p class='text-muted small'>" . htmlescape($t('Replaces the GLPI logo in the header for logged-in users. PNG/JPEG/GIF/WebP, max 300 KB.')) . "</p>";
        if ($settings->hasLogo()) {
            $logoUrl = PluginUicustomHtmlHelper::pluginDir() . 'ajax/logo.php?t=' . $settings->logoUpdatedAt();
            echo "<div class='mb-2 d-flex align-items-center gap-3'>";
            echo "<img src='" . htmlescape($logoUrl) . "' alt='' style='max-height:48px;max-width:220px;background:#fff;padding:4px;border-radius:4px'>";
            echo "<button type='submit' name='remove_logo' value='1' class='btn btn-sm btn-outline-danger'>" . htmlescape($t('Remove logo')) . "</button>";
            echo "</div>";
        }
        echo "<input type='file' class='form-control' name='logo' accept='image/png,image/jpeg,image/gif,image/webp'>";

        echo "<h6 class='mt-4'>" . htmlescape($t('Colors')) . "</h6>";
        echo "<p class='text-muted small'>" . htmlescape($t('Instance-wide, applies immediately to every logged-in user. Hover/focus/active shades are computed automatically from these.')) . "</p>";
        echo "<div class='d-flex flex-wrap gap-4'>";
        foreach (PluginUicustomBrandingSettings::labels() as $key => $label) {
            echo "<div><label class='form-label d-block'>" . htmlescape($t($label)) . "</label>";
            echo "<div class='d-flex align-items-center gap-2'>";
            echo "<input type='color' class='form-control form-control-color' name='" . htmlescape($key) . "' value='" . htmlescape($branding[$key]) . "'>";
            echo "<code class='small text-muted'>" . htmlescape($branding[$key]) . "</code>";
            echo "</div></div>";
        }
        echo "</div>";

        echo "<div class='mt-3 d-flex justify-content-between'>";
        echo "<button type='submit' name='reset_branding' value='1' class='btn btn-outline-secondary'>" . htmlescape($t('Reset to native GLPI colors')) . "</button>";
        echo "<button type='submit' name='save_branding' value='1' class='btn btn-primary'>" . htmlescape($t('Save branding')) . "</button>";
        echo "</div>";
        echo "</div></div></form>";
    }

    private function renderProfileLevel(int $pid, array $catalog, array $allProfiles): void
    {
        $t = fn(string $s) => PluginUicustomHtmlHelper::t($s);
        $cfg      = $this->repo->getRaw($pid);
        $isNew    = ($cfg === null);
        $cfg      = $cfg ?? PluginUicustomProfileConfigRepository::defaultConfig();
        $isActive = $isNew ? true : $this->repo->isActive($pid);
        $self     = PluginUicustomHtmlHelper::selfUrl();
        $card     = "style='max-width:1050px;margin:1rem auto'";

        echo "<form method='post' action='" . htmlescape($self) . "'>";
        echo PluginUicustomHtmlHelper::csrfAndProfileHidden($pid);
        echo "<div class='card' {$card}><div class='card-body'>";
        echo "<h3 class='mb-3'>" . htmlescape(sprintf($t('Profile rule: %s'), $allProfiles[$pid])) . ($isNew ? " <span class='badge bg-info'>" . htmlescape($t('new')) . "</span>" : "") . "</h3>";
        echo "<div class='mb-3'>" . PluginUicustomHtmlHelper::checkbox('is_active', $isActive, $t('Rule active (disabling = full view for this profile)')) . "</div>";
        echo "<div class='d-flex justify-content-between'>";
        echo "<button type='submit' name='delete' value='1' class='btn btn-outline-danger' " . ($isNew ? "disabled" : "") . ">" . _x('button', 'Delete permanently') . "</button>";
        echo "<button type='submit' name='save_status' value='1' class='btn btn-outline-primary'>" . htmlescape($t('Save status')) . "</button>";
        echo "</div>";
        echo "</div></div></form>";

        echo $this->renderCopyRule($pid, $allProfiles);

        foreach (PluginUicustomTweakRegistry::all() as $tweak) {
            if ($tweak instanceof PluginUicustomPanelConfigurableTweakInterface) {
                $tweakConfig = $cfg[$tweak->getKey()] ?? $tweak->getDefaultConfig();
                echo $tweak->renderProfileSection($tweakConfig, $pid, $catalog);
            }
        }

        echo $this->renderLiveEditNote();
    }

    private function renderCopyRule(int $pid, array $allProfiles): string
    {
        $t = fn(string $s) => PluginUicustomHtmlHelper::t($s);
        $sources = [];
        foreach ($this->repo->allMeta() as $spid => $meta) {
            if ($spid !== $pid && isset($allProfiles[$spid])) {
                $sources[$spid] = $allProfiles[$spid];
            }
        }
        if (empty($sources)) {
            return '';
        }
        $self = PluginUicustomHtmlHelper::selfUrl();
        $html  = "<form method='post' action='" . htmlescape($self) . "'>";
        $html .= PluginUicustomHtmlHelper::csrfAndProfileHidden($pid);
        $html .= "<div class='card' style='max-width:1050px;margin:1rem auto'><div class='card-body'>";
        $html .= "<h5>" . htmlescape($t('Copy configuration from another profile')) . "</h5>";
        $html .= "<p class='text-muted small'>" . htmlescape($t('Overwrites this whole rule (menu + tabs/fields/columns) with the chosen profile\'s rule.')) . "</p>";
        $html .= "<div class='d-flex align-items-center gap-2'>";
        ob_start();
        Dropdown::showFromArray('copy_from', $sources, ['display_emptychoice' => true, 'width' => '260px']);
        $html .= ob_get_clean();
        $html .= "<button type='submit' name='copy_rule' value='1' class='btn btn-outline-primary'>" . htmlescape($t('Copy')) . "</button>";
        $html .= "</div></div></div></form>";
        return $html;
    }

    private function renderLiveEditNote(): string
    {
        $t = fn(string $s) => PluginUicustomHtmlHelper::t($s);
        $html  = "<div class='card' style='max-width:1050px;margin:1rem auto'><div class='card-body'>";
        $html .= "<h5>" . htmlescape($t('Tabs, fields, component groups and columns')) . "</h5>";
        $html .= "<p class='text-muted mb-0'>" . htmlescape($t('Configured live only: open any object as this profile and click the "UI" button in the corner.')) . "</p>";
        $html .= "</div></div>";
        return $html;
    }

    private function describeRule(array $cfg): string
    {
        $t = fn(string $s) => PluginUicustomHtmlHelper::t($s);
        $parts = [];
        $menu = $cfg['menu'] ?? [];
        $nHidden = count($menu['hidden_sectors'] ?? []) + count($menu['hidden_help_sectors'] ?? []);
        $nKeep   = count($menu['sector_keep'] ?? []);
        if ($nHidden > 0 || $nKeep > 0 || !empty($menu['hide_dashboards'])) {
            $parts[] = sprintf($t('menu: %d hidden, %d filtered'), $nHidden, $nKeep);
        }
        $nForms = count($cfg['forms'] ?? []);
        if ($nForms > 0) {
            $parts[] = sprintf($t('elements: %d types'), $nForms);
        }
        return empty($parts) ? $t('empty (full view)') : implode(' · ', $parts);
    }

    private function allProfiles(): array
    {
        $adminIds = PluginUicustomProfileConfigRepository::adminProfileIds();
        $out = [];
        foreach ((new Profile())->find([], ['name']) as $row) {
            $id = (int) $row['id'];
            if (isset($adminIds[$id])) {
                continue;
            }
            $out[$id] = $row['name'];
        }
        return $out;
    }
}
