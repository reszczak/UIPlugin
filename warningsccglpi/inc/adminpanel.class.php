<?php
class PluginWarningsccglpiAdminPanel
{
    private PluginWarningsccglpiSettings $settings;

    public function __construct(?PluginWarningsccglpiSettings $settings = null)
    {
        $this->settings = $settings ?? new PluginWarningsccglpiSettings();
    }

    public function run(): void
    {
        $self = $this->selfUrl();

        $this->handlePost($self);

        Html::header('warningSCCGLPI', $self, 'config', 'plugins');
        $this->renderForm($self);
        Html::footer();
    }

    private function handlePost(string $self): void
    {
        if (isset($_POST['save_config'])) {
            $this->settings->save($_POST);
            Session::addMessageAfterRedirect(__('Configuration saved successfully.'));
            Html::redirect($self);
        }
    }

    private function renderForm(string $self): void
    {
        $t   = fn(string $s) => __($s, 'warningsccglpi');
        $cfg = $this->settings->get();
        $raw = $this->settings->getRaw();
        $card = "style='max-width:600px;margin:1rem auto'";

        echo "<form method='post' action='" . htmlescape($self) . "' id='warningsccglpi-config-form'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<div class='card' {$card}><div class='card-body'>";
        echo "<h3 class='mb-3'>warningSCCGLPI</h3>";
        echo "<p class='text-muted'>" . htmlescape($t(
            'Persistent banner and colored border shown on every page, visible only to users '
            . 'with the config/UPDATE right — the ones who can actually make changes.'
        )) . "</p>";

        echo "<div class='mb-3'><label class='form-label'>" . htmlescape($t('Banner text')) . "</label>";
        echo "<input type='text' class='form-control' name='label' id='warningsccglpi-label' "
            . "value='" . htmlescape((string) ($raw['label'] ?? '')) . "' placeholder='" . htmlescape($cfg['label']) . "'></div>";

        echo "<div class='mb-3'><label class='form-label'>" . htmlescape($t('Banner / border color')) . "</label>";
        echo "<input type='color' class='form-control form-control-color' name='color' id='warningsccglpi-color' value='" . htmlescape($cfg['color']) . "'></div>";

        echo "<div class='mb-3'><div class='form-label'>" . htmlescape($t('Preview')) . "</div>";
        echo "<div id='warningsccglpi-preview' style='border-radius:6px;padding:8px 12px;color:#fff;font-weight:700;text-transform:uppercase;font-size:13px;letter-spacing:.04em'></div></div>";

        echo "<button type='submit' name='save_config' value='1' class='btn btn-primary'>" . htmlescape($t('Save configuration')) . "</button>";
        echo "</div></div></form>";

        echo $this->renderPreviewScript();
    }

    private function renderPreviewScript(): string
    {
        return <<<'HTML'
        <script>
        (function () {
            var labelInput = document.getElementById('warningsccglpi-label');
            var colorInput = document.getElementById('warningsccglpi-color');
            var preview    = document.getElementById('warningsccglpi-preview');

            function updatePreview() {
                preview.style.background = colorInput.value;
                preview.textContent = labelInput.value.trim() || labelInput.placeholder;
            }

            labelInput.addEventListener('input', updatePreview);
            colorInput.addEventListener('input', updatePreview);
            updatePreview();
        })();
        </script>
        HTML;
    }

    private function selfUrl(): string
    {
        global $CFG_GLPI;
        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/warningsccglpi/front/config.form.php';
    }
}
