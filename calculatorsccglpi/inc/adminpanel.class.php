<?php
class PluginCalculatorsccglpiAdminPanel
{
    private PluginCalculatorsccglpiConfig $config;

    public function __construct(?PluginCalculatorsccglpiConfig $config = null)
    {
        $this->config = $config ?? new PluginCalculatorsccglpiConfig();
    }

    private static function t(string $s): string
    {
        return __($s, 'calculatorsccglpi');
    }

    private static function selfUrl(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/calculatorsccglpi/front/config.form.php';
    }

    public function run(): void
    {
        $self = self::selfUrl();

        $this->handlePost($self);

        Html::header('calculatorSCCGLPI', $self, 'config', 'plugins');
        $this->renderForm($self);
        Html::footer();
    }

    private function handlePost(string $self): void
    {
        if (isset($_POST['add'])) {
            $name = trim((string) ($_POST['new_name'] ?? ''));
            if ($name !== '') {
                $this->config->addEntry($name);
                Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            }
            Html::redirect($self);
        }

        if (isset($_POST['delete'])) {
            $name = trim((string) $_POST['delete']);
            if ($name !== '') {
                $this->config->deleteEntry($name);
                Session::addMessageAfterRedirect(__('Item successfully deleted'));
            }
            Html::redirect($self);
        }

        if (isset($_POST['save'])) {
            $active  = (array) ($_POST['active'] ?? []);
            $entries = [];
            foreach ($this->config->disallowedEntries() as $entry) {
                $entry['active'] = !empty($active[$entry['name']]);
                $entries[]       = $entry;
            }
            $this->config->saveEntries($entries);
            Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            Html::redirect($self);
        }
    }

    private function renderForm(string $self): void
    {
        $entries = $this->config->disallowedEntries();

        $title    = htmlescape(self::t('Disk space summary'));
        $intro    = htmlescape(self::t('Filesystem types listed here are excluded from the disk space summary shown on the Volumes tab. Type them exactly as reported by the inventory (e.g. tmpfs, devtmpfs); matching is case-insensitive.'));
        $listLbl  = htmlescape(self::t('Excluded filesystem types'));
        $emptyLbl = htmlescape(self::t('No excluded filesystem types - every volume is counted.'));
        $addLbl   = htmlescape(self::t('Add filesystem type'));
        $phLbl    = htmlescape(self::t('e.g. tmpfs'));

        $selfH = htmlescape($self);
        $csrf  = Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo "<div class='card m-4' style='max-width: 640px;'>";
        echo "<div class='card-header'><h3 class='card-title'><i class='ti ti-database me-2'></i>{$title}</h3></div>";
        echo "<div class='card-body'>";
        echo "<p class='text-muted'>{$intro}</p>";

        echo "<form method='post' action='{$selfH}' class='d-flex gap-2 mb-4'>";
        echo "<input type='text' class='form-control' name='new_name' placeholder='{$phLbl}' "
            . "aria-label='{$addLbl}' maxlength='255' required>";
        echo "<button type='submit' name='add' value='1' class='btn btn-primary text-nowrap'>"
            . "<i class='ti ti-plus me-1'></i>" . htmlescape(__('Add')) . "</button>";
        echo $csrf;
        echo "</form>";

        echo "<div class='mb-2 fw-bold'>{$listLbl}</div>";
        if ($entries === []) {
            echo "<p class='text-muted'>{$emptyLbl}</p>";
        } else {
            echo "<form method='post' action='{$selfH}'>";
            echo "<table class='table table-sm align-middle'>";
            foreach ($entries as $entry) {
                $nameH   = htmlescape($entry['name']);
                $checked = $entry['active'] ? 'checked' : '';
                $state   = $entry['active']
                    ? htmlescape(self::t('excluded'))
                    : htmlescape(self::t('inactive (counted)'));
                echo "<tr>";
                echo "<td><code>{$nameH}</code></td>";
                echo "<td><label class='form-check form-switch mb-0'>"
                    . "<input type='checkbox' class='form-check-input' name='active[{$nameH}]' value='1' {$checked}>"
                    . "<span class='form-check-label text-muted'>{$state}</span></label></td>";
                echo "<td class='text-end'><button type='submit' name='delete' value='{$nameH}' "
                    . "class='btn btn-sm btn-ghost-danger' title='" . htmlescape(__('Delete')) . "'>"
                    . "<i class='ti ti-trash'></i></button></td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<button type='submit' name='save' value='1' class='btn btn-primary'>"
                . "<i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Save')) . "</button>";
            echo $csrf;
            echo "</form>";
        }

        echo "</div></div>";
    }
}
