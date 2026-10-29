<?php
/**
 * Komponen: label nomor meja di header (ikon meja + "Meja N").
 */
require_once __DIR__ . '/../config.php';

function render_table_badge(int $number): void
{
    ?>
    <div class="table-badge">
      <img class="table-badge__icon" src="<?= e(asset('icons/icon-coffee-table.svg')) ?>" alt="">
      <span class="table-badge__text">Meja <?= $number ?></span>
    </div>
<?php
}
