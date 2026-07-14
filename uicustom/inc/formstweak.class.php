<?php
/**
 * Tweak "forms": tabs and form fields per itemtype. Configured only in
 * live edit mode, not in the panel.
 */
class PluginUicustomFormsTweak implements PluginUicustomTweakInterface
{
    public function getKey(): string
    {
        return 'forms';
    }

    public function getDefaultConfig(): array
    {
        return [];
    }

    public function getAssets(PluginUicustomContext $context): array
    {
        if (!$context->hasActiveRule()) {
            return ['css' => [], 'js' => []];
        }
        return ['css' => ['css/hide.css'], 'js' => ['js/profile-ui.js']];
    }

    public function filterMenu(array $menu, array $tweakConfig): array
    {
        return $menu;
    }

    /** Whitelist for tab names and Device* class names. */
    public static function sanitizeTabsList(array $raw): array
    {
        $tabs = [];
        foreach ($raw as $tb) {
            if (is_string($tb) && preg_match('/^[A-Za-z0-9_\\\\]+$/', $tb)) {
                $tabs[] = $tb;
            }
        }
        return array_values(array_unique($tabs));
    }

    /**
     * Hidden columns map: { "<TabClass>": [column indexes] }.
     *
     * @return array<string,int[]>
     */
    public static function sanitizeColumnsMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $tabCls => $idxs) {
            if (!is_string($tabCls) || !preg_match('/^[A-Za-z0-9_\\\\]+$/', $tabCls)) {
                continue;
            }
            $clean = [];
            foreach ((array) $idxs as $v) {
                if (is_numeric($v) && (int) $v >= 0) {
                    $clean[] = (int) $v;
                }
            }
            if (!empty($clean)) {
                $out[$tabCls] = array_values(array_unique($clean));
            }
        }
        return $out;
    }

    /** @return array{mode:string,list:string[]}|null Null if the action fails the whitelist. */
    public static function sanitizeFieldRule(string $action, array $rule): ?array
    {
        if (!preg_match('/^[a-z0-9_]+\.form\.php$/i', $action)) {
            return null;
        }
        $mode = (($rule['mode'] ?? 'keep') === 'hide') ? 'hide' : 'keep';
        $list = [];
        foreach ((array) ($rule['list'] ?? []) as $f) {
            if (is_string($f) && preg_match('/^[A-Za-z0-9_]+$/', $f)) {
                $list[] = $f;
            }
        }
        return ['mode' => $mode, 'list' => array_values(array_unique($list))];
    }
}
