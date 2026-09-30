<?php
class PluginUploadsccglpiUploadPanel
{
    private const LIST_LIMIT = 100;

    private const REPORT_KEY = 'plugin_uploadsccglpi_report';

    private PluginUploadsccglpiConfig $config;
    private PluginUploadsccglpiStorage $storage;
    private PluginUploadsccglpiInventoryCheck $check;

    public function __construct(?PluginUploadsccglpiConfig $config = null)
    {
        $this->config  = $config ?? new PluginUploadsccglpiConfig();
        $this->storage = new PluginUploadsccglpiStorage($this->config);
        $this->check   = new PluginUploadsccglpiInventoryCheck($this->config);
    }

    private static function reportRow(string $host, array $files, bool $ok, string $reason): array
    {
        return ['host' => $host, 'files' => $files, 'ok' => $ok, 'reason' => $reason];
    }

    private static function t(string $s): string
    {
        return __($s, 'uploadsccglpi');
    }

    private static function selfUrl(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/uploadsccglpi/front/upload.form.php';
    }

    public function run(): void
    {
        $self = self::selfUrl();

        $this->handlePost($self);

        Html::header(
            self::t('Upload'),
            $self,
            'uploadsccglpi',
            'PluginUploadsccglpiUploadedFile'
        );
        $this->renderForm($self);
        $this->renderReport();
        $this->renderList($self);
        Html::footer();
    }

    private function handlePost(string $self): void
    {
        if (isset($_POST['upload'])) {
            if (!PluginUploadsccglpiUploadedFile::canUpload()) {
                Html::displayRightError();
            }
            $this->handleUpload();
            Html::redirect($self);
        }

        if (isset($_POST['delete'])) {
            Session::checkRight(PluginUploadsccglpiUploadedFile::$rightname, PURGE);
            $this->handleDelete((int) $_POST['delete']);
            Html::redirect($self);
        }
    }

    private function handleUpload(): void
    {
        $uploads = self::normalizeUploads($_FILES['upload_files'] ?? []);
        if ($uploads === []) {
            Session::addMessageAfterRedirect(self::t('No file was selected.'), false, ERROR);
            return;
        }

        $limit = $this->config->maxFiles();
        if (count($uploads) > $limit) {
            Session::addMessageAfterRedirect(
                sprintf(self::t('Too many files in one upload (limit: %d).'), $limit),
                false,
                ERROR
            );
            return;
        }

        $phpLimit = $this->config->phpMaxFiles();
        if ($phpLimit > 0 && count($uploads) === $phpLimit) {
            Session::addMessageAfterRedirect(
                sprintf(
                    self::t('PHP caps the number of files per request (max_file_uploads: %d) and drops the rest without a word; if you selected more, they never reached GLPI.'),
                    $phpLimit
                ),
                false,
                WARNING
            );
        }

        $report = [];
        $pairs   = $this->groupIntoPairs($uploads, $report);

        $stored = 0;
        foreach ($pairs as $pair) {
            $files = [$pair['archive']['name'], $pair['signal']['name']];

            $reason = $this->check->check((string) $pair['archive']['upload']['tmp_name']);
            if ($reason !== '') {
                $report[] = self::reportRow($pair['stem'], $files, false, $reason);
                continue;
            }

            $failure = $this->storePair($pair);
            $report[] = self::reportRow($pair['stem'], $files, $failure === '', $failure);
            if ($failure === '') {
                $stored++;
            }
        }

        $_SESSION[self::REPORT_KEY] = $report;

        if ($stored > 0) {
            Session::addMessageAfterRedirect(
                sprintf(self::t('Pairs uploaded: %d'), $stored)
            );
        }
    }

    private function groupIntoPairs(array $uploads, array &$report): array
    {
        $archives = $this->config->archiveExtensions();
        $signal   = $this->config->signalExtension();
        $known    = $this->config->allowedExtensions();

        $groups = [];
        foreach ($uploads as $upload) {
            $name = (string) ($upload['name'] ?? '');

            $rejection = $this->storage->validate($upload);
            if ($rejection !== '') {
                $report[] = self::reportRow($name, [$name], false, $rejection);
                continue;
            }

            ['stem' => $stem, 'extension' => $extension] =
                PluginUploadsccglpiStorage::splitName($name, $known);

            $role = $extension === $signal ? 'signal' : (in_array($extension, $archives, true) ? 'archive' : '');
            if ($role === '') {
                $report[] = self::reportRow($name, [$name], false, self::t('Unrecognised extension.'));
                continue;
            }

            $key = mb_strtolower($stem);
            if (isset($groups[$key][$role])) {
                $report[] = self::reportRow($name, [$name], false, sprintf(
                    self::t('Two files of the same kind for one host - a pair is one archive and one %s file.'),
                    '.' . $signal
                ));
                continue;
            }

            $groups[$key]['stem']  ??= $stem;
            $groups[$key][$role]     = ['upload' => $upload, 'extension' => $extension, 'name' => $name];
        }

        $pairs = [];
        foreach ($groups as $group) {
            $stem = (string) $group['stem'];
            if (!isset($group['archive'])) {
                $report[] = self::reportRow(
                    $stem,
                    [$group['signal']['name']],
                    false,
                    self::t('The archive of this pair is missing.')
                );
                continue;
            }
            if (!isset($group['signal'])) {
                $report[] = self::reportRow(
                    $stem,
                    [$group['archive']['name']],
                    false,
                    sprintf(self::t('The matching "%s" file is missing.'), $stem . '.' . $signal)
                );
                continue;
            }
            $pairs[] = ['stem' => $stem, 'archive' => $group['archive'], 'signal' => $group['signal']];
        }

        return $pairs;
    }

    private function storePair(array $pair): string
    {
        $extensions = [$pair['archive']['extension'], $pair['signal']['extension']];
        $stem       = $this->storage->freeStemForPair($pair['stem'], $extensions);

        $pairKey = $stem . '#' . uniqid();

        $written = [];
        foreach (['archive', 'signal'] as $role) {
            $part   = $pair[$role];
            $result = $this->storage->store($part['upload'], $stem . '.' . $part['extension']);

            if (!$result['ok']) {
                $this->rollbackPair($written);
                return $result['error'];
            }

            $result['pair_key'] = $pairKey;
            $id = $this->recordUpload($result);
            if ($id === false) {
                $this->storage->removeStored($result);
                $this->rollbackPair($written);
                return self::t('The file could not be registered in the database.');
            }

            $written[] = ['id' => $id, 'fields' => $result];
        }

        return '';
    }

    private function rollbackPair(array $written): void
    {
        foreach ($written as $entry) {
            $item = new PluginUploadsccglpiUploadedFile();
            if ($item->getFromDB($entry['id'])) {
                $item->delete(['id' => $entry['id']], true);
            }
            $this->storage->removeStored($entry['fields']);
        }
    }

    private function recordUpload(array $result): int|false
    {
        $item = new PluginUploadsccglpiUploadedFile();

        try {
            return $item->add([
                'entities_id'   => (int) ($_SESSION['glpiactive_entity'] ?? 0),
                'is_recursive'  => 0,
                'name'          => $result['name'],
                'filename'      => $result['filename'],
                'filepath'      => $result['filepath'],
                'storage_dir'   => $result['storage_dir'],
                'pair_key'      => $result['pair_key'] ?? '',
                'extension'     => $result['extension'],
                'mime'          => $result['mime'],
                'filesize'      => $result['filesize'],
                'sha256'        => $result['sha256'],
                'users_id'      => (int) Session::getLoginUserID(),
                'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
                'date_mod'      => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            trigger_error(
                'uploadsccglpi: could not register ' . $result['filename'] . ' - ' . $e->getMessage(),
                E_USER_WARNING
            );
            return false;
        }
    }

    private function handleDelete(int $id): void
    {
        $item = new PluginUploadsccglpiUploadedFile();
        if ($id <= 0 || !$item->getFromDB($id) || !$item->canPurgeItem()) {
            Session::addMessageAfterRedirect(self::t('This file no longer exists.'), false, ERROR);
            return;
        }

        $deleted = 0;
        foreach (PluginUploadsccglpiUploadedFile::siblingIds($item->fields) as $siblingId) {
            $sibling = new PluginUploadsccglpiUploadedFile();
            if (!$sibling->getFromDB($siblingId) || !$sibling->canPurgeItem()) {
                continue;
            }

            $sibling->delete(['id' => $siblingId], true);
            $deleted++;
        }

        if ($deleted === 0) {
            Session::addMessageAfterRedirect(self::t('This file no longer exists.'), false, ERROR);
            return;
        }

        Session::addMessageAfterRedirect(__('Item successfully deleted'));
    }

    private static function normalizeUploads(array $files): array
    {
        $names = $files['name'] ?? null;
        if (!is_array($names)) {
            $names = $names === null ? [] : [$names];
            foreach (['tmp_name', 'error', 'size'] as $key) {
                $files[$key] = [$files[$key] ?? null];
            }
            $files['name'] = $names;
        }

        $out = [];
        foreach (array_keys($files['name']) as $index) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => (string) ($files['name'][$index] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
                'error'    => $error,
                'size'     => (int) ($files['size'][$index] ?? 0),
            ];
        }

        return $out;
    }

    private function renderForm(string $self): void
    {
        $extensions = $this->config->allowedExtensions();
        $accept     = implode(',', array_map(static fn(string $e) => '.' . $e, $extensions));

        $title  = htmlescape(self::t('Upload a file'));
        $intro  = htmlescape(sprintf(
            self::t('You can select up to %d files with the extension %s or %s.'),
            $this->config->maxFiles(),
            implode(', ', array_map(static fn(string $e) => '.' . $e, $this->config->archiveExtensions())),
            '.' . $this->config->signalExtension()
        ));
        $label  = htmlescape(self::t('File to upload'));
        $submit = htmlescape(self::t('Upload'));
        $selfH  = htmlescape($self);
        $acceptH = htmlescape($accept);

        echo "<div class='card m-4'>";
        echo "<div class='card-header'><h3 class='card-title'>"
            . "<i class='ti ti-cloud-upload me-2'></i>{$title}</h3></div>";
        echo "<div class='card-body'>";
        echo "<p class='text-muted'>{$intro}</p>";

        $this->renderAgentVersions();

        if (!PluginUploadsccglpiUploadedFile::canUpload()) {
            echo "<div class='alert alert-info mb-0'>"
                . htmlescape(self::t('You are not allowed to upload files.')) . "</div>";
        } else {
            $this->renderStorageWarning();
            echo "<form method='post' action='{$selfH}' enctype='multipart/form-data' "
                . "class='d-flex gap-2 align-items-start flex-wrap'>";
            echo "<input type='file' class='form-control' style='max-width:480px' "
                . "name='upload_files[]' accept='{$acceptH}' aria-label='{$label}' multiple required>";
            echo "<button type='submit' name='upload' value='1' class='btn btn-primary text-nowrap'>"
                . "<i class='ti ti-upload me-1'></i>{$submit}</button>";
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "</form>";
        }

        echo "</div></div>";
    }

    private function renderAgentVersions(): void
    {
        $state = $this->config->agentVersions();

        $items = [];
        foreach (PluginUploadsccglpiAgentVersions::platforms() as $os) {
            $newest = $this->config->newestVersionFor($os);
            $items[] = "<span class='me-4'>" . htmlescape(PluginUploadsccglpiAgentVersions::platformName($os)) . ": "
                . ($newest === null
                    ? "<span class='badge bg-red-lt'>" . htmlescape(self::t('unknown')) . "</span>"
                    : "<code>" . htmlescape($newest) . "</code>")
                . "</span>";
        }

        echo "<div class='mb-3'><span class='fw-bold me-2'>" . htmlescape(self::t('Newest agent versions')) . ":</span>"
            . implode('', $items) . "</div>";

        $issue = PluginUploadsccglpiAgentVersions::issueMessage($state);
        if ($issue !== '' && Session::haveRight('config', UPDATE)) {
            echo "<div class='alert alert-warning'><i class='ti ti-alert-triangle me-1'></i>"
                . htmlescape($issue) . "</div>";
        }
    }

    private function renderStorageWarning(): void
    {
        if ($this->storage->ensureRootDir()) {
            return;
        }

        $message = Session::haveRight('config', UPDATE)
            ? htmlescape(sprintf(
                self::t('Storage directory "%s" does not exist or is not writable.'),
                $this->storage->rootDir()
            ))
            : htmlescape(self::t('The storage directory is not writable - contact your GLPI administrator.'));
        echo "<div class='alert alert-danger'><i class='ti ti-alert-triangle me-1'></i>{$message}</div>";
    }

    private function renderReport(): void
    {
        $report = $_SESSION[self::REPORT_KEY] ?? [];
        unset($_SESSION[self::REPORT_KEY]);

        if (!is_array($report) || $report === []) {
            return;
        }

        $loaded  = count(array_filter($report, static fn(array $r) => $r['ok']));
        $refused = count($report) - $loaded;

        $title = htmlescape(self::t('Result of the last upload'));

        echo "<div class='card m-4'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>";
        echo "<h3 class='card-title mb-0'><i class='ti ti-clipboard-check me-2'></i>{$title}</h3>";
        echo "<div class='d-flex gap-2'>";
        echo "<span class='badge bg-green-lt'>" . htmlescape(sprintf(self::t('loaded: %d'), $loaded)) . "</span>";
        if ($refused > 0) {
            echo "<span class='badge bg-red-lt'>" . htmlescape(sprintf(self::t('refused: %d'), $refused)) . "</span>";
        }
        echo "</div></div>";
        echo "<div class='card-body'>";
        echo "<div class='table-responsive'><table class='table table-sm align-middle'>";
        echo "<thead><tr>";
        echo "<th>" . htmlescape(self::t('Host')) . "</th>";
        echo "<th>" . htmlescape(self::t('Files')) . "</th>";
        echo "<th>" . htmlescape(__('Status')) . "</th>";
        echo "<th>" . htmlescape(self::t('Reason')) . "</th>";
        echo "</tr></thead><tbody>";

        foreach ($report as $row) {
            $files = implode('<br>', array_map(
                static fn(string $f) => '<code>' . htmlescape($f) . '</code>',
                (array) $row['files']
            ));
            $status = $row['ok']
                ? "<span class='badge bg-green-lt'><i class='ti ti-check me-1'></i>"
                    . htmlescape(self::t('loaded')) . "</span>"
                : "<span class='badge bg-red-lt'><i class='ti ti-x me-1'></i>"
                    . htmlescape(self::t('not loaded')) . "</span>";

            echo "<tr>";
            echo "<td>" . htmlescape((string) $row['host']) . "</td>";
            echo "<td>{$files}</td>";
            echo "<td class='text-nowrap'>{$status}</td>";
            echo "<td class='text-muted'>" . htmlescape((string) $row['reason']) . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table></div>";
        echo "</div></div>";
    }

    private function renderList(string $self): void
    {
        $pairs = PluginUploadsccglpiUploadedFile::findVisiblePairs(self::LIST_LIMIT);
        $total = PluginUploadsccglpiUploadedFile::countVisiblePairs();

        $title    = htmlescape(self::t('Upload log'));
        $everyone = PluginUploadsccglpiUploadedFile::canSeeEveryUpload();
        $scope    = htmlescape($everyone
            ? self::t('Files uploaded by every user are listed.')
            : self::t('Only the files you uploaded yourself are listed.'));

        echo "<div class='card m-4'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>";
        echo "<div><h3 class='card-title mb-0'><i class='ti ti-files me-2'></i>{$title}</h3>";
        echo "<div class='text-muted small'>{$scope}</div></div>";
        echo "<span class='badge bg-secondary'>" . htmlescape((string) $total) . "</span>";
        echo "</div>";
        echo "<div class='card-body'>";

        if ($pairs === []) {
            echo "<p class='text-muted mb-0'>"
                . htmlescape(self::t('No file has been uploaded yet.')) . "</p>";
        } else {
            $this->renderTable($pairs, $self);
            if ($total > count($pairs)) {
                echo "<p class='text-muted small mb-0'>" . htmlescape(sprintf(
                    self::t('Only the %d most recent entries are listed.'),
                    count($pairs)
                )) . "</p>";
            }
        }

        echo "</div></div>";
    }

    private function renderTable(array $pairs, string $self): void
    {
        $canPurge = PluginUploadsccglpiUploadedFile::canPurge();
        $showUser = PluginUploadsccglpiUploadedFile::canSeeEveryUpload();
        $selfH    = htmlescape($self);

        echo "<form method='post' action='{$selfH}'>";
        echo "<div class='table-responsive'><table class='table table-sm align-middle'>";
        echo "<thead><tr>";
        echo "<th>" . htmlescape(self::t('Host')) . "</th>";
        echo "<th>" . htmlescape(self::t('Files')) . "</th>";
        echo "<th>" . htmlescape(self::t('Size')) . "</th>";
        if ($showUser) {
            echo "<th>" . htmlescape(__('User')) . "</th>";
        }
        echo "<th>" . htmlescape(__('Creation date')) . "</th>";
        echo "<th class='text-end'>" . htmlescape(__('Actions')) . "</th>";
        echo "</tr></thead><tbody>";

        foreach ($pairs as $pair) {
            $this->renderPairRow($pair, $showUser, $canPurge);
        }

        echo "</tbody></table></div>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "</form>";
    }

    private function renderPairRow(array $pair, bool $showUser, bool $canPurge): void
    {
        $size    = 0;
        $links   = [];
        $firstId = 0;

        foreach ($pair['rows'] as $row) {
            $size   += (int) $row['filesize'];
            $firstId = $firstId ?: (int) $row['id'];

            $links[] = "<code>" . htmlescape((string) $row['filename']) . "</code>";
        }

        $incomplete = count($pair['rows']) < 2
            ? " <span class='badge bg-red-lt'>" . htmlescape(self::t('incomplete pair')) . "</span>"
            : '';

        $label = explode('#', (string) $pair['key'])[0];

        echo "<tr>";
        echo "<td><i class='ti ti-device-desktop me-1 text-muted'></i>"
            . htmlescape($label) . $incomplete . "</td>";
        echo "<td>" . implode('<br>', $links) . "</td>";
        echo "<td class='text-nowrap'>" . htmlescape(PluginUploadsccglpiStorage::formatSize($size)) . "</td>";
        if ($showUser) {
            echo "<td>" . htmlescape(getUserName((int) $pair['users_id'])) . "</td>";
        }
        echo "<td class='text-nowrap'>" . htmlescape(Html::convDateTime((string) $pair['date'])) . "</td>";
        echo "<td class='text-end text-nowrap'>";
        if ($canPurge) {
            echo "<button type='submit' name='delete' value='{$firstId}' "
                . "class='btn btn-sm btn-ghost-danger' title='" . htmlescape(__('Delete')) . "'>"
                . "<i class='ti ti-trash'></i></button>";
        }
        echo "</td>";
        echo "</tr>";
    }
}
