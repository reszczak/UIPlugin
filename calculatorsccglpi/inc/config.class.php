<?php
class PluginCalculatorsccglpiConfig
{
    public const CONTEXT = 'plugin:calculatorsccglpi';

    public function disallowedEntries(): array
    {
        $values = Config::getConfigurationValues(self::CONTEXT);
        $raw    = json_decode((string) ($values['disallowed_filesystems'] ?? '[]'), true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry) || trim((string) ($entry['name'] ?? '')) === '') {
                continue;
            }
            $out[] = [
                'name'   => trim((string) $entry['name']),
                'active' => (bool) ($entry['active'] ?? true),
            ];
        }
        return $out;
    }

    public function saveEntries(array $entries): void
    {
        $clean = [];
        foreach ($entries as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '' || isset($clean[mb_strtolower($name)])) {
                continue;
            }
            $clean[mb_strtolower($name)] = [
                'name'   => $name,
                'active' => (bool) ($entry['active'] ?? true),
            ];
        }
        Config::setConfigurationValues(self::CONTEXT, [
            'disallowed_filesystems' => json_encode(
                array_values($clean),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }

    public function addEntry(string $name): void
    {
        $entries   = $this->disallowedEntries();
        $entries[] = ['name' => $name, 'active' => true];
        $this->saveEntries($entries);
    }

    public function deleteEntry(string $name): void
    {
        $entries = array_filter(
            $this->disallowedEntries(),
            fn(array $e) => mb_strtolower($e['name']) !== mb_strtolower($name)
        );
        $this->saveEntries($entries);
    }

    public function activeDisallowedNames(): array
    {
        $out = [];
        foreach ($this->disallowedEntries() as $entry) {
            if ($entry['active']) {
                $out[] = mb_strtolower($entry['name']);
            }
        }
        return $out;
    }

    public function isFilesystemAllowed(string $fsName): bool
    {
        return !in_array(mb_strtolower(trim($fsName)), $this->activeDisallowedNames(), true);
    }
}
