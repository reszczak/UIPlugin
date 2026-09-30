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
            $this->config->save($_POST);
            Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            Html::redirect($self);
        }
    }

    private function renderForm(string $self): void
    {
        $config  = new PluginUploadsccglpiConfig();
        $storage = new PluginUploadsccglpiStorage($config);

        $title    = htmlescape(self::t('Upload'));
        $intro    = htmlescape(self::t('Files uploaded from the Upload page are written to a fixed directory on the server and indexed in the database.'));
        $extLbl   = htmlescape(self::t('Archive extensions'));
        $extHint  = htmlescape(self::t('The data half of a pair. Comma separated, without the leading dot; compound extensions are allowed (e.g. tar.gz,gz) and the longest one wins when splitting a name.'));
        $sigLbl   = htmlescape(self::t('Marker extension'));
        $sigHint  = htmlescape(self::t('The other half of a pair, sharing the name of the archive (e.g. signal).'));
        $sizeLbl  = htmlescape(self::t('Maximum size per file (MB)'));
        $sizeHint = htmlescape(sprintf(
            self::t('PHP itself refuses anything above %s (upload_max_filesize / post_max_size), so the effective limit is %s.'),
            PluginUploadsccglpiStorage::formatSize(PluginUploadsccglpiConfig::iniBytes((string) ini_get('upload_max_filesize'))),
            PluginUploadsccglpiStorage::formatSize($config->effectiveMaxSizeBytes())
        ));

        $filesLbl  = htmlescape(self::t('Maximum number of files per upload'));
        $filesHint = $config->phpLimitBelowSetting()
            ? htmlescape(sprintf(
                self::t('WARNING: PHP on this server accepts only %d files per request (max_file_uploads) and drops the rest without a word. Raise that directive, or lower this setting to match.'),
                $config->phpMaxFiles()
            ))
            : htmlescape(sprintf(
                self::t('PHP on this server accepts %d files per request (max_file_uploads), so this setting is the one that applies.'),
                $config->phpMaxFiles()
            ));

        $extValue  = htmlescape(implode(',', $config->archiveExtensions()));
        $sigValue  = htmlescape($config->signalExtension());
        $sizeValue  = htmlescape((string) $config->maxSizeMb());
        $filesValue = htmlescape((string) $config->maxFiles());
        $selfH     = htmlescape($self);

        echo "<div class='card m-4' style='max-width: 760px;'>";
        echo "<div class='card-header'><h3 class='card-title'>"
            . "<i class='ti ti-cloud-upload me-2'></i>{$title}</h3></div>";
        echo "<div class='card-body'>";
        echo "<p class='text-muted'>{$intro}</p>";

        $this->renderStorageDir($storage);

        echo "<form method='post' action='{$selfH}'>";

        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='uploadsccglpi_ext'>{$extLbl}</label>";
        echo "<input type='text' class='form-control' id='uploadsccglpi_ext' name='archive_extensions' "
            . "value='{$extValue}' maxlength='255' required>";
        echo "<div class='form-text'>{$extHint}</div>";
        echo "</div>";

        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='uploadsccglpi_sig'>{$sigLbl}</label>";
        echo "<input type='text' class='form-control' id='uploadsccglpi_sig' name='signal_extension' "
            . "value='{$sigValue}' maxlength='64' style='max-width:240px' required>";
        echo "<div class='form-text'>{$sigHint}</div>";
        echo "</div>";

        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='uploadsccglpi_size'>{$sizeLbl}</label>";
        echo "<input type='number' class='form-control' id='uploadsccglpi_size' name='max_size_mb' "
            . "value='{$sizeValue}' min='1' max='4096' style='max-width:160px' required>";
        echo "<div class='form-text'>{$sizeHint}</div>";
        echo "</div>";

        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='uploadsccglpi_files'>{$filesLbl}</label>";
        echo "<input type='number' class='form-control' id='uploadsccglpi_files' name='max_files' "
            . "value='{$filesValue}' min='1' max='10000' style='max-width:160px' required>";
        echo "<div class='form-text" . ($config->phpLimitBelowSetting() ? ' text-danger' : '') . "'>{$filesHint}</div>";
        echo "</div>";

        echo "<hr>";
        echo "<div class='mb-3 fw-bold'>" . htmlescape(self::t('Content checks')) . "</div>";
        echo "<p class='text-muted'>" . htmlescape(self::t('Read from the .cur file inside the archive, before anything is written to disk. An archive that fails either check is refused and the pair is not stored.')) . "</p>";

        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='uploadsccglpi_age'>"
            . htmlescape(self::t('Maximum inventory age (days)')) . "</label>";
        echo "<input type='number' class='form-control' id='uploadsccglpi_age' name='max_age_days' "
            . "value='" . htmlescape((string) $config->maxAgeDays()) . "' min='1' max='3650' "
            . "style='max-width:160px' required>";
        echo "<div class='form-text'>" . htmlescape(self::t('Checked against var:general::date in the .cur file.')) . "</div>";
        echo "</div>";

        $this->renderAgentVersions($config);

        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='uploadsccglpi_gap'>"
            . htmlescape(self::t('Maximum version gap')) . "</label>";
        echo "<input type='number' class='form-control' id='uploadsccglpi_gap' name='version_max_gap' "
            . "value='" . htmlescape((string) $config->versionMaxGap()) . "' min='1' max='999' "
            . "style='max-width:160px' required>";
        $ranges = [];
        foreach (PluginUploadsccglpiAgentVersions::platforms() as $os) {
            $oldest = $config->oldestAcceptedVersion($os);
            $ranges[] = sprintf(
                '%s: %s',
                PluginUploadsccglpiAgentVersions::platformName($os),
                $oldest ?? self::t('none')
            );
        }
        echo "<div class='form-text'>" . htmlescape(sprintf(
            self::t('An archive this far behind the newest version, or further, is refused. Accepted at the moment - %s and newer.'),
            implode('; ', $ranges)
        )) . "</div>";
        echo "</div>";

        echo "<button type='submit' name='save' value='1' class='btn btn-primary'>"
            . "<i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Save')) . "</button>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "</form>";

        echo "</div></div>";
    }

    private function renderAgentVersions(PluginUploadsccglpiConfig $config): void
    {
        global $CFG_GLPI;

        $state = $config->agentVersions();

        echo "<div class='mb-3'>";
        echo "<div class='form-label'>" . htmlescape(self::t('Newest agent versions')) . "</div>";

        $issue = PluginUploadsccglpiAgentVersions::issueMessage($state);
        if ($issue !== '') {
            echo "<div class='alert alert-warning mb-2'><i class='ti ti-alert-triangle me-1'></i>"
                . htmlescape($issue) . "</div>";
        }

        echo "<table class='table table-sm w-auto mb-2'><thead><tr>"
            . "<th>" . htmlescape(self::t('System')) . "</th>"
            . "<th>" . htmlescape(self::t('Version')) . "</th>"
            . "<th>" . htmlescape(self::t('Source')) . "</th>"
            . "<th>" . htmlescape(self::t('Changed')) . "</th>"
            . "<th>" . htmlescape(self::t('Last read from the article')) . "</th>"
            . "</tr></thead><tbody>";
        foreach (PluginUploadsccglpiAgentVersions::platforms() as $os) {
            $row = $state['versions'][$os] ?? null;
            echo "<tr><td class='pe-4'>" . htmlescape(PluginUploadsccglpiAgentVersions::platformName($os)) . "</td>";
            if ($row === null) {
                echo "<td colspan='4'><span class='badge bg-red-lt'>"
                    . htmlescape(self::t('unknown - uploads from this system are refused')) . "</span></td></tr>";
                continue;
            }
            $source = $row['source'] === PluginUploadsccglpiAgentVersions::SOURCE_ARTICLE
                ? "<span class='badge bg-green-lt'>" . htmlescape(self::t('knowledge base article')) . "</span>"
                : "<span class='badge bg-yellow-lt'>" . htmlescape(self::t('last saved copy')) . "</span>";
            echo "<td><code>" . htmlescape($row['version']) . "</code></td>"
                . "<td>{$source}</td>"
                . "<td>" . htmlescape(Html::convDateTime($row['date_mod']) ?? '-') . "</td>"
                . "<td>" . htmlescape(Html::convDateTime($row['date_sync']) ?? '-') . "</td></tr>";
        }
        echo "</tbody></table>";

        $root = $CFG_GLPI['root_doc'] ?? '';
        $url  = $state['article'] !== null
            ? $root . '/front/knowbaseitem.form.php?id=' . $state['article']['id']
            : $root . '/front/knowbaseitem.php';
        echo "<div class='form-text'>" . htmlescape(sprintf(
            self::t('Read from the knowledge base article "%s" on every visit and saved in the plugin\'s database. Edit the article to raise a version - this is not a setting of the plugin. If the article disappears or can no longer be read, the last saved version stays in force.'),
            PluginUploadsccglpiAgentVersions::KB_TITLE
        )) . " <a href='" . htmlescape($url) . "'>" . htmlescape(
            $state['article'] !== null ? self::t('Open the article') : self::t('Open the knowledge base')
        ) . "</a></div>";

        echo "<div class='form-text'>" . htmlescape(sprintf(
            self::t('Which line applies is decided per archive by fix:general::scc.conf:os_category. %s map to Linux / Unix; Windows has its own.'),
            'Linux, HPUX, SunOS, AIX'
        )) . "</div>";
        echo "</div>";
    }

    private function renderStorageDir(PluginUploadsccglpiStorage $storage): void
    {
        $dir      = $storage->rootDir();
        $writable = is_dir($dir) && is_writable($dir);

        $label = htmlescape(self::t('Storage directory'));
        $dirH  = htmlescape($dir);
        $state = $writable
            ? "<span class='badge bg-green-lt'>" . htmlescape(self::t('writable')) . "</span>"
            : "<span class='badge bg-red-lt'>" . htmlescape(self::t('missing or read-only')) . "</span>";

        echo "<div class='mb-4'>";
        echo "<div class='form-label'>{$label}</div>";
        echo "<div class='d-flex justify-content-between align-items-center flex-wrap gap-2 "
            . "border rounded px-3 py-2 bg-light-lt'>";
        echo "<code class='text-break'>{$dirH}</code>";
        echo $state;
        echo "</div>";

        echo "<div class='form-text'>" . htmlescape(self::t(
            'This path is fixed and cannot be changed from GLPI. To have another tool read the files from elsewhere, symlink this directory.'
        )) . "</div>";

        if (!$writable) {
            echo "<div class='form-text text-danger'>" . htmlescape(self::t(
                'The directory is created automatically on the first upload if its parent is writable by the web server user.'
            )) . "</div>";
        }

        echo "</div>";
    }
}
