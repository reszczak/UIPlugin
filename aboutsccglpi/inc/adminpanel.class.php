<?php
/**
 * Config panel: home page form, subpages list, subpage add/edit form, and
 * POST handling for all of the above.
 */

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PluginAboutsccglpiAdminPanel
{
    public function run(): void
    {
        $this->handlePost();

        $tab = (($_GET['tab'] ?? '') === 'subpages') ? 'subpages' : 'home';

        Html::header(PluginAboutsccglpiMenu::getMenuName(), $_SERVER['PHP_SELF'], 'config', 'plugins');

        echo "<div class='aboutsccglpi-config'>";
        $this->renderTabs($tab);

        if ($tab === 'subpages') {
            $this->renderSubpagesTab();
        } else {
            $this->renderHomeTab(PluginAboutsccglpiDocumentation::getHome());
        }

        echo "</div>";
        echo $this->styles();

        Html::footer();
    }

    /* ------------------------------ POST ------------------------------- */

    private function handlePost(): void
    {
        $self = PluginAboutsccglpiHtmlHelper::selfUrl();
        $doc  = new PluginAboutsccglpiDocumentation();

        if (isset($_POST['update'])) {
            $doc->saveHome((string) ($_POST['name'] ?? ''), (string) ($_POST['content'] ?? ''));
            Session::addMessageAfterRedirect(PluginAboutsccglpiHtmlHelper::t('Documentation saved successfully.'), true, INFO);
            Html::redirect($self . '?tab=home');
        }

        if (isset($_POST['add_subpage'])) {
            $id = $doc->addSubpage((string) ($_POST['name'] ?? ''), (string) ($_POST['content'] ?? ''));
            if ($id > 0) {
                Session::addMessageAfterRedirect(PluginAboutsccglpiHtmlHelper::t('Subpage added.'), true, INFO);
            } else {
                Session::addMessageAfterRedirect(PluginAboutsccglpiHtmlHelper::t('Page title is required.'), true, ERROR);
            }
            Html::redirect($self . '?tab=subpages');
        }

        if (isset($_POST['update_subpage'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $ok = $doc->updateSubpage($id, (string) ($_POST['name'] ?? ''), (string) ($_POST['content'] ?? ''));
            Session::addMessageAfterRedirect(
                $ok ? PluginAboutsccglpiHtmlHelper::t('Subpage updated.') : PluginAboutsccglpiHtmlHelper::t('Page title is required.'),
                true,
                $ok ? INFO : ERROR
            );
            Html::redirect($self . '?tab=subpages');
        }

        if (isset($_POST['delete_subpage'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $ok = $doc->deleteSubpage($id);
            if ($ok) {
                Session::addMessageAfterRedirect(PluginAboutsccglpiHtmlHelper::t('Subpage deleted.'), true, INFO);
            }
            Html::redirect($self . '?tab=subpages');
        }
    }

    /* ------------------------------ tabs -------------------------------- */

    private function renderTabs(string $active): void
    {
        $t    = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);
        $self = PluginAboutsccglpiHtmlHelper::selfUrl();

        echo "<ul class='nav nav-tabs mb-3'>";
        echo "<li class='nav-item'><a class='nav-link" . ($active === 'home' ? ' active' : '') . "' href='"
            . htmlescape($self) . "?tab=home'>" . htmlescape($t('Home page')) . "</a></li>";
        echo "<li class='nav-item'><a class='nav-link" . ($active === 'subpages' ? ' active' : '') . "' href='"
            . htmlescape($self) . "?tab=subpages'>" . htmlescape($t('Subpages')) . "</a></li>";
        echo "</ul>";
    }

    /* --------------------------- home tab -------------------------------- */

    private function renderHomeTab(PluginAboutsccglpiDocumentation $doc): void
    {
        $t       = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);
        $self    = PluginAboutsccglpiHtmlHelper::selfUrl();
        $viewUrl = PluginAboutsccglpiHtmlHelper::viewUrl();
        $name    = (string) ($doc->fields['name'] ?? '');
        $content = (string) ($doc->fields['content'] ?? '');
        $preview = $doc->getRenderedHtml();

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
        echo $this->markdownHint();
        echo "</div>";

        echo "<div class='col-lg-6 mb-3'>";
        echo "<label class='form-label d-flex align-items-center'>";
        echo "<i class='ti ti-eye me-1'></i> " . htmlescape($t('Preview (last saved)'));
        echo "</label>";
        echo $this->previewPane($preview);
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
    }

    /* ------------------------- subpages tab ------------------------------ */

    private function renderSubpagesTab(): void
    {
        $edit = $_GET['edit'] ?? null;

        if ($edit !== null) {
            $this->renderSubpageForm($edit === 'new' ? null : (int) $edit);
            return;
        }

        $this->renderSubpagesList();
    }

    private function renderSubpagesList(): void
    {
        $t     = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);
        $self  = PluginAboutsccglpiHtmlHelper::selfUrl();
        $pages = PluginAboutsccglpiDocumentation::getSubpages();

        echo "<div class='card'>";
        echo "<div class='card-header d-flex align-items-center'>";
        echo "<h2 class='card-title mb-0'>" . htmlescape($t('Subpages')) . "</h2>";
        echo "<a href='" . htmlescape($self) . "?tab=subpages&edit=new' class='btn btn-sm btn-primary ms-auto'>";
        echo "<i class='ti ti-plus'></i> " . htmlescape($t('Add subpage'));
        echo "</a>";
        echo "</div>";

        echo "<div class='card-body'>";
        if (empty($pages)) {
            echo "<div class='text-muted text-center py-5'>" . htmlescape($t('No subpages yet.')) . "</div>";
        } else {
            echo "<table class='table table-hover'>";
            echo "<thead><tr><th>" . htmlescape($t('Title')) . "</th><th>" . htmlescape($t('Last modified'))
                . "</th><th class='text-end'>" . htmlescape($t('Actions')) . "</th></tr></thead><tbody>";
            foreach ($pages as $page) {
                $id      = (int) $page['id'];
                $editUrl = $self . '?tab=subpages&edit=' . $id;

                echo "<tr>";
                echo "<td>" . htmlescape($page['name']) . "</td>";
                echo "<td class='text-muted'>" . htmlescape(Html::convDateTime($page['date_mod'])) . "</td>";
                echo "<td class='text-end'>";
                echo "<a href='" . htmlescape(PluginAboutsccglpiHtmlHelper::pageUrl($id)) . "' class='btn btn-sm btn-outline-secondary' target='_blank'>"
                    . "<i class='ti ti-external-link'></i></a> ";
                echo "<button type='button' class='btn btn-sm btn-outline-secondary aboutsccglpi-copy-link' data-copy-url='"
                    . htmlescape(PluginAboutsccglpiHtmlHelper::pageUrlAbsolute($id)) . "' title='" . htmlescape($t('Copy link')) . "'>"
                    . "<i class='ti ti-link'></i></button> ";
                echo "<a href='" . htmlescape($editUrl) . "' class='btn btn-sm btn-outline-primary'>" . _x('button', 'Edit') . "</a> ";
                echo "<form method='post' action='" . htmlescape($self) . "' class='d-inline'"
                    . " onsubmit='return confirm(" . htmlescape(json_encode($t('Delete this subpage?'))) . ");'>";
                echo PluginAboutsccglpiHtmlHelper::csrfHidden();
                echo Html::hidden('id', ['value' => $id]);
                echo "<button type='submit' name='delete_subpage' class='btn btn-sm btn-outline-danger'>"
                    . _x('button', 'Delete permanently') . "</button>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
        echo "</div>"; // card-body
        echo "</div>"; // card
    }

    private function renderSubpageForm(?int $id): void
    {
        $t    = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);
        $self = PluginAboutsccglpiHtmlHelper::selfUrl();

        $page = null;
        if ($id !== null) {
            $page = PluginAboutsccglpiDocumentation::getPage($id);
            if ($page === null) {
                throw new NotFoundHttpException();
            }
        }

        $name    = $page ? (string) $page->fields['name'] : '';
        $content = $page ? (string) $page->fields['content'] : '';
        $preview = $page ? $page->getRenderedHtml() : '';
        $action  = $page ? 'update_subpage' : 'add_subpage';

        echo "<form method='post' action='" . htmlescape($self) . "'>";
        echo PluginAboutsccglpiHtmlHelper::csrfHidden();
        if ($page) {
            echo Html::hidden('id', ['value' => $id]);
        }
        echo "<div class='card'>";

        echo "<div class='card-header d-flex align-items-center'>";
        echo "<h2 class='card-title mb-0'>" . htmlescape($page ? $t('Edit subpage') : $t('Add subpage')) . "</h2>";
        echo "<a href='" . htmlescape($self) . "?tab=subpages' class='btn btn-sm btn-outline-secondary ms-auto'>"
            . _x('button', 'Cancel') . "</a>";
        echo "</div>";

        echo "<div class='card-body'>";
        echo "<div class='mb-3'>";
        echo "<label class='form-label' for='oap-sub-name'>" . htmlescape($t('Title')) . "</label>";
        echo "<input type='text' class='form-control' id='oap-sub-name' name='name' value='" . htmlescape($name) . "' required>";
        echo "</div>";

        echo "<div class='row'>";
        echo "<div class='col-lg-6 mb-3'>";
        echo "<label class='form-label d-flex align-items-center' for='oap-sub-content'>";
        echo "<i class='ti ti-markdown me-1'></i> " . htmlescape($t('Documentation (Markdown)'));
        echo "</label>";
        echo "<textarea class='form-control' id='oap-sub-content' name='content' rows='24' spellcheck='false'"
            . " style='font-family: var(--tblr-font-monospace, monospace); font-size: .875rem;'"
            . " placeholder='# Heading&#10;&#10;Paste your documentation here in Markdown format…'>"
            . htmlescape($content) . "</textarea>";
        echo $this->markdownHint();
        echo "</div>";

        echo "<div class='col-lg-6 mb-3'>";
        echo "<label class='form-label d-flex align-items-center'>";
        echo "<i class='ti ti-eye me-1'></i> " . htmlescape($t('Preview (last saved)'));
        echo "</label>";
        echo $this->previewPane($preview);
        echo "</div>";
        echo "</div>"; // row
        echo "</div>"; // card-body

        echo "<div class='card-footer text-end'>";
        echo "<button type='submit' name='{$action}' class='btn btn-primary'>";
        echo "<i class='ti ti-device-floppy'></i> " . _x('button', 'Save');
        echo "</button>";
        echo "</div>";

        echo "</div>"; // card
        echo "</form>";
    }

    /* ------------------------------ shared -------------------------------- */

    /** Markdown syntax hint block, shared between the home and subpage forms. */
    private function markdownHint(): string
    {
        $t = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);

        $html  = "<div class='form-hint mt-2'>";
        $html .= htmlescape($t('Paste the content in Markdown format. Supported, among others:')) . " ";
        $html .= "<code>#</code> " . htmlescape($t('headings')) . ", ";
        $html .= "<code>**" . htmlescape($t('bold')) . "**</code>, <code>*" . htmlescape($t('italics')) . "*</code>, "
            . "<code>- " . htmlescape($t('lists')) . "</code>, <code>[link](https://…)</code>, "
            . "<code>`" . htmlescape($t('code')) . "`</code>, " . htmlescape($t('tables and quotes')) . " ";
        $html .= "(" . htmlescape($t('GitHub Flavored Markdown')) . "). ";
        $html .= htmlescape($t('The table of contents and heading anchors are generated automatically.'));
        $html .= "</div>";
        return $html;
    }

    /** Rendered-preview pane, shared between the home and subpage forms. */
    private function previewPane(string $preview): string
    {
        $t = fn(string $s) => PluginAboutsccglpiHtmlHelper::t($s);

        $html = "<div class='border rounded p-3 aboutsccglpi-doc' style='height: 585px; overflow-y: auto;'>";
        if (trim($preview) === '') {
            $html .= "<div class='text-muted text-center py-5'>" . htmlescape($t('No content yet — paste Markdown and save.')) . "</div>";
        } else {
            $html .= "<div class='markdown-body'>{$preview}</div>";
        }
        $html .= "</div>";
        return $html;
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
