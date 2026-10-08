<?php
class PluginUploadsccglpiAdminPanel
{
    private PluginUploadsccglpiConfig $config;

    public function __construct(?PluginUploadsccglpiConfig $config = null)
    {
        $this->config = $config ?? new PluginUploadsccglpiConfig();
    }

    private static function t(string $s): string
    {
        return __($s, 'uploadsccglpi');
    }

    private static function selfUrl(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/uploadsccglpi/front/config.form.php';
    }

    public function run(): void
    {
        $self = self::selfUrl();

        $this->handlePost($self);

        Html::header('uploadSCCGLPI', $self, 'config', 'plugins');
        $this->renderForm($self);
        Html::footer();
    }

    private function handlePost(string $self): void
    {
        if (isset($_POST['save'])) {
            $adjusted = $this->config->save($_POST);
            Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            if ($adjusted !== []) {
                Session::addMessageAfterRedirect(
                    self::t('Some values were invalid or out of range and were adjusted - check the form.'),
                    false,
                    WARNING
                );
            }
            Html::redirect($self);
        }
    }

    private function renderForm(string $self): void
    {
        $config  = new PluginUploadsccglpiConfig();
        $storage = new PluginUploadsccglpiStorage($config);

        echo "<div class='card m-4' style='max-width: 640px;'>";
        echo "<div class='card-header'><h3 class='card-title'>"
            . "<i class='ti ti-cloud-upload me-2'></i>" . htmlescape(self::t('Upload')) . "</h3></div>";
        echo "<div class='card-body'>";

        $this->renderStorageDir($storage);
        $this->renderAgentVersions($config);

        echo "<form method='post' action='" . htmlescape($self) . "'>";

        self::field('archive_extensions', self::t('Archive extensions'), implode(',', $config->archiveExtensions()), 'text', "maxlength='255'", self::t('Comma-separated, e.g. tar.gz,gz.'));
        self::field('signal_extension', self::t('Marker extension'), $config->signalExtension(), 'text', "maxlength='64'", self::t('Every archive must come with a file with this extension and the same name.'));
        self::field('max_size_mb', self::t('Maximum size per file (MB)'), (string) $config->maxSizeMb(), 'number', "min='1' max='4096'", self::t('Applies to each file separately.'));
        self::field('max_files', self::t('Maximum number of files per upload'), (string) $config->maxFiles(), 'number', "min='1' max='10000'", self::t('Archives and marker files together, in one upload.'));
        self::field('max_age_days', self::t('Maximum inventory age (days)'), (string) $config->maxAgeDays(), 'number', "min='1' max='3650'", self::t('Counted from the inventory date in the .cur file.'));
        self::field('version_max_gap', self::t('Maximum version gap'), (string) $config->versionMaxGap(), 'number', "min='1' max='999'", self::t('Counted from the newest version in the knowledge base; with 3, the newest and the two previous ones are accepted.'));

        echo "<button type='submit' name='save' value='1' class='btn btn-primary'>"
            . "<i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Save')) . "</button>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "</form>";

        echo "</div></div>";
    }

    private static function field(string $name, string $label, string $value, string $type, string $attributes, string $help): void
    {
        $id    = 'uploadsccglpi_' . $name;
        $width = $type === 'number' ? '160px' : '320px';

        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='{$id}'>" . htmlescape($label) . "</label>";
        echo "<input type='{$type}' class='form-control' id='{$id}' name='{$name}' "
            . "value='" . htmlescape($value) . "' {$attributes} style='max-width:{$width}' required>";
        echo "<div class='form-text'>" . htmlescape($help) . "</div>";
        echo "</div>";
    }

    private function renderAgentVersions(PluginUploadsccglpiConfig $config): void
    {
        global $CFG_GLPI;

        $state = $config->agentVersions();
        $root  = $CFG_GLPI['root_doc'] ?? '';
        $url   = $state['article'] !== null
            ? $root . '/front/knowbaseitem.form.php?id=' . $state['article']['id']
            : $root . '/front/knowbaseitem.php';

        echo "<div class='mb-4'>";
        echo "<div class='form-label'>" . htmlescape(self::t('Newest agent versions'))
            . " <a href='" . htmlescape($url) . "' class='ms-1' title='" . htmlescape(PluginUploadsccglpiAgentVersions::KB_TITLE) . "'>"
            . "<i class='ti ti-external-link'></i></a></div>";

        $issue = PluginUploadsccglpiAgentVersions::issueMessage($state);
        if ($issue !== '') {
            echo "<div class='alert alert-warning mb-2'>" . htmlescape($issue) . "</div>";
        }

        echo "<table class='table table-sm w-auto mb-0'><thead><tr><th></th>"
            . "<th>" . htmlescape(self::t('Newest')) . "</th>"
            . "<th>" . htmlescape(self::t('Minimum version')) . "</th></tr></thead><tbody>";
        foreach (PluginUploadsccglpiAgentVersions::platforms() as $os) {
            $version = $state['versions'][$os]['version'] ?? null;
            echo "<tr><td class='pe-4'>" . htmlescape(PluginUploadsccglpiAgentVersions::platformName($os)) . "</td>";
            if ($version === null) {
                echo "<td colspan='2'><span class='badge bg-red-lt'>" . htmlescape(self::t('unknown')) . "</span></td></tr>";
                continue;
            }
            [$min] = PluginUploadsccglpiInventoryCheck::versionRange($version, $config->versionMaxGap());
            echo "<td class='pe-4'><code>" . htmlescape($version) . "</code></td>"
                . "<td><code>" . htmlescape($min) . "</code></td></tr>";
        }
        echo "</tbody></table>";
        echo "</div>";

        $this->renderVersionHistory();
    }

    private function renderVersionHistory(): void
    {
        $history = PluginUploadsccglpiAgentVersions::history();
        if ($history === []) {
            return;
        }

        echo "<div class='mb-4'>";
        echo "<div class='form-label'>" . htmlescape(self::t('Agent version history')) . "</div>";
        echo "<table class='table table-sm w-auto mb-0'><tbody>";
        foreach ($history as $row) {
            echo "<tr><td class='pe-4 text-secondary'>" . htmlescape(Html::convDateTime((string) $row['date_creation'])) . "</td>"
                . "<td class='pe-4'>" . htmlescape(PluginUploadsccglpiAgentVersions::platformName((string) $row['platform'])) . "</td>"
                . "<td><code>" . htmlescape((string) $row['version']) . "</code></td></tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    }

    private function renderStorageDir(PluginUploadsccglpiStorage $storage): void
    {
        $dir      = $storage->rootDir();
        $writable = is_dir($dir) && is_writable($dir);

        echo "<div class='mb-4'>";
        echo "<div class='form-label'>" . htmlescape(self::t('Storage directory')) . "</div>";
        echo "<div class='d-flex justify-content-between align-items-center flex-wrap gap-2 "
            . "border rounded px-3 py-2 bg-light-lt'>";
        echo "<code class='text-break'>" . htmlescape($dir) . "</code>";
        echo $writable
            ? "<span class='badge bg-green-lt'>" . htmlescape(self::t('writable')) . "</span>"
            : "<span class='badge bg-red-lt'>" . htmlescape(self::t('missing or read-only')) . "</span>";
        echo "</div>";
        echo "</div>";
    }
}
