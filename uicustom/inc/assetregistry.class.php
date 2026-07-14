<?php
/**
 * Collects CSS/JS assets declared by tweaks and writes them to $PLUGIN_HOOKS.
 */
class PluginUicustomAssetRegistry
{
    /** @var array<string,true> */
    private array $css = [];
    private array $js  = [];

    public function addCss(string $path): self
    {
        $this->css[$path] = true;
        return $this;
    }

    public function addJs(string $path): self
    {
        $this->js[$path] = true;
        return $this;
    }

    public function collect(PluginUicustomContext $context): self
    {
        foreach (PluginUicustomTweakRegistry::all() as $tweak) {
            $assets = $tweak->getAssets($context);
            foreach ($assets['css'] ?? [] as $c) {
                $this->addCss($c);
            }
            foreach ($assets['js'] ?? [] as $j) {
                $this->addJs($j);
            }
        }

        if ($context->isConfigAdmin()) {
            $this->addJs('js/edit-mode.js');
            $this->addCss('css/edit-mode.css');
        }

        $this->addCss('ajax/branding.css.php');

        if (!empty($this->js)) {
            $this->js = array_merge(
                ['js/uic-core.js' => true, 'js/uic-dom-utils.js' => true],
                $this->js
            );
        }

        return $this;
    }

    public function cssList(): array
    {
        return array_keys($this->css);
    }

    public function jsList(): array
    {
        return array_keys($this->js);
    }

    public function applyToHooks(array &$hooks): void
    {
        if (!empty($this->css)) {
            $hooks['add_css']['uicustom'] = $this->cssList();
        }
        if (!empty($this->js)) {
            $hooks['add_javascript']['uicustom'] = $this->jsList();
        }
    }
}
