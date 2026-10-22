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

    private string $lastGroup = '';

    public function lastGroup(): string
    {
        return $this->lastGroup;
    }

    private function reject(string $message, ?string $group = null): string
    {
        $this->lastGroup = $group ?? $message;

        return $message;
    }

    public function check(string $path, string $stem): string
    {
        $this->lastGroup = '';

        $content = PluginUploadsccglpiArchive::findMember($path, self::MEMBER_SUFFIX);
        if ($content === null) {
            return $this->reject(sprintf(self::t('No "%s" file could be read from the archive.'), self::MEMBER_SUFFIX));
        }

        $reason = $this->checkDate($content);
        if ($reason !== '') {
            return $reason;
        }

        $reason = $this->checkVersion($content);
        if ($reason !== '') {
            return $reason;
        }

        $reason = $this->checkHostAccess($content, $stem);
        if ($reason !== '') {
            return $reason;
        }

        return $this->checkNotOlderThanKnown($content, $stem);
    }

    private function checkNotOlderThanKnown(string $content, string $stem): string
    {
        $uuid = self::hostUuid($content, $stem);
        if ($uuid === '' || preg_match(self::DATE_PATTERN, $content, $m) !== 1) {
            return '';
        }

        $timestamp = strtotime(trim($m[1]));
        if ($timestamp === false) {
            return '';
        }

        $known = PluginUploadsccglpiUploadedFile::lastScanDate($uuid);
        $day   = date('Y-m-d', $timestamp);
        if ($known === null || $day >= $known) {
            return '';
        }

        return $this->reject(
            sprintf(self::t('The inventory date %s is older than the last known one for this host (%s).'), $day, $known),
            self::t('The inventory is older than the one already stored.')
        );
    }

    private function checkHostAccess(string $content, string $stem): string
    {
        $uuid = self::hostUuid($content, $stem);
        if ($uuid === '') {
            return $this->reject(self::t('The host UUID could not be determined from the .cur file or the file name.'));
        }

        $entities = PluginUploadsccglpiUploadedFile::computerEntities($uuid);
        if ($entities === []) {
            return '';
        }

        $allowed = array_map('intval', Profile_User::getUserEntities((int) Session::getLoginUserID(), true));
        foreach ($entities as $entity) {
            if (!in_array($entity, $allowed, true)) {
                return $this->reject(self::t('You do not have permission to the IT system of this host.'));
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
            return $this->reject(
                self::t('The inventory date (var:general::date) is missing from the .cur file.'),
                self::t('The inventory date is missing or unreadable.')
            );
        }

        $raw       = trim($m[1]);
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return $this->reject(
                sprintf(self::t('The inventory date "%s" could not be read.'), $raw),
                self::t('The inventory date is missing or unreadable.')
            );
        }

        if ($timestamp > strtotime('now +1 day')) {
            return $this->reject(
                sprintf(self::t('The inventory date %s is in the future.'), date('Y-m-d H:i', $timestamp)),
                self::t('The inventory date is in the future.')
            );
        }

        $days = $this->config->maxAgeDays();
        if ($timestamp < strtotime("today -{$days} days")) {
            return $this->reject(
                sprintf(
                    self::t('The inventory is from %s and is older than the allowed %d days.'),
                    date('Y-m-d H:i', $timestamp),
                    $days
                ),
                self::t('The inventory is too old.')
            );
        }

        return '';
    }

    private function checkVersion(string $content): string
    {
        if (preg_match(self::VERSION_PATTERN, $content, $m) !== 1) {
            return $this->reject(
                self::t('The scc.conf version (fix:general::scc.conf:version) is missing from the .cur file.'),
                self::t('The agent version is missing or unreadable.')
            );
        }

        $found = trim($m[1]);

        if (preg_match(self::CATEGORY_PATTERN, $content, $c) !== 1) {
            return $this->reject(
                self::t('The system category (fix:general::scc.conf:os_category) is missing from the .cur file.'),
                self::t('The system category is missing or unknown.')
            );
        }

        $category = trim($c[1]);
        $platform = PluginUploadsccglpiAgentVersions::platformFor($category);
        if ($platform === null) {
            return $this->reject(
                sprintf(
                    self::t('Unknown system category "%s" - expected one of: %s.'),
                    $category,
                    implode(', ', PluginUploadsccglpiAgentVersions::knownCategories())
                ),
                self::t('The system category is missing or unknown.')
            );
        }

        $newest = $this->config->newestVersionFor($platform);
        if ($newest === null) {
            return $this->reject(
                sprintf(
                    self::t('No newest agent version is known for %s - the "%s" knowledge base article could not be read. Contact your GLPI administrator.'),
                    $category,
                    PluginUploadsccglpiAgentVersions::KB_TITLE
                ),
                self::t('The newest agent version is unknown.')
            );
        }

        if (!PluginUploadsccglpiConfig::isVersion($found)) {
            return $this->reject(
                sprintf(self::t('The scc.conf version "%s" could not be read.'), $found),
                self::t('The agent version is missing or unreadable.')
            );
        }

        [$min, $max] = self::versionRange($newest, $this->config->versionMaxGap());
        if (self::withinRange($found, $min, $max)) {
            return '';
        }

        return version_compare($found, $newest, '<')
            ? $this->reject(
                sprintf(self::t('The agent version %s is too old for %s.'), $found, $category),
                self::t('The agent version is too old.')
            )
            : $this->reject(
                sprintf(self::t('The agent version %s is newer than allowed for %s.'), $found, $category),
                self::t('The agent version is newer than allowed.')
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
