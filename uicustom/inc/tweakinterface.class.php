<?php
/**
 * Interface for a single UI tweak.
 */
interface PluginUicustomTweakInterface
{
    /** Config section key, e.g. 'menu', 'forms'. */
    public function getKey(): string;

    public function getDefaultConfig(): array;

    /** CSS/JS assets (paths relative to public/) for the given context. */
    public function getAssets(PluginUicustomContext $context): array;

    /** Filters the menu structure. */
    public function filterMenu(array $menu, array $tweakConfig): array;
}
