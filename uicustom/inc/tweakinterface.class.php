<?php
interface PluginUicustomTweakInterface
{
    public function getKey(): string;

    public function getDefaultConfig(): array;

    public function getAssets(PluginUicustomContext $context): array;

    public function filterMenu(array $menu, array $tweakConfig): array;
}
