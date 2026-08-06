<?php
class PluginUicustomContext
{
    private bool $configLoaded = false;
    private ?array $configCache = null;

    public function __construct(private PluginUicustomProfileConfigRepository $repo)
    {
    }

    public function activeProfileId(): int
    {
        return (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
    }

    public function isConfigAdmin(): bool
    {
        return (bool) ((int) ($_SESSION['glpiactiveprofile']['config'] ?? 0) & UPDATE);
    }

    public function activeConfig(): ?array
    {
        if (!$this->configLoaded) {
            $id = $this->activeProfileId();
            $this->configCache = $id > 0 ? $this->repo->get($id) : null;
            $this->configLoaded = true;
        }
        return $this->configCache;
    }

    public function hasActiveRule(): bool
    {
        return $this->activeConfig() !== null;
    }
}
