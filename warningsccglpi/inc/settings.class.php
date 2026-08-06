<?php
class PluginWarningsccglpiSettings
{
    private const CONTEXT = 'plugin:warningsccglpi';

    private const DEFAULT_LABEL = 'PRODUCTION';
    private const DEFAULT_COLOR = '#c0392b';

    public static function isValidHexColor(string $v): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $v);
    }

    public function getRaw(): array
    {
        return Config::getConfigurationValues(self::CONTEXT, ['label', 'color']);
    }

    public function get(): array
    {
        $stored = $this->getRaw();

        $label = (string) ($stored['label'] ?? '');
        $color = (string) ($stored['color'] ?? '');

        return [
            'label' => $label !== '' ? $label : __(self::DEFAULT_LABEL, 'warningsccglpi'),
            'color' => self::isValidHexColor($color) ? $color : self::DEFAULT_COLOR,
        ];
    }

    public function save(array $post): void
    {
        Config::setConfigurationValues(self::CONTEXT, [
            'label' => trim((string) ($post['label'] ?? '')),
            'color' => trim((string) ($post['color'] ?? '')),
        ]);
    }
}
