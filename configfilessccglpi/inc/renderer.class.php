<?php
class PluginConfigfilessccglpiRenderer
{
    public function render(Computer $computer): void
    {
        $locator   = new PluginConfigfilessccglpiDocumentLocator();
        $documents = $locator->findForComputer($computer);

        if (empty($documents)) {
            $this->renderEmptyState();
            return;
        }

        $logbook = $locator->findLogbookForComputer($computer)[0] ?? null;

        $extractor = new PluginConfigfilessccglpiHtmlExtractor();
        foreach ($documents as $document) {
            $this->renderDocument($document, $logbook, $extractor);
        }
    }

    private function renderEmptyState(): void
    {
        $message = htmlescape(__('No configuration file is linked to this computer yet.', 'configfilessccglpi'));
        echo "<div class='alert alert-info'>{$message}</div>";
    }

    private function renderDocument(array $document, ?array $logbook, PluginConfigfilessccglpiHtmlExtractor $extractor): void
    {
        $path = GLPI_DOC_DIR . '/' . $document['filepath'];
        if (!is_file($path) || !is_readable($path)) {
            $missing = htmlescape(sprintf(
                __('Configuration file "%s" is referenced in GLPI but missing on disk.', 'configfilessccglpi'),
                $document['filename']
            ));
            echo "<div class='alert alert-warning'>{$missing}</div>";
            return;
        }

        $raw          = (string) file_get_contents($path);
        $configSrcdoc = htmlescape($extractor->buildEmbeddableDocument($raw));

        $logSrcdoc = null;
        if ($logbook !== null) {
            $logPath = GLPI_DOC_DIR . '/' . $logbook['filepath'];
            if (is_file($logPath) && is_readable($logPath)) {
                $logRaw    = (string) file_get_contents($logPath);
                $logSrcdoc = htmlescape($extractor->buildEmbeddableDocument($logRaw));
            }
        }

        $title        = htmlescape($document['name'] !== '' ? $document['name'] : $document['filename']);
        $updatedLabel = htmlescape(__('Last update', 'configfilessccglpi'));
        $updated      = htmlescape(Html::convDateTime($document['date_mod']));

        echo "<div class='card mb-3'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>";
        echo "<h3 class='card-title mb-0'><i class='ti ti-file-code me-1'></i>{$title}</h3>";
        echo "<span class='text-muted small'>{$updatedLabel}: {$updated}</span>";
        echo "</div>";

        if ($logSrcdoc !== null) {
            $this->renderDualPane($configSrcdoc, $logSrcdoc);
        } else {
            echo "<div class='card-body p-0'>";
            $this->renderFrame($configSrcdoc);
            echo "</div>";
        }

        echo "</div>";
    }

    private function renderDualPane(string $configSrcdoc, string $logSrcdoc): void
    {
        $configLabel = htmlescape(__('Configuration file', 'configfilessccglpi'));
        $logLabel    = htmlescape(__('Logbook', 'configfilessccglpi'));

        echo "<div class='card-body p-0'>";
        echo "<ul class='nav nav-pills p-2 pb-0' role='tablist'>";
        echo "<li class='nav-item'><button type='button' class='nav-link active' data-scc-pane='config'>{$configLabel}</button></li>";
        echo "<li class='nav-item'><button type='button' class='nav-link' data-scc-pane='log'>{$logLabel}</button></li>";
        echo "</ul>";
        $this->renderFrame($configSrcdoc, 'config', false);
        $this->renderFrame($logSrcdoc, 'log', true);
        echo "</div>";
    }

    private function renderFrame(string $srcdoc, ?string $pane = null, bool $hidden = false): void
    {
        $paneAttr   = $pane !== null ? " data-scc-pane='{$pane}'" : '';
        $hiddenAttr = $hidden ? ' hidden' : '';
        echo "<iframe class='plugin-configfilessccglpi-frame' sandbox='allow-same-origin' "
            . "style='width:100%;border:0;display:block;min-height:200px'{$paneAttr}{$hiddenAttr} "
            . "srcdoc=\"{$srcdoc}\"></iframe>";
    }
}
