<?php

class PluginUploadsccglpiArchive
{
    public const MAX_READ_BYTES = 64 * 1024 * 1024;

    private const TAR_MAGIC_OFFSET = 257;
    private const TAR_TYPE_OFFSET  = 156;
    private const TAR_BLOCK        = 512;

    public static function findMember(string $path, string $suffix, int $maxBytes = self::MAX_READ_BYTES): ?string
    {
        $handle = @gzopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $header = self::readExactly($handle, self::TAR_BLOCK);

            if ($header === null || !self::looksLikeTar($header)) {
                @gzrewind($handle);
                return self::readStream($handle, $maxBytes);
            }

            return self::walkTar($handle, $header, $suffix, $maxBytes);
        } finally {
            @gzclose($handle);
        }
    }

    private static function looksLikeTar(string $header): bool
    {
        return str_starts_with(substr($header, self::TAR_MAGIC_OFFSET, 5), 'ustar');
    }

    private static function walkTar($handle, string $header, string $suffix, int $maxBytes): ?string
    {
        $suffix = mb_strtolower($suffix);
        $read   = 0;

        while ($header !== null && trim(substr($header, 0, 100), "\0") !== '') {
            $name = trim(substr($header, 0, 100), " \0");
            $size = (int) octdec(trim(substr($header, 124, 12), " \0") ?: '0');

            if ($size < 0 || $read + $size > $maxBytes) {
                return null;
            }

            $type = substr($header, self::TAR_TYPE_OFFSET, 1);
            if ($size > 0 && self::isWantedMember($name, $type, $suffix)) {
                return self::readExactly($handle, $size);
            }

            $skip = (int) (ceil($size / self::TAR_BLOCK) * self::TAR_BLOCK);
            if ($skip > 0 && self::readExactly($handle, $skip) === null) {
                return null;
            }
            $read += $skip;

            $header = self::readExactly($handle, self::TAR_BLOCK);
        }

        return null;
    }

    private static function isWantedMember(string $name, string $type, string $suffix): bool
    {
        if ($type !== '0' && $type !== "\0") {
            return false;
        }

        return str_ends_with(mb_strtolower(basename($name)), $suffix);
    }

    private static function readExactly($handle, int $length): ?string
    {
        $out = '';
        while (strlen($out) < $length) {
            $chunk = @gzread($handle, min(262144, $length - strlen($out)));
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $out .= $chunk;
        }
        return $out;
    }

    private static function readStream($handle, int $maxBytes): ?string
    {
        $out = '';
        while (!gzeof($handle)) {
            $chunk = @gzread($handle, 262144);
            if ($chunk === false) {
                return null;
            }
            $out .= $chunk;
            if (strlen($out) > $maxBytes) {
                return null;
            }
        }
        return $out !== '' ? $out : null;
    }
}
