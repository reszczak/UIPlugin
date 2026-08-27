<?php
class PluginCalculatorsccglpiComponentsSummary
{
    public static function t(string $s): string
    {
        return __($s, 'calculatorsccglpi');
    }

    public function compute(CommonDBTM $item): array
    {
        global $DB;

        $where = [
            'itemtype'   => $item->getType(),
            'items_id'   => $item->getID(),
            'is_deleted' => 0,
        ];

        $memory = 0;
        foreach ($DB->request(['SELECT' => 'size', 'FROM' => Item_DeviceMemory::getTable(), 'WHERE' => $where]) as $row) {
            $memory += max(0, (int) $row['size']);
        }

        $hdd = 0;
        foreach ($DB->request(['SELECT' => 'capacity', 'FROM' => Item_DeviceHardDrive::getTable(), 'WHERE' => $where]) as $row) {
            $hdd += max(0, (int) $row['capacity']);
        }

        $logical        = 0;
        $cores          = 0;
        $multithreading = false;
        foreach ($DB->request(['SELECT' => ['nbcores', 'nbthreads'], 'FROM' => Item_DeviceProcessor::getTable(), 'WHERE' => $where]) as $row) {
            $c = max(0, (int) $row['nbcores']);
            $t = max(0, (int) $row['nbthreads']);
            $logical++;
            $cores += $c;
            if ($c > 0 && $t > $c) {
                $multithreading = true;
            }
        }

        return [
            'memory'         => $memory,
            'hdd'            => $hdd,
            'logical'        => $logical,
            'cores'          => $cores,
            'multithreading' => $multithreading,
        ];
    }

    public function render(CommonDBTM $item): void
    {
        $sums = $this->compute($item);

        $title = htmlescape(self::t('Components summary'));

        $tiles = [
            [
                'icon'  => 'ti-stack-2',
                'label' => self::t('Total RAM'),
                'value' => $this->formatMio($sums['memory']),
                'code'  => 'MEMORY',
            ],
            [
                'icon'  => 'ti-database',
                'label' => self::t('Total disk capacity'),
                'value' => $this->formatMio($sums['hdd']),
                'code'  => 'HDD',
            ],
        ];

        $tilesHtml = '';
        foreach ($tiles as $tile) {
            $icon   = htmlescape($tile['icon']);
            $label  = htmlescape($tile['label']);
            $value  = htmlescape($tile['value']);
            $code   = htmlescape($tile['code']);
            $vclass = htmlescape($tile['class'] ?? '');
            $tilesHtml .= <<<HTML
                <div class="border rounded-2 px-3 py-2 flex-fill text-center" style="min-width: 10.5rem;">
                    <div class="d-flex align-items-center justify-content-center text-muted small mb-1 text-nowrap">
                        <i class="ti {$icon} me-1"></i>{$label}
                    </div>
                    <div class="fs-3 fw-bold lh-1 {$vclass}">{$value}</div>
                    <div class="text-muted small font-monospace mt-1">{$code}</div>
                </div>
            HTML;
        }
        $tilesHtml .= $this->cpuTileHtml($sums);

        echo <<<HTML
        <div class="card mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="ti ti-cpu me-1"></i>{$title}</h3>
            </div>
            <div class="card-body py-3 d-flex flex-wrap justify-content-center gap-3">
                {$tilesHtml}
            </div>
        </div>
        HTML;
    }

    private function cpuTileHtml(array $sums): string
    {
        $label   = htmlescape(self::t('Processor'));
        $mtValue = $sums['multithreading']
            ? "<span class='text-green'>" . htmlescape(__('Yes')) . "</span>"
            : htmlescape(__('No'));
        $logical = (int) $sums['logical'];
        $cores   = (int) $sums['cores'];

        $columns = [
            ['value' => (string) $logical, 'code' => 'CPU_LOGICAL_CNT'],
            ['value' => (string) $cores,   'code' => 'CPU_CORE_CNT'],
            ['value' => $mtValue,          'code' => 'MULTITHREADING'],
        ];

        $columnsHtml = '';
        foreach ($columns as $column) {
            $columnsHtml .= <<<HTML
                <div class="text-center">
                    <div class="fs-3 fw-bold lh-1">{$column['value']}</div>
                    <div class="text-muted small font-monospace mt-1 text-nowrap">{$column['code']}</div>
                </div>
            HTML;
        }

        return <<<HTML
            <div class="border rounded-2 px-3 py-2 flex-fill text-center" style="min-width: 22rem;">
                <div class="d-flex align-items-center justify-content-center text-muted small mb-1 text-nowrap">
                    <i class="ti ti-cpu me-1"></i>{$label}
                </div>
                <div class="d-flex flex-wrap justify-content-center column-gap-4">
                    {$columnsHtml}
                </div>
            </div>
        HTML;
    }

    private function formatMio(int $mio): string
    {
        return Toolbox::getSize($mio * 1024 * 1024);
    }
}
