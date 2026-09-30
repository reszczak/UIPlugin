<?php

class PluginUploadsccglpiAgentVersions
{
    public const KB_TITLE = 'Agent SCC - wersja';

    public const OS_UNIX    = 'unix';
    public const OS_WINDOWS = 'windows';

    public const SOURCE_ARTICLE = 'article';
    public const SOURCE_STORED  = 'stored';

    public const ISSUE_NONE       = '';
    public const ISSUE_MISSING    = 'missing';
    public const ISSUE_RENAMED    = 'renamed';
    public const ISSUE_UNPARSED   = 'unparsed';
    public const ISSUE_INCOMPLETE = 'incomplete';
    public const ISSUE_ERROR      = 'error';

    public const CATEGORIES = [
        'linux'   => self::OS_UNIX,
        'hpux'    => self::OS_UNIX,
        'sunos'   => self::OS_UNIX,
        'aix'     => self::OS_UNIX,
        'windows' => self::OS_WINDOWS,
    ];

    private const PATTERNS = [
        self::OS_UNIX    => '/(?:linux|unix)/i',
        self::OS_WINDOWS => '/windows/i',
    ];

    public static function platforms(): array
    {
        return array_keys(self::PATTERNS);
    }

    public static function platformName(string $os): string
    {
        return match ($os) {
            self::OS_WINDOWS => __('Windows', 'uploadsccglpi'),
            default          => __('Linux / Unix', 'uploadsccglpi'),
        };
    }

    public static function issueMessage(array $state): string
    {
        $t = static fn(string $s) => __($s, 'uploadsccglpi');

        return match ($state['issue']) {
            self::ISSUE_MISSING    => sprintf($t('The knowledge base article "%s" was not found. The last saved versions stay in force until it is back.'), self::KB_TITLE),
            self::ISSUE_RENAMED    => sprintf($t('The knowledge base article was renamed to "%s". Versions are still read from it, but give it back the title "%s" so it is found by name.'), $state['article']['name'] ?? '', self::KB_TITLE),
            self::ISSUE_UNPARSED   => sprintf($t('No version could be read from the knowledge base article "%s" - its content has changed. The last saved versions stay in force.'), self::KB_TITLE),
            self::ISSUE_INCOMPLETE => sprintf($t('The knowledge base article "%s" does not state a version for every system. For those, the last saved version stays in force.'), self::KB_TITLE),
            self::ISSUE_ERROR      => sprintf($t('The knowledge base article "%s" could not be read because of a database error. The last saved versions stay in force.'), self::KB_TITLE),
            default                => '',
        };
    }

    public static function platformFor(string $category): ?string
    {
        return self::CATEGORIES[mb_strtolower(trim($category))] ?? null;
    }

    public static function knownCategories(): array
    {
        return ['Linux', 'Windows', 'HPUX', 'SunOS', 'AIX'];
    }

    public static function sync(): array
    {
        $state = ['issue' => self::ISSUE_NONE, 'article' => null, 'versions' => []];

        $stored = self::stored();

        try {
            [$article, $renamed] = self::findArticle($stored);
        } catch (Throwable $e) {
            trigger_error(
                'uploadsccglpi: could not read the "' . self::KB_TITLE . '" article - ' . $e->getMessage(),
                E_USER_WARNING
            );
            [$article, $renamed] = [null, false];
            $state['issue'] = self::ISSUE_ERROR;
        }

        $fromArticle = [];
        if ($article !== null) {
            $state['article'] = ['id' => $article['id'], 'name' => $article['name']];
            foreach (self::parse($article['answer']) as $os => $version) {
                if (PluginUploadsccglpiConfig::isVersion($version)) {
                    $fromArticle[$os] = $version;
                }
            }
        }

        foreach (self::platforms() as $os) {
            if (isset($fromArticle[$os])) {
                $row = self::remember($os, $fromArticle[$os], $article['id'], $stored[$os] ?? null);
                $state['versions'][$os] = [
                    'version'   => $fromArticle[$os],
                    'source'    => self::SOURCE_ARTICLE,
                    'date_mod'  => $row['date_mod'],
                    'date_sync' => $row['date_sync'],
                ];
            } elseif (isset($stored[$os])) {
                $state['versions'][$os] = [
                    'version'   => $stored[$os]['version'],
                    'source'    => self::SOURCE_STORED,
                    'date_mod'  => $stored[$os]['date_mod'],
                    'date_sync' => $stored[$os]['date_sync'],
                ];
            }
        }

        if ($state['issue'] === self::ISSUE_NONE) {
            $state['issue'] = match (true) {
                $article === null                                   => self::ISSUE_MISSING,
                $fromArticle === []                                 => self::ISSUE_UNPARSED,
                count($fromArticle) < count(self::platforms())      => self::ISSUE_INCOMPLETE,
                $renamed                                            => self::ISSUE_RENAMED,
                default                                             => self::ISSUE_NONE,
            };
        }

        return $state;
    }

    private static function findArticle(array $stored): array
    {
        global $DB;

        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'answer'],
            'FROM'   => 'glpi_knowbaseitems',
            'WHERE'  => ['name' => self::KB_TITLE],
            'ORDER'  => ['date_mod DESC', 'id DESC'],
            'LIMIT'  => 1,
        ]) as $row) {
            return [self::articleRow($row), false];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn(array $row) => (int) $row['knowbaseitems_id'],
            $stored
        ))));
        if ($ids === []) {
            return [null, false];
        }

        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'answer'],
            'FROM'   => 'glpi_knowbaseitems',
            'WHERE'  => ['id' => $ids],
            'ORDER'  => ['date_mod DESC', 'id DESC'],
            'LIMIT'  => 1,
        ]) as $row) {
            return [self::articleRow($row), true];
        }

        return [null, false];
    }

    private static function articleRow(array $row): array
    {
        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'answer' => (string) $row['answer']];
    }

    public static function stored(): array
    {
        global $DB;

        $out = [];

        try {
            if (!$DB->tableExists(PLUGIN_UPLOADSCCGLPI_VERSIONS_TABLE)) {
                return [];
            }
            foreach ($DB->request(['FROM' => PLUGIN_UPLOADSCCGLPI_VERSIONS_TABLE]) as $row) {
                if (PluginUploadsccglpiConfig::isVersion((string) $row['version'])) {
                    $out[(string) $row['platform']] = [
                        'version'          => (string) $row['version'],
                        'knowbaseitems_id' => (int) $row['knowbaseitems_id'],
                        'date_mod'         => $row['date_mod'],
                        'date_sync'        => $row['date_sync'],
                    ];
                }
            }
        } catch (Throwable $e) {
            trigger_error('uploadsccglpi: could not read the stored agent versions - ' . $e->getMessage(), E_USER_WARNING);
        }

        return $out;
    }

    private static function remember(string $os, string $version, int $articleId, ?array $row): array
    {
        global $DB;

        if ($row !== null && $row['version'] === $version && $row['knowbaseitems_id'] === $articleId) {
            return ['date_mod' => $row['date_mod'], 'date_sync' => $row['date_sync']];
        }

        $now     = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $dateMod = ($row !== null && $row['version'] === $version) ? $row['date_mod'] : $now;

        try {
            if (!$DB->tableExists(PLUGIN_UPLOADSCCGLPI_VERSIONS_TABLE)) {
                return ['date_mod' => null, 'date_sync' => null];
            }
            $DB->updateOrInsert(
                PLUGIN_UPLOADSCCGLPI_VERSIONS_TABLE,
                [
                    'version'          => $version,
                    'knowbaseitems_id' => $articleId,
                    'date_mod'         => $dateMod,
                    'date_sync'        => $now,
                ],
                ['platform' => $os]
            );
        } catch (Throwable $e) {
            trigger_error('uploadsccglpi: could not store the agent version - ' . $e->getMessage(), E_USER_WARNING);
        }

        return ['date_mod' => $dateMod, 'date_sync' => $now];
    }

    public static function parse(string $answer): array
    {
        $out = [];
        foreach (self::toLines($answer) as $line) {
            if (preg_match('/(\d+(?:\.\d+)+)/', $line, $m) !== 1) {
                continue;
            }
            $version = $m[1];

            foreach (self::PATTERNS as $os => $pattern) {
                if (!isset($out[$os]) && preg_match($pattern, $line) === 1) {
                    $out[$os] = $version;
                }
            }
        }

        return $out;
    }

    private static function toLines(string $answer): array
    {
        $text = preg_replace('#<\s*(br|/p|/div|/li|/tr|/h[1-6])\s*/?\s*>#i', "\n", $answer) ?? $answer;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
