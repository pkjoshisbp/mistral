@php
    $t = is_string($text ?? '') ? $text : '';
    // Numbered list normalisation — mirrors the JS linkify() logic in widget/script.blade.php
    // Case 1: number at end of previous line → push to new paragraph
    $t = preg_replace('/([^\n])\s*(\d+)\.\s*\n/', "$1\n\n$2. ", $t);
    // Case 2: number already starts a line but no blank line precedes it
    $t = preg_replace('/\n(\d+\.\s)/', "\n\n$1", $t);
    // Case 3: fully inline list — no newlines at all, e.g. "conversations) 2. Basic"
    $t = preg_replace('/([.!?:,)])\s+(\d+\.\s+)(?=\D)/u', "$1\n\n$2", $t);
    // Normalize Markdown links: [label](url) -> label (url) or just URL
    $t = preg_replace_callback('/\[(.*?)\]\(([^)]+)\)/s', function ($m) {
        $label = trim($m[1] ?? '');
        $inner = trim($m[2] ?? '');
        $url = '';
        if (preg_match('/https?:\/\/[^\s)]+/i', $inner, $um)) {
            $url = $um[0];
        } elseif (preg_match('/(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s)]*)?/i', $inner, $dm)) {
            $url = 'https://' . $dm[0];
        }
        if ($url !== '') {
            if ($label === '' || strcasecmp($label, $url) === 0 || preg_match('/^https?:\/\//i', $label)) return $url;
            return $label . ' (' . $url . ')';
        }
        return $label !== '' ? $label : $inner;
    }, $t);

    $processed = $t;
    $makePlaceholder = function($i) { return "##L{$i}##"; };
    $isPlaceholder = function($s) { return preg_match('/##L\d+##/', $s); };
    $links = [];
    $idx = 0;

    // Emails FIRST: keep as plain text (no mailto)
    $processed = preg_replace_callback('/(?<![\w])([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})(?![\w])/', function($m) use (&$links, &$idx, $makePlaceholder) {
        $email = $m[1];
        $placeholder = $makePlaceholder($idx);
        $links[$idx] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $idx++;
        return $placeholder;
    }, $processed);

    // Full URLs
    $processed = preg_replace_callback('/https?:\/\/[^\s<]+/i', function($m) use (&$links, &$idx, $makePlaceholder) {
        $full = $m[0];
        if (!preg_match('/^(.*?)([\.,!?)]?]?)$/', $full, $mm)) return $full;
        $url = $mm[1];
        $trail = substr($full, strlen($url));
        while(preg_match('/[\.,!?)]$/', $url)) {
            $trail = substr($url, -1) . $trail;
            $url = substr($url, 0, -1);
        }
        $placeholder = $makePlaceholder($idx);
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $isImage = preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|#|$)/i', $safeUrl);
        $links[$idx] = ($isImage
            ? '<img src="' . $safeUrl . '" alt="image" style="max-width:100%;height:auto;"/>'
            : '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeUrl . '</a>'
        ) . htmlspecialchars($trail, ENT_QUOTES, 'UTF-8');
        $idx++;
        return $placeholder;
    }, $processed);

    // Bare domains (use ~ as delimiter; escape ~ in character class)
    $processed = preg_replace_callback("~(^|[^@\\w:/>\\(\\[])(?!mailto:)(((?:[a-zA-Z0-9-]+\\.)+[a-zA-Z]{2,})(/[\\w\\-\\._\\~:/?#\\[\\]@!$&'()*+,;=%]*)?)~i", function($m) use (&$links, &$idx, $makePlaceholder, $isPlaceholder) {
        $left = $m[1];
        $full = $m[2];
        if ($isPlaceholder($full)) return $m[0];
        if ($left && preg_match('/@|[\\w]$/', $left)) return $m[0];
        $url = 'https://' . html_entity_decode($full, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $placeholder = $makePlaceholder($idx);
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeText = htmlspecialchars($full, ENT_QUOTES, 'UTF-8');
        $isImage = preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|#|$)/i', $safeText);
        $links[$idx] = $left . ($isImage
            ? '<img src="' . $safeUrl . '" alt="image" style="max-width:100%;height:auto;"/>'
            : '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeText . '</a>'
        );
        $idx++;
        return $placeholder;
    }, $processed);

    // Escape remaining content
    $processed = htmlspecialchars($processed, ENT_QUOTES, 'UTF-8');
    // Restore links
    foreach ($links as $i => $html) {
        $ph = '/' . preg_quote($makePlaceholder($i), '/') . '/';
        $processed = preg_replace($ph, $html, $processed);
    }
    // Preserve line breaks
    $processed = nl2br($processed);
@endphp
{!! $processed !!}
