<?php
/**
 * Config panel: edit form + save handling for the documentation record.
 */
class PluginAboutsccglpiAdminPanel
{
    public function run(): void
    {
        $doc = PluginAboutsccglpiDocumentation::getSingleton();
        $this->handlePost($doc);

        Html::header(PluginAboutsccglpiMenu::getMenuName(), $_SERVER['PHP_SELF'], 'config', 'plugins');

        $this->render(PluginAboutsccglpiDocumentation::getSingleton());

        Html::footer();
    }

    /* ------------------------------ POST ------------------------------- */

    private function handlePost(PluginAboutsccglpiDocumentation $doc): void
    {
        if (!isset($_POST['update'])) {
            return;
        }
        $doc->saveContent((string) ($_POST['name'] ?? ''), (string) ($_POST['content'] ?? ''));
        Session::addMessageAfterRedirect(PluginAboutsccglpiHtmlHelper::t('Documentation saved successfully.'), true, INFO);
        Html::back();
    }

    /* ------------------------------ view ------------------------------- */

    private function render(PluginAboutsccglpiDocumentation $doc): void
    {
        $t       = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);
        $self    = PluginAboutsccglpiHtmlHelper::selfUrl();
        $viewUrl = PluginAboutsccglpiHtmlHelper::viewUrl();
        $name    = (string) ($doc->fields['name'] ?? '');
        $content = (string) ($doc->fields['content'] ?? '');
        $preview = $doc->getRenderedHtml();

        echo "<div class='aboutsccglpi-config'>";
        echo "<form name='aboutsccglpi_config_form' method='post' action='" . htmlescape($self) . "'>";
        echo PluginAboutsccglpiHtmlHelper::csrfHidden();
        echo "<div class='card'>";

        echo "<div class='card-header d-flex align-items-center'>";
        echo "<h2 class='card-title mb-0'><i class='ti ti-settings me-1'></i> "
            . htmlescape($t('Configuration — “About the Application” documentation')) . "</h2>";
        echo "<a href='" . htmlescape($viewUrl) . "' class='btn btn-sm btn-outline-secondary ms-auto' target='_blank'>";
        echo "<i class='ti ti-external-link'></i> " . htmlescape($t('View page'));
        echo "</a>";
        echo "</div>";

        echo "<div class='card-body'>";
        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='oap-name'>" . htmlescape($t('Page title')) . "</label>";
        echo "<input type='text' class='form-control' id='oap-name' name='name' value='" . htmlescape($name) . "'"
            . " placeholder='" . htmlescape(PluginAboutsccglpiMenu::getMenuName()) . "'>";
        echo "</div>";

        echo "<div class='row'>";

        echo "<div class='col-lg-6 mb-3'>";
        echo "<label class='form-label d-flex align-items-center' for='oap-content'>";
        echo "<i class='ti ti-markdown me-1'></i> " . htmlescape($t('Documentation (Markdown)'));
        echo "</label>";
        echo "<textarea class='form-control' id='oap-content' name='content' rows='24' spellcheck='false'"
            . " style='font-family: var(--tblr-font-monospace, monospace); font-size: .875rem;'"
            . " placeholder='# Heading&#10;&#10;Paste your documentation here in Markdown format…'>"
            . htmlescape($content) . "</textarea>";
        echo "<div class='form-hint mt-2'>";
        echo htmlescape($t('Paste the content in Markdown format. Supported, among others:')) . " ";
        echo "<code>#</code> " . htmlescape($t('headings')) . ", ";
        echo "<code>**" . htmlescape($t('bold')) . "**</code>, <code>*" . htmlescape($t('italics')) . "*</code>, "
            . "<code>- " . htmlescape($t('lists')) . "</code>, <code>[link](https://…)</code>, "
            . "<code>`" . htmlescape($t('code')) . "`</code>, " . htmlescape($t('tables and quotes')) . " ";
        echo "(" . htmlescape($t('GitHub Flavored Markdown')) . "). ";
        echo htmlescape($t('The table of contents and heading anchors are generated automatically.'));
        echo "</div>";
        echo "</div>";

        echo "<div class='col-lg-6 mb-3'>";
        echo "<label class='form-label d-flex align-items-center'>";
        echo "<i class='ti ti-eye me-1'></i> " . htmlescape($t('Preview (last saved)'));
        echo "</label>";
        echo "<div class='border rounded p-3 aboutsccglpi-doc' style='height: 585px; overflow-y: auto;'>";
        if (trim($preview) === '') {
            echo "<div class='text-muted text-center py-5'>" . htmlescape($t('No content yet — paste Markdown and save.')) . "</div>";
        } else {
            echo "<div class='markdown-body'>{$preview}</div>";
        }
        echo "</div>";
        echo "</div>";

        echo "</div>"; // row
        echo "</div>"; // card-body

        echo "<div class='card-footer text-end'>";
        echo "<button type='submit' name='update' class='btn btn-primary'>";
        echo "<i class='ti ti-device-floppy'></i> " . _x('button', 'Save');
        echo "</button>";
        echo "</div>";

        echo "</div>"; // card
        echo "</form>";
        echo "</div>"; // aboutsccglpi-config

        echo $this->styles();
    }

    /** Typography for the preview pane. */
    private function styles(): string
    {
        return <<<'CSS'
        <style>
        .aboutsccglpi-config .markdown-body { line-height: 1.6; }
        .aboutsccglpi-config .markdown-body h1 { font-size: 1.6rem; }
        .aboutsccglpi-config .markdown-body h2 { font-size: 1.3rem; padding-bottom: .3rem;
            border-bottom: 1px solid var(--tblr-border-color, #e6e7e9); }
        .aboutsccglpi-config .markdown-body h1,
        .aboutsccglpi-config .markdown-body h2,
        .aboutsccglpi-config .markdown-body h3 { margin: 1.2rem 0 .6rem; font-weight: 600; }
        .aboutsccglpi-config .markdown-body h1:first-child { margin-top: 0; }
        .aboutsccglpi-config .markdown-body pre { padding: .75rem; border-radius: 6px; overflow-x: auto;
            background: var(--tblr-bg-surface-secondary, #f1f3f5); }
        .aboutsccglpi-config .markdown-body code { padding: .1em .35em; border-radius: 4px;
            background: var(--tblr-bg-surface-secondary, #f1f3f5); font-size: .85em; }
        .aboutsccglpi-config .markdown-body pre code { padding: 0; background: none; }
        .aboutsccglpi-config .markdown-body blockquote { margin: .8rem 0; padding: .4rem .8rem;
            border-left: 3px solid var(--tblr-primary, #206bc4);
            background: var(--tblr-bg-surface-secondary, #f6f7f9); }
        .aboutsccglpi-config .markdown-body table { border-collapse: collapse; margin: .8rem 0; }
        .aboutsccglpi-config .markdown-body th,
        .aboutsccglpi-config .markdown-body td { padding: .35rem .6rem;
            border: 1px solid var(--tblr-border-color, #e6e7e9); }
        .aboutsccglpi-config .markdown-body .heading-permalink { display: none; }
        .aboutsccglpi-config .markdown-body .table-of-contents { padding-left: 1.5rem; }
        </style>
        CSS;
    }
}
