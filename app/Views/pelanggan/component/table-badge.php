<?php
/**
 * Komponen: label/tag nomor meja di header (ikon meja + "Meja N").
 * $number = meja.no_meja (VARCHAR) dari hasil scan QR (?meja=<kode_qr>) yang disimpan di session.
 * Jika belum scan QR ($number === null), tampilkan tag "Pilih Meja" yang mengarah ke halaman testing QR (?page=qr).
 */
function render_table_badge(?string $number): void
{
    $hasTable = $number !== null && $number !== '';
    $label    = $hasTable ? 'Meja ' . $number : 'Pilih Meja';
    $title    = $hasTable
        ? 'Nomor meja aktif: Meja ' . $number . ' (klik untuk ganti / tes QR)'
        : 'Belum scan QR meja — klik untuk memilih atau scan QR meja';
    ?>
    <a class="table-badge<?= $hasTable ? '' : ' table-badge--unset' ?>"
       href="<?= e(url('qr')) ?>"
       data-table-badge
       data-table-number="<?= e((string) $number) ?>"
       title="<?= e($title) ?>"
       aria-label="<?= e($title) ?>">
      <img class="table-badge__icon" src="<?= e(asset('images/icons/icon-coffee-table.svg')) ?>" alt="">
      <span class="table-badge__text"><?= e($label) ?></span>
    </a>
<?php
}

