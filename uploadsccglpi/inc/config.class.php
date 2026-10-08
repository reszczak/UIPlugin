<?php
class PluginUploadsccglpiConfig
{
    public const CONTEXT = 'plugin:uploadsccglpi';

    public const DEFAULTS = [
        'archive_extensions' => 'tar.gz,gz',
        'signal_extension'   => 'signal',
        'max_size_mb'        => '64',
        'max_files'          => '200',
        'version_max_gap'    => '3',
        'max_age_days'       => '7',
    ];

    public const OBSOLETE_KEYS = ['storage_dir', 'allowed_extensions', 'newest_version'];

    private array $values;

    private ?array $agentVersions = null;

    public function __construct(?array $values = null)
    {
        $this->values = $values ?? Config::getConfigurationValues(self::CONTEXT);
    }

    private function value(string $key): string
    {
        return trim((string) ($this->values[$key] ?? self::DEFAULTS[$key] ?? ''));
    }

    public function storageDir(): string
    {
        return GLPI_DOC_DIR . '/_plugins/uploadsccglpi';
    }

    public function archiveExtensions(): array
    {
        $out = self::parseExtensions($this->value('archive_extensions'));
        return $out !== [] ? $out : self::parseExtensions(self::DEFAULTS['archive_extensions']);
    }

    public function signalExtension(): string
    {
        $out = self::parseExtensions($this->value('signal_extension'));
        return $out[0] ?? self::DEFAULTS['signal_extension'];
    }

    public function allowedExtensions(): array
    {
        $all = array_values(array_unique(array_merge(
            $this->archiveExtensions(),
            [$this->signalExtension()]
        )));

        usort($all, static fn(string $a, string $b) => strlen($b) <=> strlen($a));

        return $all;
    }

    private static function parseExtensions(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $ext) {
            $ext = mb_strtolower(trim($ext, " \t\n\r\0\x0B."));

            if ($ext !== '' && preg_match('/^[a-z0-9]{1,16}(\.[a-z0-9]{1,16}){0,3}$/', $ext) === 1) {
                $out[$ext] = $ext;
            }
        }
        return array_values($out);
    }

    public function maxSizeMb(): int
    {
        return max(1, (int) $this->value('max_size_mb'));
    }

    public function maxSizeBytes(): int
    {
        return $this->maxSizeMb() * 1024 * 1024;
    }

    public function maxFiles(): int
    {
        return max(1, (int) $this->value('max_files'));
    }

    public function phpMaxFiles(): int
    {
        return (int) ini_get('max_file_uploads');
    }

    public static function iniBytes(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }
        $value = (int) $raw;
        return match (mb_strtolower(substr($raw, -1))) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }

    public function agentVersions(): array
    {
        return $this->agentVersions ??= PluginUploadsccglpiAgentVersions::sync();
    }

    public function newestVersionFor(string $os): ?string
    {
        return $this->agentVersions()['versions'][$os]['version'] ?? null;
    }

    public function versionMaxGap(): int
    {
        return max(1, (int) $this->value('version_max_gap'));
    }

    public function maxAgeDays(): int
    {
        return max(1, (int) $this->value('max_age_days'));
    }

    public static function isVersion(string $value): bool
    {
        return preg_match('/^\d{1,6}(\.\d{1,6}){0,4}$/', trim($value)) === 1;
    }

    public const LIMITS = [
        'max_size_mb'     => [1, 4096],
        'max_files'       => [1, 10000],
        'max_age_days'    => [1, 3650],
        'version_max_gap' => [1, 999],
    ];

    public function save(array $input): array
    {
        $archive = self::parseExtensions((string) ($input['archive_extensions'] ?? ''));
        $signal  = self::parseExtensions((string) ($input['signal_extension'] ?? ''));

        $adjusted = [];
        if ($archive === []) {
            $adjusted[] = 'archive_extensions';
        }
        if ($signal === []) {
            $adjusted[] = 'signal_extension';
        }

        $values = [
            'archive_extensions' => implode(',', $archive !== [] ? $archive : $this->archiveExtensions()),
            'signal_extension'   => $signal[0] ?? $this->signalExtension(),
        ];

        foreach (self::LIMITS as $key => [$min, $max]) {
            $raw = trim((string) ($input[$key] ?? ''));
            if (preg_match('/^\d{1,9}$/', $raw) !== 1) {
                $adjusted[] = $key;
                $values[$key] = $this->value($key);
                continue;
            }

            $number = (int) $raw;
            if ($number < $min || $number > $max) {
                $adjusted[] = $key;
            }
            $values[$key] = (string) min($max, max($min, $number));
        }

        Config::setConfigurationValues(self::CONTEXT, $values);
        $this->values = array_merge($this->values, $values);

        return $adjusted;
    }
}
