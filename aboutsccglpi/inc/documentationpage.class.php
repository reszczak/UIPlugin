<?php
/**
 * Public documentation page (read-only).
 */
class PluginAboutsccglpiDocumentationPage
{
    public function run(): void
    {
        Html::header(
            PluginAboutsccglpiMenu::getMenuName(),
            $_SERVER['PHP_SELF'],
            'aboutsccglpi',
            PluginAboutsccglpiMenu::class
        );

        $doc = PluginAboutsccglpiDocumentation::getSingleton();

        $this->render(
            (string) ($doc->fields['name'] ?? PluginAboutsccglpiMenu::getMenuName()),
            $doc->getRenderedHtml(),
            PluginAboutsccglpiDocumentation::canUpdatePages()
        );

        Html::footer();
    }

    private function render(string $title, string $content, bool $canConfig): void
    {
        $t         = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);
        $configUrl = PluginAboutsccglpiHtmlHelper::selfUrl();

        echo "<div class='aboutsccglpi-doc'>";
        echo "<div class='d-flex flex-wrap align-items-center gap-2 mb-4'>";
        echo "<span class='avatar avatar-lg rounded bg-blue-lt'><i class='ti ti-book-2 fs-1'></i></span>";
        echo "<div class='me-auto'>";
        echo "<h1 class='mb-0'>" . htmlescape($title) . "</h1>";
        echo "<div class='text-muted'>" . htmlescape($t('User guide and documentation')) . "</div>";
        echo "</div>";

        if ($canConfig) {
            echo "<a href='" . htmlescape($configUrl) . "' class='btn btn-outline-secondary'>";
            echo "<i class='ti ti-settings'></i> " . htmlescape($t('Configure documentation'));
            echo "</a>";
        }
        echo "</div>";

        echo "<div class='card'><div class='card-body'>";
        if (trim($content) === '') {
            echo "<div class='text-center text-muted py-5'>";
            echo "<i class='ti ti-file-off fs-1 d-block mb-2'></i>";
            echo htmlescape($t('No documentation.'));
            if ($canConfig) {
                echo "<div class='mt-3'>";
                echo "<a href='" . htmlescape($configUrl) . "' class='btn btn-primary'>";
                echo "<i class='ti ti-settings'></i> " . htmlescape($t('Add documentation (Markdown)'));
                echo "</a>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<div class='markdown-body'>{$content}</div>";
        }
        echo "</div></div>";
        echo "</div>";

        echo $this->styles();
    }

    /** Typography for the rendered Markdown. */
    private function styles(): string
    {
        return <<<'CSS'
        <style>
        .aboutsccglpi-doc .markdown-body { line-height: 1.65; }
        .aboutsccglpi-doc .markdown-body h1,
        .aboutsccglpi-doc .markdown-body h2,
        .aboutsccglpi-doc .markdown-body h3,
        .aboutsccglpi-doc .markdown-body h4 { margin-top: 1.6rem; margin-bottom: .8rem; font-weight: 600; }
        .aboutsccglpi-doc .markdown-body h1:first-child,
        .aboutsccglpi-doc .markdown-body h2:first-child { margin-top: 0; }
        .aboutsccglpi-doc .markdown-body h2 { padding-bottom: .3rem; border-bottom: 1px solid var(--tblr-border-color, #e6e7e9); }
        .aboutsccglpi-doc .markdown-body p { margin-bottom: 1rem; }
        .aboutsccglpi-doc .markdown-body ul,
        .aboutsccglpi-doc .markdown-body ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        .aboutsccglpi-doc .markdown-body li { margin-bottom: .25rem; }
        .aboutsccglpi-doc .markdown-body blockquote {
            margin: 1rem 0; padding: .5rem 1rem;
            border-left: 4px solid var(--tblr-primary, #206bc4);
            background: var(--tblr-bg-surface-secondary, #f6f7f9);
            color: var(--tblr-secondary, #6c757d);
        }
        .aboutsccglpi-doc .markdown-body code {
            padding: .12em .4em; border-radius: 4px; font-size: .875em;
            background: var(--tblr-bg-surface-secondary, #f1f3f5);
        }
        .aboutsccglpi-doc .markdown-body pre {
            padding: 1rem; border-radius: 6px; overflow-x: auto;
            background: var(--tblr-bg-surface-secondary, #f1f3f5);
        }
        .aboutsccglpi-doc .markdown-body pre code { padding: 0; background: none; }
        .aboutsccglpi-doc .markdown-body table {
            width: auto; margin: 1rem 0; border-collapse: collapse;
        }
        .aboutsccglpi-doc .markdown-body th,
        .aboutsccglpi-doc .markdown-body td {
            padding: .4rem .75rem; border: 1px solid var(--tblr-border-color, #e6e7e9);
        }
        .aboutsccglpi-doc .markdown-body th { background: var(--tblr-bg-surface-secondary, #f6f7f9); }
        .aboutsccglpi-doc .markdown-body img { max-width: 100%; height: auto; }
        .aboutsccglpi-doc .markdown-body hr { margin: 1.75rem 0; }
        .aboutsccglpi-doc .markdown-body .heading-permalink { text-decoration: none; opacity: 0; margin-left: .35rem; }
        .aboutsccglpi-doc .markdown-body :hover > .heading-permalink { opacity: .5; }
        .aboutsccglpi-doc .markdown-body .table-of-contents {
            margin: 0 0 1.5rem; padding: 1rem 1rem 1rem 2rem; list-style: disc;
            background: var(--tblr-bg-surface-secondary, #f6f7f9); border-radius: 6px;
        }
        .aboutsccglpi-doc .markdown-body .table-of-contents a { text-decoration: none; }
        </style>
        CSS;
    }
}
