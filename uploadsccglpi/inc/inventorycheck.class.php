<?php

class PluginUploadsccglpiInventoryCheck
{
    public const MEMBER_SUFFIX = '.cur';

    private const DATE_PATTERN     = '/var:general::date[ \t]*:[ \t]*(.+)/i';
    private const VERSION_PATTERN  = '/fix:general::scc\.conf:version[ \t]+([^\r\n]+)/i';

    private const CATEGORY_PATTERN = '/fix:general::scc\.conf:os_category[ \t]*([^\r\n]+)/i';

    private const UUID_PATTERN = '#fix:(?:general::UUID:|agent:/etc/uuidscc::)[ \t]*([^\r\n]*)#i';

    private PluginUploadsccglpiConfig $config;

    public function __construct(?PluginUploadsccglpiConfig $config = null)
    {
        $this->config = $config ?? new PluginUploadsccglpiConfig();
    }

    private static function t(string $s): string
    {
        return __($s, 'uploadsccglpi');
    }

    public function check(string $path, string $stem): string
    {
        $content = PluginUploadsccglpiArchive::findMember($path, self::MEMBER_SUFFIX);
        if ($content === null) {
            return sprintf(self::t('No "%s" file could be read from the archive.'), self::MEMBER_SUFFIX);
        }

        $reason = $this->checkDate($content);
        if ($reason !== '') {
            return $reason;
        }

        $reason = $this->checkVersion($content);
        if ($reason !== '') {
            return $reason;
        }

        return $this->checkHostAccess($content, $stem);
    }

    private function checkHostAccess(string $content, string $stem): string
    {
        $uuid = self::hostUuid($content, $stem);
        if ($uuid === '') {
            return self::t('The host UUID could not be determined from the .cur file or the file name.');
        }

        $entities = PluginUploadsccglpiUploadedFile::computerEntities($uuid);
        if ($entities === []) {
            return '';
        }

        $allowed = array_map('intval', Profile_User::getUserEntities((int) Session::getLoginUserID(), true));
        foreach ($entities as $entity) {
            if (!in_array($entity, $allowed, true)) {
                return self::t('You do not have permission to the IT system of this host.');
            }
        }

        return '';
    }

    public static function hostUuid(string $content, string $stem): string
    {
        if (preg_match(self::UUID_PATTERN, $content, $m) === 1) {
            $value = trim($m[1]);
            if ($value !== '' && strcasecmp($value, 'none') !== 0) {
                return $value;
            }
        }

        $at = strrpos($stem, '@');

        return $at === false ? '' : trim(substr($stem, $at + 1));
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

        if ($timestamp > strtotime('now +1 day')) {
            return sprintf(
                self::t('The inventory date %s is in the future.'),
                date('Y-m-d H:i', $timestamp)
            );
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

        [$min, $max] = self::versionRange($newest, $this->config->versionMaxGap());
        if (self::withinRange($found, $min, $max)) {
            return '';
        }

        return version_compare($found, $newest, '<')
            ? sprintf(
                self::t('The agent version %s is too old for %s - the minimum accepted version is %s.'),
                $found,
                $category,
                $min
            )
            : sprintf(
                self::t('The agent version %s is newer than allowed for %s - the maximum accepted version is %s.'),
                $found,
                $category,
                $max
            );
    }

    public static function versionRange(string $newest, int $maxGap): array
    {
        $parts  = explode('.', $newest);
        $last   = (int) array_pop($parts);
        $spread = max(0, $maxGap - 1);
        $prefix = $parts === [] ? '' : implode('.', $parts) . '.';

        return [$prefix . max(0, $last - $spread), $prefix . ($last + $spread)];
    }

    private static function withinRange(string $found, string $min, string $max): bool
    {
        $foundParts = explode('.', $found);
        $minParts   = explode('.', $min);

        if (count($foundParts) !== count($minParts) || array_slice($foundParts, 0, -1) !== array_slice($minParts, 0, -1)) {
            return false;
        }

        return version_compare($found, $min, '>=') && version_compare($found, $max, '<=');
    }
}
