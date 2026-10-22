<?php
class PluginUploadsccglpiUploadPanel
{
    private const LIST_LIMITS        = [10, 25, 50, 100, 200, 500];
    private const LIST_LIMIT_DEFAULT = 100;
    private const LIST_LIMIT_KEY     = 'plugin_uploadsccglpi_list_limit';
    private const USER_SEARCH_LIMIT  = 20;

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

    private static function reportRow(string $host, array $files, bool $ok, string $reason, string $group = ''): array
    {
        return [
            'host'   => $host,
            'files'  => $files,
            'ok'     => $ok,
            'reason' => $reason,
            'group'  => $group !== '' ? $group : $reason,
        ];
    }

    private static function exportUrl(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/uploadsccglpi/front/upload.export.php';
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
                throw new \Glpi\Exception\Http\AccessDeniedHttpException();
            }
            $this->handleUpload();
            Html::redirect($self);
        }

        if (isset($_POST['delete'])) {
            if (!PluginUploadsccglpiUploadedFile::canPurge()) {
                throw new \Glpi\Exception\Http\AccessDeniedHttpException();
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
        if ($this->countHosts($uploads) > $limit) {
            Session::addMessageAfterRedirect(
                sprintf(self::t('Too many hosts in one upload (limit: %d).'), $limit),
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
        $hosts  = $this->groupByHost($uploads, $report);

        $stored = 0;
        foreach ($hosts as $host) {
            $files = [$host['archive']['name']];

            $reason = $this->check->check((string) $host['archive']['upload']['tmp_name'], $host['stem']);
            if ($reason !== '') {
                $report[] = self::reportRow($host['stem'], $files, false, $reason, $this->check->lastGroup());
                continue;
            }

            $failure = $this->storeArchive($host);
            $report[] = self::reportRow($host['stem'], $files, $failure === '', $failure);
            if ($failure === '') {
                $stored++;
            }
        }

        PluginUploadsccglpiLastReport::save((int) Session::getLoginUserID(), $report);

        if ($stored > 0) {
            Session::addMessageAfterRedirect(
                sprintf(self::t('Hosts uploaded: %d'), $stored)
            );
        }
    }

    private function countHosts(array $uploads): int
    {
        $signal = $this->config->signalExtension();
        $known  = $this->config->allowedExtensions();

        return count(array_filter($uploads, static fn(array $u) =>
            PluginUploadsccglpiStorage::splitName((string) ($u['name'] ?? ''), $known)['extension'] !== $signal));
    }

    private function groupByHost(array $uploads, array &$report): array
    {
        $archives = $this->config->archiveExtensions();
        $signal   = $this->config->signalExtension();
        $known    = $this->config->allowedExtensions();

        $hosts      = [];
        $signalOnly = [];
        foreach ($uploads as $upload) {
            $name = (string) ($upload['name'] ?? '');

            ['stem' => $stem, 'extension' => $extension] =
                PluginUploadsccglpiStorage::splitName($name, $known);
            $key = mb_strtolower($stem);

            if ($extension === $signal) {
                $signalOnly[$key] ??= ['stem' => $stem, 'name' => $name];
                continue;
            }

            $rejection = $this->storage->validate($upload);
            if ($rejection !== '') {
                $report[] = self::reportRow($stem, [$name], false, $rejection);
                $hosts[$key] = false;
                continue;
            }

            if (!in_array($extension, $archives, true)) {
                $report[] = self::reportRow($name, [$name], false, self::t('Unrecognised extension.'));
                continue;
            }

            if (isset($hosts[$key])) {
                if ($hosts[$key] !== false) {
                    $report[] = self::reportRow($stem, [$name], false, self::t('Two archives for one host.'));
                }
                continue;
            }

            $hosts[$key] = ['stem' => $stem, 'archive' => ['upload' => $upload, 'extension' => $extension, 'name' => $name]];
        }

        foreach ($signalOnly as $key => $orphan) {
            if (!isset($hosts[$key])) {
                $report[] = self::reportRow(
                    $orphan['stem'],
                    [$orphan['name']],
                    false,
                    self::t('The archive of this host is missing.')
                );
            }
        }

        return array_values(array_filter($hosts));
    }

    private function storeArchive(array $host): string
    {
        $stem   = $this->storage->freeStemForPair($host['stem'], [$host['archive']['extension']]);
        $result = $this->storage->store($host['archive']['upload'], $stem . '.' . $host['archive']['extension']);

        if (!$result['ok']) {
            return $result['error'];
        }

        $result['pair_key'] = $stem . '#' . uniqid();
        if ($this->recordUpload($result) === false) {
            $this->storage->removeStored($result);
            return self::t('The file could not be registered in the database.');
        }

        return '';
    }

    private function recordUpload(array $result): int|false
    {
        $item = new PluginUploadsccglpiUploadedFile();

        try {
            return $item->add([
                'entities_id'   => (int) ($_SESSION['glpiactive_entity'] ?? 0),
                'is_recursive'  => 0,
                'name'          => mb_substr((string) $result['name'], 0, 255),
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
            self::t('You can select up to %d hosts at once (archives: %s). Optionally, you can also add a %s file for each host.'),
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
        echo "<div class='d-flex gap-2'>";
        $this->renderHelpButton();
        $this->renderSettingsButton();
        echo "</div>";
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
            echo plugin_uploadsccglpi_csrf_field();
            echo "</form>";
        }

        echo "</div></div>";
    }

    private function renderHelpButton(): void
    {
        global $CFG_GLPI;

        $id = $this->config->helpArticleId();
        if ($id <= 0) {
            return;
        }

        $url = ($CFG_GLPI['root_doc'] ?? '') . '/front/knowbaseitem.form.php?id=' . $id;
        echo "<a href='" . htmlescape($url) . "' target='_blank' rel='noopener' class='btn btn-outline-primary btn-sm'>"
            . "<i class='ti ti-book me-1'></i>" . htmlescape(self::t('How to upload a file?')) . "</a>";
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
        $state = PluginUploadsccglpiLastReport::load((int) Session::getLoginUserID());
        if ($state === null) {
            return;
        }
        $rows = $state['rows'];

        $loadedRows = array_values(array_filter($rows, static fn(array $r) => $r['ok']));
        $failedRows = array_values(array_filter($rows, static fn(array $r) => !$r['ok']));

        $loadedBadge = "<span class='badge bg-green-lt'>"
            . htmlescape(sprintf(self::t('loaded: %d'), count($loadedRows))) . "</span>";
        $failedBadge = $failedRows === [] ? '' : "<span class='badge bg-red-lt'>"
            . htmlescape(sprintf(self::t('refused: %d'), count($failedRows))) . "</span>";

        $buttons = $this->exportButton(self::t('Download the full log of the last upload (.txt)'));

        $title = htmlescape(self::t('Result of the last upload'));

        echo "<div class='card m-4'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>";
        echo "<div><h3 class='card-title mb-0'><i class='ti ti-clipboard-check me-2'></i>{$title}</h3>";
        if ($state['date'] !== '') {
            echo "<div class='text-muted small'>" . htmlescape(Html::convDateTime($state['date'])) . "</div>";
        }
        echo "</div>";
        echo "<div class='d-flex gap-2 flex-wrap align-items-center'>{$loadedBadge}{$failedBadge}{$buttons}</div>";
        echo "</div>";
        echo "<div class='card-body'>";

        if ($failedRows !== []) {
            $groups = [];
            foreach ($failedRows as $row) {
                $groups[(string) ($row['group'] ?? $row['reason'])][] = $row;
            }
            uasort($groups, static fn(array $a, array $b) => count($b) <=> count($a));

            echo "<h4 class='text-danger'><i class='ti ti-x me-1'></i>"
                . htmlescape(self::t('Not loaded')) . "</h4>";
            foreach ($groups as $group => $groupRows) {
                $heading = htmlescape($group !== '' ? $group : self::t('Unknown reason.'))
                    . " <span class='badge bg-red-lt ms-1'>" . count($groupRows) . "</span>";
                echo "<details class='mb-2'" . (count($groups) === 1 ? ' open' : '') . ">";
                echo "<summary class='fw-bold'>{$heading}</summary>";
                echo $this->hostList($groupRows, true);
                echo "</details>";
            }
        }

        if ($loadedRows !== []) {
            $heading = htmlescape(self::t('Loaded'))
                . " <span class='badge bg-green-lt ms-1'>" . count($loadedRows) . "</span>";
            echo "<details class='mt-3'>";
            echo "<summary class='fw-bold text-success'><i class='ti ti-check me-1'></i>{$heading}</summary>";
            echo $this->hostList($loadedRows);
            echo "</details>";
        }

        echo "</div></div>";
    }

    private function exportButton(string $label): string
    {
        return "<a class='btn btn-sm btn-outline-secondary text-nowrap' href='"
            . htmlescape(self::exportUrl()) . "'><i class='ti ti-download me-1'></i>"
            . htmlescape($label) . "</a>";
    }

    private function hostList(array $rows, bool $withDetail = false): string
    {
        $html = "<ul class='list-unstyled mb-0 mt-2 ms-3' style='max-height:320px;overflow:auto'>";
        foreach ($rows as $row) {
            $detail = '';
            if ($withDetail && (string) $row['reason'] !== (string) ($row['group'] ?? $row['reason'])) {
                $detail = " <span class='text-muted small ms-2'>" . htmlescape((string) $row['reason']) . "</span>";
            }
            $html .= "<li>" . htmlescape((string) $row['host']) . $detail . "</li>";
        }

        return $html . "</ul>";
    }

    public static function exportReport(): void
    {
        $state = PluginUploadsccglpiLastReport::load((int) Session::getLoginUserID());
        $rows  = $state['rows'] ?? [];

        $clean = static fn(string $v): string => trim(preg_replace('/[\t\r\n]+/', ' ', $v));

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode("\t", [
                $clean((string) $row['host']),
                $row['ok'] ? 'OK' : 'ERROR',
                $clean((string) $row['reason']),
            ]);
        }

        header('Content-Type: text/plain; charset=utf-8');
        $uploaded = strtotime((string) ($state['date'] ?? ''));
        $stamp    = date('Ymd_His', $uploaded !== false ? $uploaded : time());
        header('Content-Disposition: attachment; filename="upload_log_' . $stamp . '.txt"');
        header('X-Content-Type-Options: nosniff');
        echo implode("\r\n", $lines) . ($lines === [] ? '' : "\r\n");
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
        $range = PluginUploadsccglpiUploadedFile::dateRange();
        echo "<span class='d-flex align-items-center gap-2 text-nowrap'>";
        echo "<label class='form-label mb-0 text-muted small' for='uploadsccglpi_list_from'>"
            . htmlescape(self::t('From')) . "</label>";
        echo "<input type='date' id='uploadsccglpi_list_from' name='list_from' class='form-control form-control-sm w-auto' "
            . "value='" . htmlescape($range['from']) . "'></span>";
        echo "<span class='d-flex align-items-center gap-2 text-nowrap'>";
        echo "<label class='form-label mb-0 text-muted small' for='uploadsccglpi_list_to'>"
            . htmlescape(self::t('To')) . "</label>";
        echo "<input type='date' id='uploadsccglpi_list_to' name='list_to' class='form-control form-control-sm w-auto' "
            . "value='" . htmlescape($range['to']) . "'></span>";
        echo "<button type='submit' class='btn btn-sm btn-outline-primary'><i class='ti ti-filter me-1'></i>"
            . htmlescape(self::t('Filter')) . "</button>";
        if ($range['from'] !== '' || $range['to'] !== '') {
            echo "<a href='" . htmlescape($self . '?list_from=&list_to=') . "' class='btn btn-sm btn-outline-secondary'>"
                . htmlescape(self::t('Clear dates')) . "</a>";
        }
        echo "<span class='d-flex align-items-center gap-2 text-nowrap'>";
        echo "<label class='form-label mb-0 text-muted small' for='uploadsccglpi_list_limit'>{$label}</label>";
        echo "<select id='uploadsccglpi_list_limit' name='list_limit' class='form-select form-select-sm w-auto' "
            . "onchange=\"if (this.form.list_user_search) { this.form.list_user_search.value = ''; } this.form.submit()\">";
        foreach (self::LIST_LIMITS as $option) {
            echo "<option value='{$option}'" . ($option === $limit ? ' selected' : '') . ">{$option}</option>";
        }
        echo "</select></span><noscript><button type='submit' class='btn btn-sm btn-outline-secondary'>OK</button></noscript>";
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
        if (isset($_GET['list_from']) || isset($_GET['list_to'])) {
            PluginUploadsccglpiUploadedFile::setDateRange(
                (string) ($_GET['list_from'] ?? ''),
                (string) ($_GET['list_to'] ?? '')
            );
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
        echo "<div><h3 class='card-title mb-0'><i class='ti ti-files me-2'></i>{$title}"
            . " <span class='badge bg-blue-lt fs-6 ms-1'>" . htmlescape((string) $total) . "</span></h3>";
        echo "<div class='text-muted small'>{$scope}</div></div>";
        echo "<div class='d-flex align-items-center gap-3'>";
        $this->renderLimitSelect($self, $limit);
        echo "</div>";
        echo "</div>";
        echo "<div class='card-body'>";
        $this->renderUserMatches($self);

        if ($pairs === []) {
            $range = PluginUploadsccglpiUploadedFile::dateRange();
            $filtered = $range['from'] !== '' || $range['to'] !== '';
            echo "<p class='text-muted mb-0'>"
                . htmlescape($filtered ? self::t('No entries in the selected date range.') : self::t('No file has been uploaded yet.'))
                . "</p>";
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
        echo plugin_uploadsccglpi_csrf_field();
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

        $label = explode('#', (string) $pair['key'])[0];

        echo "<tr>";
        echo "<td><i class='ti ti-device-desktop me-1 text-muted'></i>"
            . htmlescape($label) . "</td>";
        echo "<td>" . implode('<br>', $links) . "</td>";
        echo "<td class='text-nowrap'>" . htmlescape(PluginUploadsccglpiStorage::formatSize($size)) . "</td>";
        if ($showUser) {
            echo "<td>" . htmlescape(getUserName((int) $pair['users_id'])) . "</td>";
        }
        echo "<td class='text-nowrap'>" . htmlescape(Html::convDateTime((string) $pair['date'])) . "</td>";
        echo "<td class='text-end text-nowrap'>";
        if ($canPurge) {
            $confirm = htmlescape(json_encode(self::t('Remove this entry from the log? The files on disk are not deleted.')));
            echo "<button type='submit' name='delete' value='{$firstId}' onclick='return confirm({$confirm})' "
                . "class='btn btn-sm btn-ghost-danger' title='" . htmlescape(__('Delete')) . "'>"
                . "<i class='ti ti-trash'></i></button>";
        }
        echo "</td>";
        echo "</tr>";
    }
}
