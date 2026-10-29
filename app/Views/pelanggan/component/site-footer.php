<?php
/**
 * Komponen: footer situs (nama merek + hak cipta).
 */
require_once __DIR__ . '/../config.php';

function render_site_footer(): void
{
    ?>
  <footer class="site-footer">
    <p class="site-footer__brand"><?= e(APP_NAME) ?></p>
    <p class="site-footer__copy">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
  </footer>
<?php
}
