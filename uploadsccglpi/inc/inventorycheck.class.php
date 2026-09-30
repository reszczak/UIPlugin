<?php

class PluginUploadsccglpiInventoryCheck
{
    public const MEMBER_SUFFIX = '.cur';

    private const DATE_PATTERN     = '/var:general::date\s*:\s*(.+)/i';
    private const VERSION_PATTERN  = '/fix:general::scc\.conf:version[ \t]+([^\r\n]+)/i';

    private const CATEGORY_PATTERN = '/fix:general::scc\.conf:os_category[ \t]*([^\r\n]+)/i';

    private PluginUploadsccglpiConfig $config;

    public function __construct(?PluginUploadsccglpiConfig $config = null)
    {
        $this->config = $config ?? new PluginUploadsccglpiConfig();
    }

    private static function t(string $s): string
    {
        return __($s, 'uploadsccglpi');
    }

    public function check(string $path): string
    {
        $content = PluginUploadsccglpiArchive::findMember($path, self::MEMBER_SUFFIX);
        if ($content === null) {
            return sprintf(self::t('No "%s" file could be read from the archive.'), self::MEMBER_SUFFIX);
        }

        $reason = $this->checkDate($content);
        if ($reason !== '') {
            return $reason;
        }

        return $this->checkVersion($content);
    }

    private function checkDate(string $content): string
    {
        if (preg_match(self::DATE_PATTERN, $content, $m) !== 1) {
            return self::t('The inventory date (var:general::date) is missing from the .cur file.');
        }

        $raw       = trim($m[1]);
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return sprintf(self::t('The inventory date "%s" could not be read.'), $raw);
        }

        $days = $this->config->maxAgeDays();
        if ($timestamp < strtotime("today -{$days} days")) {
            return sprintf(
                self::t('The inventory is from %s and is older than the allowed %d days.'),
                date('Y-m-d H:i', $timestamp),
                $days
            );
        }

        return '';
    }

    private function checkVersion(string $content): string
    {
        if (preg_match(self::VERSION_PATTERN, $content, $m) !== 1) {
            return self::t('The scc.conf version (fix:general::scc.conf:version) is missing from the .cur file.');
        }

        $found = trim($m[1]);

        if (preg_match(self::CATEGORY_PATTERN, $content, $c) !== 1) {
            return self::t('The system category (fix:general::scc.conf:os_category) is missing from the .cur file.');
        }

        $category = trim($c[1]);
        $platform = PluginUploadsccglpiAgentVersions::platformFor($category);
        if ($platform === null) {
            return sprintf(
                self::t('Unknown system category "%s" - expected one of: %s.'),
                $category,
                implode(', ', PluginUploadsccglpiAgentVersions::knownCategories())
            );
        }

        $newest = $this->config->newestVersionFor($platform);
        if ($newest === null) {
            return sprintf(
                self::t('No newest agent version is known for %s - the "%s" knowledge base article could not be read. Contact your GLPI administrator.'),
                $category,
                PluginUploadsccglpiAgentVersions::KB_TITLE
            );
        }

        if (!PluginUploadsccglpiConfig::isVersion($found)) {
            return sprintf(self::t('The scc.conf version "%s" could not be read.'), $found);
        }

        $gap = self::versionGap($newest, $found);
        if ($gap === null) {
            return sprintf(
                self::t('The scc.conf version %s is too far behind %s, the current one for %s.'),
                $found,
                $newest,
                $category
            );
        }

        $max = $this->config->versionMaxGap();
        if ($gap >= $max) {
            return sprintf(
                self::t('The scc.conf version %s is %d behind %s, the current one for %s; at most %d is allowed.'),
                $found,
                $gap,
                $newest,
                $category,
                $max - 1
            );
        }

        return '';
    }

    public static function versionGap(string $newest, string $found): ?int
    {
        if (version_compare($found, $newest, '>=')) {
            return 0;
        }

        $newestParts = explode('.', $newest);
        $foundParts  = explode('.', $found);

        if (count($newestParts) !== count($foundParts)) {
            return null;
        }
        if (array_slice($newestParts, 0, -1) !== array_slice($foundParts, 0, -1)) {
            return null;
        }

        return (int) end($newestParts) - (int) end($foundParts);
    }
}
