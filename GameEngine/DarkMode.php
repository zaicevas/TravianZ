<?php
#################################################################################
##  DarkMode.php - toggleable dark theme for every HTML page (light default).  ##
##                                                                             ##
##  The legacy stylesheets differ per page, so instead of restyling them the   ##
##  whole page is colour-inverted (contrast is preserved, hue kept by the      ##
##  180deg rotation) and every picture is inverted back: <img> (which also     ##
##  covers the x.gif sprites), video/canvas/iframe, and any element painted    ##
##  with a background image (marked .tz-keep by the script below).             ##
##  The choice lives in localStorage and is applied in <head>, before paint.   ##
#################################################################################

// Not in the admin panel.
if (PHP_SAPI !== 'cli' && !defined('TZ_DARK_MODE') && stripos($_SERVER['SCRIPT_NAME'] ?? '', '/Admin/') === false) {
    define('TZ_DARK_MODE', 1);

    ob_start(function ($html) {
        $i = stripos($html, '</head>');
        if ($i === false || strpos($html, 'id="tz_dark_css"') !== false) {
            return $html;
        }
        $snippet = <<<'HTML'
<style id="tz_dark_css">
html.tz-dark { filter: invert(1) hue-rotate(180deg); background: #fff; }
html.tz-dark img, html.tz-dark video, html.tz-dark canvas, html.tz-dark iframe,
html.tz-dark embed, html.tz-dark object, html.tz-dark .tz-keep { filter: invert(1) hue-rotate(180deg); }
html.tz-dark .tz-keep img, html.tz-dark .tz-keep .tz-keep, html.tz-dark .tz-keep video,
html.tz-dark .tz-keep canvas, html.tz-dark .tz-keep iframe { filter: none; }
/* the village centre's name is drawn over the (light) village picture */
html.tz-dark #content.village2 > h1 { filter: invert(1) hue-rotate(180deg); }
#tz_dark_toggle { position: fixed; left: 10px; bottom: 10px; z-index: 100000; width: 32px; height: 32px;
    padding: 0; border: 1px solid #999; border-radius: 50%; background: #fff; color: #333;
    font: 17px/30px Arial, sans-serif; text-align: center; cursor: pointer; opacity: .75;
    box-shadow: 0 1px 4px rgba(0,0,0,.3); }
#tz_dark_toggle:hover, #tz_dark_toggle:focus { opacity: 1; }
</style>
<script id="tz_dark_js">
(function () {
    var root = document.documentElement, dark = false;
    try { dark = localStorage.getItem('tz_theme') === 'dark'; } catch (e) {}
    if (dark) root.classList.add('tz-dark');

    // Elements painted with a background picture get inverted back, unless an
    // ancestor already is (that would double it). Only text-free ones (icons,
    // artwork) and the picture containers below: inverting back a panel that
    // holds text would leave dark text on the dark page behind it. Other
    // panels stay inverted, their texture negative but the text readable.
    var PICTURES = '#header, #navigation, #village_map, #map_content, #mapContainer, .map, #qstbg, #content.village3';
    function mark() {
        if (!root.classList.contains('tz-dark')) return;
        var all = document.body ? document.body.getElementsByTagName('*') : [];
        for (var i = 0; i < all.length; i++) {
            var el = all[i], bg;
            if (el.classList.contains('tz-keep') || /^(IMG|VIDEO|CANVAS|IFRAME|SCRIPT|STYLE|BR|OPTION)$/.test(el.tagName)) continue;
            bg = getComputedStyle(el).backgroundImage;
            if (!bg || bg.indexOf('url(') < 0) continue;
            if (/\S/.test(el.textContent) && !el.matches(PICTURES)) continue;
            if (el.parentNode.closest && el.parentNode.closest('.tz-keep')) continue;
            el.classList.add('tz-keep');
        }
    }
    var queued = false;
    function schedule() { if (!queued) { queued = true; setTimeout(function () { queued = false; mark(); }, 50); } }

    document.addEventListener('DOMContentLoaded', function () {
        var b = document.createElement('button');
        b.type = 'button';
        b.id = 'tz_dark_toggle';
        function label() {
            var on = root.classList.contains('tz-dark');
            b.textContent = on ? '☀' : '☾';
            b.title = on ? 'Light mode' : 'Dark mode';
            b.setAttribute('aria-label', b.title);
        }
        b.onclick = function () {
            root.classList.toggle('tz-dark');
            try { localStorage.setItem('tz_theme', root.classList.contains('tz-dark') ? 'dark' : 'light'); } catch (e) {}
            label(); mark();
        };
        label();
        document.body.appendChild(b);
        mark();
        if (window.MutationObserver) new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
    });
    window.addEventListener('load', mark);
})();
</script>
HTML;
        return substr($html, 0, $i) . $snippet . substr($html, $i);
    });
}
