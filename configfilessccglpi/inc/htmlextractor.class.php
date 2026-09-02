<?php
class PluginConfigfilessccglpiHtmlExtractor
{
    /**
     * @param array<string,string> $navLinks map of lowercase link label (e.g. 'home',
     *        'logbook', 'configuration') to the absolute URL it should point to.
     */
    public function buildEmbeddableDocument(string $rawHtml, array $navLinks = []): string
    {
        $body = $this->extractBody($rawHtml, $navLinks);
        $css  = $this->embedCss();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<base href="about:srcdoc">'
            . '<style>' . $css . '</style>'
            . '</head><body><div class="scc-embed">' . $body . '</div></body></html>';
    }

    private function extractBody(string $rawHtml, array $navLinks): string
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

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if ($href !== '' && $href[0] !== '#') {
                $anchor->removeAttribute('href');
            }
        }

        if (!empty($navLinks)) {
            foreach ($dom->getElementsByTagName('a') as $anchor) {
                $label = strtolower(trim($anchor->textContent));
                if (isset($navLinks[$label])) {
                    $anchor->setAttribute('href', $navLinks[$label]);
                    $anchor->setAttribute('target', '_top');
                }
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
