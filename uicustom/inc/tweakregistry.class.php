<?php
/**
 * Registry of all plugin tweaks.
 */
class PluginUicustomTweakRegistry
{
    /** @var array<string,PluginUicustomTweakInterface> */
    private static array $tweaks = [];
    private static bool $booted = false;

    public static function register(PluginUicustomTweakInterface $tweak): void
    {
        self::$tweaks[$tweak->getKey()] = $tweak;
    }

    /** @return PluginUicustomTweakInterface[] */
    public static function all(): array
    {
        self::boot();
        return self::$tweaks;
    }

    public static function get(string $key): ?PluginUicustomTweakInterface
    {
        self::boot();
        return self::$tweaks[$key] ?? null;
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        self::register(new PluginUicustomMenuTweak());
        self::register(new PluginUicustomFormsTweak());
    }
}
