<?php
class PluginCalculatorsccglpiSummary
{
    private PluginCalculatorsccglpiConfig $config;

    public function __construct(?PluginCalculatorsccglpiConfig $config = null)
    {
        $this->config = $config ?? new PluginCalculatorsccglpiConfig();
    }

    public static function t(string $s): string
    {
        return __($s, 'calculatorsccglpi');
    }

    public function compute(CommonDBTM $item): array
    {
        global $DB;

        $disallowed = $this->config->activeDisallowedNames();
        $total      = 0;
        $free       = 0;
        $included   = 0;
        $excluded   = 0;

        $diskTable = Item_Disk::getTable();
        $fsTable   = Filesystem::getTable();

        $iterator = $DB->request([
            'SELECT'    => [
                "$diskTable.totalsize",
                "$diskTable.freesize",
                "$fsTable.name AS fsname",
            ],
            'FROM'      => $diskTable,
            'LEFT JOIN' => [
                $fsTable => [
                    'ON' => [
                        $fsTable   => 'id',
                        $diskTable => 'filesystems_id',
                    ],
                ],
            ],
            'WHERE'     => [
                "$diskTable.itemtype"   => $item->getType(),
                "$diskTable.items_id"   => $item->getID(),
                "$diskTable.is_deleted" => 0,
            ],
        ]);

        foreach ($iterator as $row) {
            $fsName = mb_strtolower(trim((string) ($row['fsname'] ?? '')));
            if (in_array($fsName, $disallowed, true)) {
                $excluded++;
                continue;
            }
            $included++;
            $total += (int) $row['totalsize'];
            $free  += (int) $row['freesize'];
        }

        return [
            'total'    => $total,
            'free'     => $free,
            'used'     => max(0, $total - $free),
            'included' => $included,
            'excluded' => $excluded,
        ];
    }

    public function render(CommonDBTM $item): void
    {
        $sums    = $this->compute($item);
        $percent = $sums['total'] > 0 ? (int) round(100 * $sums['used'] / $sums['total']) : 0;

        $totalH = htmlescape($this->formatMio($sums['total']));
        $usedH  = htmlescape($this->formatMio($sums['used']));
        $freeH  = htmlescape($this->formatMio($sums['free']));

        $title  = htmlescape(self::t('Disk space summary'));
        $lTotal = htmlescape(self::t('Total size'));
        $lUsed  = htmlescape(self::t('Used'));
        $lFree  = htmlescape(self::t('Free'));

        $barColor = $percent >= 90 ? 'bg-danger' : ($percent >= 75 ? 'bg-warning' : 'bg-primary');

        $configLink = '';
        if (Session::haveRight('config', UPDATE)) {
            global $CFG_GLPI;
            $url        = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/calculatorsccglpi/front/config.form.php';
            $configTip  = htmlescape(self::t('Configure counted filesystems'));
            $configLink = "<a href='" . htmlescape($url) . "' class='ms-auto text-muted' title='{$configTip}'>"
                . "<i class='ti ti-settings'></i></a>";
        }

        $badgeColor = $percent >= 90 ? 'bg-red-lt' : ($percent >= 75 ? 'bg-yellow-lt' : 'bg-blue-lt');

        echo <<<HTML
        <div class="card mb-3">
            <div class="card-body py-2 d-flex flex-wrap align-items-center column-gap-4 row-gap-2">
                <span class="fw-bold text-nowrap"><i class="ti ti-database me-1"></i>{$title}</span>
                <div class="vr d-none d-md-block"></div>
                <div class="text-nowrap">
                    <div class="text-muted small lh-1 mb-1">{$lTotal}</div>
                    <div class="fw-bold fs-5 lh-1">{$totalH}</div>
                </div>
                <div class="text-nowrap">
                    <div class="text-muted small lh-1 mb-1">{$lUsed}</div>
                    <div class="fw-bold fs-5 lh-1">{$usedH} <span class="badge {$badgeColor} ms-1">{$percent}%</span></div>
                </div>
                <div class="text-nowrap">
                    <div class="text-muted small lh-1 mb-1">{$lFree}</div>
                    <div class="fw-bold fs-5 lh-1">{$freeH}</div>
                </div>
                <div class="progress flex-grow-1" style="min-width: 140px; max-width: 300px; height: 12px;">
                    <div class="progress-bar {$barColor}" role="progressbar"
                         style="width: {$percent}%;" aria-valuenow="{$percent}"
                         aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                {$configLink}
            </div>
        </div>
        HTML;
    }

    private function formatMio(int $mio): string
    {
        return Toolbox::getSize($mio * 1024 * 1024);
    }
}
