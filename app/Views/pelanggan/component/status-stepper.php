<?php
/**
 * Komponen: stepper 4 langkah Status Pesanan (Pesanan Diterima, Pembayaran, Diproses, Selesai).
 * Data langkah dari PemesananModel::statusView(): label, icon (order|pay|process|done), status, time.
 *   status: done (lingkaran cokelat terisi) | current (garis cokelat) | todo (abu-abu)
 * Ikon SVG di public/assets/images/icons/icon-status-<icon>.svg ditanam langsung (inline) agar warnanya
 * mengikuti CSS (stroke="currentColor").
 */
function render_status_icon(string $name): void
{
    $file = APP_ROOT . '/public/assets/images/icons/icon-status-' . preg_replace('/[^a-z]/', '', $name) . '.svg';
    echo is_file($file) ? file_get_contents($file) : '';
}

function render_status_stepper(array $steps): void
{
    ?>
      <ol class="status-steps" aria-label="Tahapan pesanan">
<?php foreach ($steps as $step):
    $state = in_array($step['status'], ['done', 'current', 'todo'], true) ? $step['status'] : 'todo';
    $srText = ['done' => 'selesai', 'current' => 'sedang berlangsung', 'todo' => 'belum'][$state];
?>
        <li class="status-step status-step--<?= e($state) ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
          <span class="status-step__icon" aria-hidden="true"><?php render_status_icon($step['icon']); ?></span>
          <span class="status-step__label"><?= e($step['label']) ?><span class="visually-hidden"> (<?= e($srText) ?>)</span></span>
<?php if ($step['time'] !== ''): ?>
          <span class="status-step__time"><?= e($step['time']) ?></span>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ol>
<?php
}
