<?php
class PluginConfigfilessccglpiHtmlExtractor
{
    private const STRIPPED_NAV_CLASSES = ['scc_snap_nav', 'scc_log_nav'];

    public function buildEmbeddableDocument(string $rawHtml): string
    {
        $body = $this->extractBody($rawHtml);
        $css  = $this->embedCss();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<base href="about:srcdoc">'
            . '<style>' . $css . '</style>'
            . '</head><body><div class="scc-embed">' . $body . '</div></body></html>';
    }

    private function extractBody(string $rawHtml): string
    {
        if (trim($rawHtml) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $rawHtml,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return '';
        }

        foreach (['script', 'link', 'style'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                $node->parentNode?->removeChild($node);
            }
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $name = $element->attributes->item($i)->name;
                if (stripos($name, 'on') === 0) {
                    $element->removeAttribute($name);
                }
            }
        }

        $navDivs = [];
        foreach ($dom->getElementsByTagName('div') as $div) {
            $class = strtolower(trim($div->getAttribute('class')));
            if (in_array($class, self::STRIPPED_NAV_CLASSES, true)) {
                $navDivs[] = $div;
            }
        }
        foreach ($navDivs as $div) {
            $div->parentNode?->removeChild($div);
        }

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if ($href !== '' && $href[0] !== '#') {
                $anchor->removeAttribute('href');
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }

        $html = '';
        foreach ($body->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    private function embedCss(): string
    {
        $path = __DIR__ . '/../public/css/embed.css';
        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
