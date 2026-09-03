<?php
class PluginConfigfilessccglpiHtmlExtractor
{
    private const STRIPPED_NAV_CLASSES = ['scc_snap_nav', 'scc_log_nav'];

    private const STRIPPED_TAGS = [
        'script', 'link', 'style', 'meta', 'base',
        'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'area',
    ];

    private const UNWRAPPED_TAGS = ['form'];

    private const SCRIPT_SCHEMES = ['javascript:', 'vbscript:'];

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

        foreach ($this->collectByLocalName($dom, self::STRIPPED_TAGS) as $element) {
            $element->parentNode?->removeChild($element);
        }

        $this->unwrapElements($dom);
        $this->stripDangerousAttributes($dom);
        $this->stripNavigation($dom);
        $this->stripLeavingLinks($dom);

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

    private function collectByLocalName(DOMDocument $dom, array $names): array
    {
        $found = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            $name  = strtolower($element->nodeName);
            $colon = strrpos($name, ':');
            if ($colon !== false) {
                $name = substr($name, $colon + 1);
            }
            if (in_array($name, $names, true)) {
                $found[] = $element;
            }
        }

        return $found;
    }

    private function unwrapElements(DOMDocument $dom): void
    {
        foreach ($this->collectByLocalName($dom, self::UNWRAPPED_TAGS) as $element) {
            $parent = $element->parentNode;
            if ($parent === null) {
                continue;
            }
            while ($element->firstChild !== null) {
                $parent->insertBefore($element->firstChild, $element);
            }
            $parent->removeChild($element);
        }
    }

    private function stripDangerousAttributes(DOMDocument $dom): void
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $attribute = $element->attributes->item($i);
                if (stripos($attribute->name, 'on') === 0 || $this->isScriptUrl($attribute->value)) {
                    $element->removeAttribute($attribute->name);
                }
            }
        }
    }

    private function isScriptUrl(string $value): bool
    {
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $value));
        foreach (self::SCRIPT_SCHEMES as $scheme) {
            if (str_starts_with($normalized, $scheme)) {
                return true;
            }
        }

        return false;
    }

    private function stripNavigation(DOMDocument $dom): void
    {
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
    }

    private function stripLeavingLinks(DOMDocument $dom): void
    {
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if ($href !== '' && $href[0] !== '#') {
                $anchor->removeAttribute('href');
            }
        }
    }

    private function embedCss(): string
    {
        $path = __DIR__ . '/../public/css/embed.css';
        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
