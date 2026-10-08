<?php
class PluginUploadsccglpiUploadPanel
{
    private const LIST_LIMITS        = [10, 25, 50, 100, 200, 500];
    private const LIST_LIMIT_DEFAULT = 100;
    private const LIST_LIMIT_KEY     = 'plugin_uploadsccglpi_list_limit';
    private const USER_SEARCH_LIMIT  = 20;

    private const REPORT_KEY = 'plugin_uploadsccglpi_report';

    private PluginUploadsccglpiConfig $config;
    private PluginUploadsccglpiStorage $storage;
    private PluginUploadsccglpiInventoryCheck $check;

    private string $userSearch = '';

    private array $userMatches = [];

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
            if (!PluginUploadsccglpiUploadedFile::canPurge()) {
                Html::displayRightError();
            }
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

            $reason = $this->check->check((string) $pair['archive']['upload']['tmp_name'], $pair['stem']);
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

        $groups   = [];
        $rejected = [];
        foreach ($uploads as $upload) {
            $name = (string) ($upload['name'] ?? '');

            $rejection = $this->storage->validate($upload);
            if ($rejection !== '') {
                $report[] = self::reportRow($name, [$name], false, $rejection);
                $rejected[mb_strtolower(PluginUploadsccglpiStorage::splitName($name, $known)['stem'])] = true;
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
            if (isset($rejected[mb_strtolower($stem)]) && (!isset($group['archive']) || !isset($group['signal']))) {
                continue;
            }
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
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>"
            . "<h3 class='card-title mb-0'><i class='ti ti-cloud-upload me-2'></i>{$title}</h3>";
        $this->renderSettingsButton();
        echo "</div>";
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

    private function renderSettingsButton(): void
    {
        global $CFG_GLPI;

        if (!Session::haveRight('config', UPDATE)) {
            return;
        }

        $url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/uploadsccglpi/front/config.form.php';
        echo "<a href='" . htmlescape($url) . "' class='btn btn-outline-secondary btn-sm'>"
            . "<i class='ti ti-settings me-1'></i>" . htmlescape(self::t('Configuration')) . "</a>";
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

    private static function listLimit(): int
    {
        if (isset($_GET['list_limit'])) {
            $requested = (int) $_GET['list_limit'];
            if (in_array($requested, self::LIST_LIMITS, true)) {
                $_SESSION[self::LIST_LIMIT_KEY] = $requested;
            }
        }

        $stored = (int) ($_SESSION[self::LIST_LIMIT_KEY] ?? 0);

        return in_array($stored, self::LIST_LIMITS, true) ? $stored : self::LIST_LIMIT_DEFAULT;
    }

    private function renderLimitSelect(string $self, int $limit): void
    {
        $label = htmlescape(self::t('Entries to show'));

        echo "<form method='get' action='" . htmlescape($self) . "' class='d-flex align-items-center gap-2 flex-wrap'>";
        if (PluginUploadsccglpiUploadedFile::canSeeEveryUpload()) {
            $listed = PluginUploadsccglpiUploadedFile::listedUserId();
            if ($listed !== null) {
                echo "<span class='badge bg-blue-lt fs-6 fw-normal'><i class='ti ti-user me-1'></i>"
                    . htmlescape(getUserName($listed))
                    . " <a href='" . htmlescape($self . '?list_scope=all') . "' class='ms-1 text-reset' title='"
                    . htmlescape(self::t('Show every user')) . "'><i class='ti ti-x'></i></a></span>";
            }
            echo "<div class='position-relative'>";
            echo "<input type='search' id='uploadsccglpi_user_search' name='list_user_search' autocomplete='off' "
                . "class='form-control form-control-sm' style='width:240px' "
                . "value='" . htmlescape($this->userSearch) . "' "
                . "placeholder='" . htmlescape(self::t('Search user...')) . "' "
                . "aria-label='" . htmlescape(self::t('Search user')) . "'>";
            echo "<div id='uploadsccglpi_user_suggest' class='list-group position-absolute shadow' "
                . "style='display:none;z-index:1050;min-width:100%;max-height:320px;overflow:auto'></div>";
            echo "</div>";
            echo $this->suggestScript($self);
        }
        echo "<label class='form-label mb-0 text-muted small' for='uploadsccglpi_list_limit'>{$label}</label>";
        echo "<select id='uploadsccglpi_list_limit' name='list_limit' class='form-select form-select-sm w-auto' "
            . "onchange=\"if (this.form.list_user_search) { this.form.list_user_search.value = ''; } this.form.submit()\">";
        foreach (self::LIST_LIMITS as $option) {
            echo "<option value='{$option}'" . ($option === $limit ? ' selected' : '') . ">{$option}</option>";
        }
        echo "</select><noscript><button type='submit' class='btn btn-sm btn-outline-secondary'>OK</button></noscript>";
        echo "</form>";
    }

    private function suggestScript(string $self): string
    {
        global $CFG_GLPI;

        $config = json_encode([
            'endpoint' => ($CFG_GLPI['root_doc'] ?? '') . '/plugins/uploadsccglpi/front/uploaders.ajax.php',
            'target'   => $self,
            'empty'    => self::t('No matching user found.'),
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        $js = <<<'JS'
(function (cfg) {
    var input = document.getElementById('uploadsccglpi_user_search');
    var box   = document.getElementById('uploadsccglpi_user_suggest');
    if (!input || !box || !window.fetch) { return; }

    var items = [], active = -1, timer = null, seq = 0;

    function hide() { box.style.display = 'none'; active = -1; }

    function go(item) { window.location.href = cfg.target + '?list_scope=' + encodeURIComponent(item.id); }

    function paint() {
        box.textContent = '';
        if (!items.length) {
            var none = document.createElement('div');
            none.className = 'list-group-item py-1 text-muted small';
            none.textContent = cfg.empty;
            box.appendChild(none);
        }
        items.forEach(function (item, i) {
            var a = document.createElement('a');
            a.href = cfg.target + '?list_scope=' + encodeURIComponent(item.id);
            a.className = 'list-group-item list-group-item-action py-1' + (i === active ? ' active' : '');
            a.textContent = item.label + (item.login && item.login !== item.label ? ' (' + item.login + ')' : '');
            box.appendChild(a);
        });
        box.style.display = 'block';
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q === '') { hide(); return; }
        var mine = ++seq;
        timer = setTimeout(function () {
            fetch(cfg.endpoint + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (mine !== seq) { return; }
                    items = d.users || [];
                    active = -1;
                    paint();
                })
                .catch(hide);
        }, 150);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hide(); return; }
        if (box.style.display === 'none' || !items.length) { return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            paint();
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            go(items[active]);
        } else if (e.key === 'Enter' && items.length === 1) {
            e.preventDefault();
            go(items[0]);
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target !== input && !box.contains(e.target)) { hide(); }
    });
})(__CONFIG__);
JS;

        return Html::scriptBlock(str_replace('__CONFIG__', (string) $config, $js));
    }

    private function searchUsers(): void
    {
        if (!PluginUploadsccglpiUploadedFile::canSeeEveryUpload()) {
            return;
        }

        $query = trim((string) ($_GET['list_user_search'] ?? ''));
        if ($query === '') {
            return;
        }

        $matches = PluginUploadsccglpiUploadedFile::searchUsers($query, self::USER_SEARCH_LIMIT);
        if (count($matches) === 1) {
            PluginUploadsccglpiUploadedFile::setListScope((string) $matches[0]);
            return;
        }

        $this->userSearch  = $query;
        $this->userMatches = $matches;
    }

    private function renderUserMatches(string $self): void
    {
        if ($this->userSearch === '') {
            return;
        }

        if ($this->userMatches === []) {
            echo "<div class='alert alert-warning py-2'>" . htmlescape(sprintf(
                self::t('No user matches "%s".'),
                $this->userSearch
            )) . "</div>";
            return;
        }

        $shown = array_slice($this->userMatches, 0, self::USER_SEARCH_LIMIT);

        echo "<div class='alert alert-info py-2'><span class='me-2'>"
            . htmlescape(self::t('Pick a user:')) . "</span>";
        foreach ($shown as $userId) {
            echo "<a class='btn btn-sm btn-outline-primary me-1 mb-1' href='"
                . htmlescape($self . '?list_scope=' . $userId) . "'>"
                . htmlescape(getUserName($userId)) . "</a>";
        }
        if (count($this->userMatches) > self::USER_SEARCH_LIMIT) {
            echo "<div class='small text-muted'>" . htmlescape(sprintf(
                self::t('Showing the first %d matches - narrow your search.'),
                self::USER_SEARCH_LIMIT
            )) . "</div>";
        }
        echo "</div>";
    }

    private function renderList(string $self): void
    {
        if (isset($_GET['list_scope'])) {
            PluginUploadsccglpiUploadedFile::setListScope((string) $_GET['list_scope']);
        }
        $this->searchUsers();
        $limit = self::listLimit();
        $pairs = PluginUploadsccglpiUploadedFile::findVisiblePairs($limit);
        $total = PluginUploadsccglpiUploadedFile::countVisiblePairs();

        $title    = htmlescape(self::t('Upload log'));
        $everyone = PluginUploadsccglpiUploadedFile::listsEveryUpload();
        $listed   = PluginUploadsccglpiUploadedFile::listedUserId();
        $scope    = htmlescape(match (true) {
            $everyone                                => self::t('Files uploaded by every user are listed.'),
            $listed === (int) Session::getLoginUserID() => self::t('Only the files you uploaded yourself are listed.'),
            default                                  => sprintf(self::t('Files uploaded by %s are listed.'), getUserName((int) $listed)),
        });

        echo "<div class='card m-4'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>";
        echo "<div><h3 class='card-title mb-0'><i class='ti ti-files me-2'></i>{$title}</h3>";
        echo "<div class='text-muted small'>{$scope}</div></div>";
        echo "<div class='d-flex align-items-center gap-3'>";
        $this->renderLimitSelect($self, $limit);
        echo "<span class='badge bg-secondary'>" . htmlescape((string) $total) . "</span>";
        echo "</div>";
        echo "</div>";
        echo "<div class='card-body'>";
        $this->renderUserMatches($self);

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
        $showUser = PluginUploadsccglpiUploadedFile::listsEveryUpload();
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
            $confirm = htmlescape(json_encode(self::t('Remove this pair from the log? The files on disk are not deleted.')));
            echo "<button type='submit' name='delete' value='{$firstId}' onclick='return confirm({$confirm})' "
                . "class='btn btn-sm btn-ghost-danger' title='" . htmlescape(__('Delete')) . "'>"
                . "<i class='ti ti-trash'></i></button>";
        }
        echo "</td>";
        echo "</tr>";
    }
}
