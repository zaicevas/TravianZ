<?php
// Incoming attacks/raids (no scouts, no reinforcements) on any of the player's villages:
// countdown toast, flashing village link, and a beep the first time each attack is seen.
// Only while playable (outside the window nobody can react anyway).
// ponytail: refreshed on page load only; add ajax polling if players sit on one page.
if (!RoundControl::isPlayable() || empty($session->villages)) return;
$ids = implode(',', array_map('intval', $session->villages));
$incoming = $database->query_return("SELECT m.moveid, m.`to`, m.endtime, v.name FROM " . TB_PREFIX . "movement m
    JOIN " . TB_PREFIX . "attacks a ON a.id = m.ref JOIN " . TB_PREFIX . "vdata v ON v.wref = m.`to`
    WHERE m.`to` IN ($ids) AND m.proc = 0 AND m.sort_type = 3 AND a.attack_type NOT IN (1, 2) AND m.endtime > " . time() . "
    ORDER BY m.endtime LIMIT 5");
if (!$incoming) return;
?>
<div id="atk_alert"><?php foreach ($incoming as $a) { ?>
<a href="build.php?newdid=<?php echo (int) $a['to']; ?>&amp;id=39" data-id="<?php echo (int) $a['moveid']; ?>" data-vid="<?php echo (int) $a['to']; ?>" data-left="<?php echo $a['endtime'] - time(); ?>">&#9876; <?php echo sprintf(RND_ATTACK_INCOMING, htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8')); ?> <b></b></a>
<?php } ?></div>
<style>
#atk_alert { position: fixed; right: 16px; bottom: 16px; z-index: 9999; display: flex; flex-direction: column; gap: 6px; }
#atk_alert a { background: #b00; color: #fff; padding: 8px 12px; border-radius: 4px; font-weight: bold; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,.4); animation: atkflash 1s step-start infinite; }
#atk_alert a b { font-variant-numeric: tabular-nums; }
.atk_flash { color: #c00 !important; font-weight: bold; animation: atkflash 1s step-start infinite; }
@keyframes atkflash { 50% { opacity: .35; } }
</style>
<script>
(function () {
    var box = document.getElementById('atk_alert'), t0 = Date.now(), fresh = false, seen = [];
    try { seen = JSON.parse(localStorage.getItem('atk_seen') || '[]'); } catch (e) {}
    box.querySelectorAll('a').forEach(function (a) {
        document.querySelectorAll('a[href*="newdid=' + a.dataset.vid + '"]').forEach(function (l) {
            if (!box.contains(l)) l.classList.add('atk_flash');
        });
        if (seen.indexOf(a.dataset.id) < 0) { seen.push(a.dataset.id); fresh = true; }
    });
    try { localStorage.setItem('atk_seen', JSON.stringify(seen.slice(-50))); } catch (e) {}
    function beep() {
        try {
            var c = new (window.AudioContext || window.webkitAudioContext)();
            [0, .35, .7].forEach(function (d) {
                var o = c.createOscillator(), g = c.createGain();
                o.frequency.value = 880; g.gain.value = .15; o.connect(g); g.connect(c.destination);
                o.start(c.currentTime + d); o.stop(c.currentTime + d + .2);
            });
            return c.state === 'running';
        } catch (e) { return true; }
    }
    // Browsers block sound until the page has had a click; retry on the first one.
    if (fresh && !beep()) document.addEventListener('click', beep, {once: true});
    (function tick() {
        box.querySelectorAll('a').forEach(function (a) {
            var s = Math.max(0, a.dataset.left - Math.floor((Date.now() - t0) / 1000));
            var m = Math.floor(s / 60) % 60, x = s % 60;
            a.querySelector('b').textContent = Math.floor(s / 3600) + ':' + (m < 10 ? '0' : '') + m + ':' + (x < 10 ? '0' : '') + x;
        });
        setTimeout(tick, 1000);
    })();
})();
</script>
