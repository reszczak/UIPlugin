<?php
interface PluginUicustomPanelConfigurableTweakInterface extends PluginUicustomTweakInterface
{
    public function getProfileSaveButtonName(): string;

    public function renderProfileSection(array $tweakConfig, int $profilesId, array $catalog): string;

    public function handleProfileSave(array $post, array $tweakConfig, array $catalog): array;
}
