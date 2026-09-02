<?php
class PluginConfigfilessccglpiRenderer
{
    public function render(Computer $computer): void
    {
        $locator   = new PluginConfigfilessccglpiDocumentLocator();
        $documents = $locator->findForComputer($computer);

        $navLinks = ['home' => $this->tabUrl($computer, 'Computer$main')];
        if (!empty($locator->findLogbookForComputer($computer))) {
            $navLinks['logbook'] = $this->tabUrl($computer, 'PluginConfigfilessccglpiLogbookTab$1');
        }

        $this->renderDocuments(
            $documents,
            __('No configuration file is linked to this computer yet.', 'configfilessccglpi'),
            $navLinks
        );
    }

    public function renderLogbook(Computer $computer): void
    {
        $documents = (new PluginConfigfilessccglpiDocumentLocator())->findLogbookForComputer($computer);

        $navLinks = [
            'home'          => $this->tabUrl($computer, 'Computer$main'),
            'configuration' => $this->tabUrl($computer, 'PluginConfigfilessccglpiComputertab$1'),
        ];

        $this->renderDocuments(
            $documents,
            __('No logbook file is linked to this computer yet.', 'configfilessccglpi'),
            $navLinks
        );
    }

    private function tabUrl(Computer $computer, string $forcetab): string
    {
        global $CFG_GLPI;

        return $CFG_GLPI['url_base'] . '/front/computer.form.php?id=' . $computer->getID() . '&forcetab=' . $forcetab;
    }

    private function renderDocuments(array $documents, string $emptyMessage, array $navLinks): void
    {
        if (empty($documents)) {
            echo "<div class='alert alert-info'>" . htmlescape($emptyMessage) . "</div>";
            return;
        }

        $extractor = new PluginConfigfilessccglpiHtmlExtractor();
        foreach ($documents as $document) {
            $this->renderDocument($document, $extractor, $navLinks);
        }
    }

    private function renderDocument(array $document, PluginConfigfilessccglpiHtmlExtractor $extractor, array $navLinks): void
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

        $raw    = (string) file_get_contents($path);
        $srcdoc = htmlescape($extractor->buildEmbeddableDocument($raw, $navLinks));

        $title        = htmlescape($document['name'] !== '' ? $document['name'] : $document['filename']);
        $updatedLabel = htmlescape(__('Last update', 'configfilessccglpi'));
        $updated      = htmlescape(Html::convDateTime($document['date_mod']));

        echo "<div class='card mb-3'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center flex-wrap gap-2'>";
        echo "<h3 class='card-title mb-0'><i class='ti ti-file-code me-1'></i>{$title}</h3>";
        echo "<span class='text-muted small'>{$updatedLabel}: {$updated}</span>";
        echo "</div>";
        echo "<div class='card-body p-0'>";
        echo "<iframe class='plugin-configfilessccglpi-frame' sandbox='allow-same-origin allow-top-navigation-by-user-activation' "
            . "style='width:100%;border:0;display:block;min-height:200px' "
            . "srcdoc=\"{$srcdoc}\"></iframe>";
        echo "</div></div>";
    }
}
