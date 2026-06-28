<?php
/** Render flash toast messages from session. Include before </body>. */
declare(strict_types=1);
use App\Auth\Flash;
$flashes = Flash::get();
if (empty($flashes)) return;
?>
<div class="flash-toast" id="flash-toast">
<?php foreach ($flashes as $f): ?>
    <div class="flash-msg flash-<?= htmlspecialchars($f['type'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($f['message'], ENT_QUOTES, 'UTF-8') ?>
        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Zamknij">&times;</button>
    </div>
<?php endforeach; ?>
</div>
<script>
(function () {
    var toasts = document.querySelectorAll('.flash-msg');
    toasts.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 320);
        }, 4000);
    });
})();
</script>

