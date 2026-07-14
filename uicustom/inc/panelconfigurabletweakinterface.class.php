<?php
/**
 * Interface for tweaks configurable in the admin panel (profile level).
 */
interface PluginUicustomPanelConfigurableTweakInterface extends PluginUicustomTweakInterface
{
    /** Submit button name for the profile-level form. */
    public function getProfileSaveButtonName(): string;

    /** HTML for this tweak's section on the profile page. */
    public function renderProfileSection(array $tweakConfig, int $profilesId, array $catalog): string;

    /** Parses $_POST into a new config fragment. */
    public function handleProfileSave(array $post, array $tweakConfig, array $catalog): array;
}
