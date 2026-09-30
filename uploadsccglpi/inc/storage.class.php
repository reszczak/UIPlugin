<?php
class PluginUploadsccglpiStorage
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

    public function rootDir(): string
    {
        return $this->config->storageDir();
    }

    public function ensureRootDir(): bool
    {
        $dir = $this->rootDir();
        if (!is_dir($dir) && !@mkdir($dir, 0o770, true) && !is_dir($dir)) {
            return false;
        }
        return is_writable($dir);
    }

    public function absolutePath(string $relative): string
    {
        return $this->rootDir() . '/' . ltrim($relative, '/');
    }

    public function validate(array $upload): string
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return self::uploadErrorMessage($error);
        }

        $allowed = $this->config->allowedExtensions();
        if ($this->extensionOf((string) ($upload['name'] ?? '')) === '') {
            return sprintf(
                self::t('Only these extensions are accepted: %s.'),
                implode(', ', array_map(static fn(string $e) => '.' . $e, $allowed))
            );
        }

        $size = (int) ($upload['size'] ?? 0);
        if ($size <= 0) {
            return self::t('The file is empty.');
        }
        if ($size > $this->config->maxSizeBytes()) {
            return sprintf(
                self::t('The file exceeds the maximum allowed size (%s).'),
                self::formatSize($this->config->maxSizeBytes())
            );
        }

        return '';
    }

    public function store(array $upload, string $targetName): array
    {
        $original = (string) ($upload['name'] ?? '');
        $tmpName  = (string) ($upload['tmp_name'] ?? '');

        $rejection = $this->validate($upload);
        if ($rejection !== '') {
            return self::failure($original, $rejection);
        }
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return self::failure($original, self::t('The file was not received by the server.'));
        }

        $extension = $this->extensionOf($original);

        if (!$this->ensureRootDir()) {
            return self::failure(
                $original,
                self::t('The storage directory is not writable - contact your GLPI administrator.')
            );
        }

        $filename = $targetName;
        $target   = $this->absolutePath($filename);

        $sha256 = hash_file('sha256', $tmpName);
        if (!@move_uploaded_file($tmpName, $target)) {
            return self::failure(
                $original,
                self::t('The storage directory is not writable - contact your GLPI administrator.')
            );
        }
        @chmod($target, 0o640);

        return [
            'ok'        => true,
            'error'     => '',
            'name'      => $original,
            'filename'  => $filename,
            'filepath'  => $filename,
            'storage_dir' => $this->rootDir(),
            'extension' => $extension,
            'filesize'  => (int) filesize($target),
            'sha256'    => (string) $sha256,
            'mime'      => self::mimeOf($target, $extension),
        ];
    }

    public function resolve(array $fields): array
    {
        $relative = self::sanitizeName((string) ($fields['filepath'] ?? ''));
        if ($relative === '') {
            return ['path' => '', 'root' => ''];
        }

        $recorded = rtrim((string) ($fields['storage_dir'] ?? ''), '/');
        if ($recorded !== '' && is_file($recorded . '/' . $relative)) {
            return ['path' => $recorded . '/' . $relative, 'root' => $recorded];
        }

        return ['path' => $this->absolutePath($relative), 'root' => $this->rootDir()];
    }

    public function removeStored(array $fields): void
    {
        ['path' => $path, 'root' => $root] = $this->resolve($fields);
        if ($path === '' || $root === '') {
            return;
        }

        $realPath = realpath($path);
        $realRoot = realpath($root);
        if ($realPath === false || $realRoot === false) {
            return;
        }
        if (!str_starts_with($realPath, rtrim($realRoot, '/') . '/')) {
            return;
        }

        @unlink($realPath);
    }

    public static function sanitizeName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._@+-]/', '_', $name) ?? '';
        $name = ltrim($name, '.');
        $name = preg_replace('/_{2,}/', '_', $name) ?? '';

        if (mb_strlen($name) > 200) {
            $extension = self::trailingSegment($name);
            $stem      = mb_substr($name, 0, 200 - mb_strlen($extension) - 1);
            $name      = $extension !== '' ? $stem . '.' . $extension : $stem;
        }

        return $name;
    }

    public static function splitName(string $name, array $extensions): array
    {
        $lower = mb_strtolower($name);

        usort($extensions, static fn(string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($extensions as $extension) {
            $suffix = '.' . mb_strtolower($extension);
            if ($suffix !== '.' && str_ends_with($lower, $suffix) && mb_strlen($lower) > mb_strlen($suffix)) {
                return [
                    'stem'      => substr($name, 0, -mb_strlen($suffix)),
                    'extension' => mb_strtolower($extension),
                ];
            }
        }

        return ['stem' => $name, 'extension' => ''];
    }

    public function extensionOf(string $name): string
    {
        return self::splitName($name, $this->config->allowedExtensions())['extension'];
    }

    private static function trailingSegment(string $name): string
    {
        $position = strrpos($name, '.');
        if ($position === false || $position === mb_strlen($name) - 1) {
            return '';
        }
        return mb_strtolower(substr($name, $position + 1));
    }

    public function freeStemForPair(string $stem, array $extensions): string
    {
        $stem = self::sanitizeName($stem);
        if ($stem === '') {
            $stem = 'upload';
        }

        $taken = function (string $candidate) use ($extensions): bool {
            foreach ($extensions as $extension) {
                if (file_exists($this->absolutePath($candidate . '.' . $extension))) {
                    return true;
                }
            }
            return false;
        };

        if (!$taken($stem)) {
            return $stem;
        }

        for ($i = 1; $i < 1000; $i++) {
            if (!$taken("{$stem}-{$i}")) {
                return "{$stem}-{$i}";
            }
        }

        return $stem . '-' . uniqid();
    }

    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $value = (float) max(0, $bytes);
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }
        return ($index === 0 ? (string) (int) $value : number_format($value, 2, '.', ' ')) . ' ' . $units[$index];
    }

    private static function mimeOf(string $path, string $extension): string
    {
        if ($extension === 'gz') {
            return 'application/gzip';
        }
        $mime = function_exists('mime_content_type') ? @mime_content_type($path) : false;
        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    private static function failure(string $name, string $message): array
    {
        return ['ok' => false, 'error' => $message, 'name' => $name];
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                self::t('The file exceeds the maximum allowed size (%s).'),
                self::formatSize(PluginUploadsccglpiConfig::iniBytes((string) ini_get('upload_max_filesize')))
            ),
            UPLOAD_ERR_PARTIAL  => self::t('The file was only partially uploaded.'),
            UPLOAD_ERR_NO_FILE  => self::t('No file was selected.'),
            default             => self::t('The file was not received by the server.'),
        };
    }
}
