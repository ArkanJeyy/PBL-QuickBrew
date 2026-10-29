<?php
/**
 * Template bersama: bottom bar (opsional) dan penutup halaman.
 * $withBottomBar (bool, default true): isi bottom bar kosong sesuai desain Figma.
 */
?>
</div>
<?php if ($withBottomBar ?? true): ?>
<div class="bottom-bar" aria-hidden="true"></div>
<?php endif; ?>
</body>
</html>
