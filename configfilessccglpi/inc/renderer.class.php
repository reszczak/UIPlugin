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

        $logbookUrl = empty($locator->findLogbookForComputer($computer))
            ? null
            : $this->logbookUrl($computer);

        $extractor = new PluginConfigfilessccglpiHtmlExtractor();
        foreach ($documents as $document) {
            $this->renderDocument($document, $extractor, $logbookUrl);
        }
    }

    private function logbookUrl(Computer $computer): string
    {
        global $CFG_GLPI;

        return $CFG_GLPI['url_base'] . '/plugins/configfilessccglpi/front/logbook.php?computers_id=' . $computer->getID();
    }

    private function renderEmptyState(): void
    {
        $message = htmlescape(__('No configuration file is linked to this computer yet.', 'configfilessccglpi'));
        echo "<div class='alert alert-info'>{$message}</div>";
    }

    private function renderDocument(array $document, PluginConfigfilessccglpiHtmlExtractor $extractor, ?string $logbookUrl): void
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

        $raw        = (string) file_get_contents($path);
        $embeddable = $extractor->buildEmbeddableDocument($raw, $logbookUrl);
        $srcdoc     = htmlescape($embeddable);

        $title        = htmlescape($document['name'] !== '' ? $document['name'] : $document['filename']);
        $updatedLabel = htmlescape(__('Last update', 'configfilessccglpi'));
        $updated      = htmlescape(Html::convDateTime($document['date_mod']));

        echo "<div class='card mb-3'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>";
        echo "<h3 class='card-title mb-0'><i class='ti ti-file-code me-1'></i>{$title}</h3>";
        echo "<span class='text-muted small'>{$updatedLabel}: {$updated}</span>";
        echo "</div>";
        echo "<div class='card-body p-0'>";
        echo "<iframe class='plugin-configfilessccglpi-frame' sandbox='allow-same-origin allow-popups' "
            . "style='width:100%;border:0;display:block;min-height:200px' "
            . "srcdoc=\"{$srcdoc}\"></iframe>";
        echo "</div></div>";
    }
}
