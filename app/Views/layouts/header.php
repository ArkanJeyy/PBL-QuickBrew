<?php
/**
 * Template bersama: <head>, pembuka <body>, dan (opsional) header merek.
 *
 * Variabel opsional dari halaman:
 *   $pageTitle       string  Judul halaman.
 *   $pageStyles      array   Nama file CSS di assets/css (tanpa .css).
 *   $pageScripts     array   Nama file JS di assets/js (tanpa .js).
 *   $pageClass       string  Kelas tambahan untuk wrapper .page.
 *   $withBrandHeader bool    Tampilkan header logo + nama merek (default true).
 *   $brandCta        array   Tombol di kanan header merek: ['label' => ..., 'href' => ...] (opsional).
 *   $brandTable      int     Nomor meja di kanan header merek (opsional).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/components/table-badge.php';

$pageTitle       = $pageTitle ?? APP_NAME;
$pageStyles      = $pageStyles ?? [];
$pageScripts     = $pageScripts ?? [];
$pageClass       = $pageClass ?? '';
$withBrandHeader = $withBrandHeader ?? true;
$brandCta        = $brandCta ?? null;
$brandTable      = $brandTable ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<?php foreach ($pageStyles as $style): ?>
  <link rel="stylesheet" href="<?= e(asset('css/' . $style . '.css')) ?>">
<?php endforeach; ?>
<?php foreach ($pageScripts as $script): ?>
  <script src="<?= e(asset('js/' . $script . '.js')) ?>" defer></script>
<?php endforeach; ?>
</head>
<body>
<div class="page<?= $pageClass !== '' ? ' ' . e($pageClass) : '' ?>">
<?php if ($withBrandHeader): ?>
  <header class="site-header">
    <a class="brand" href="index.php" aria-label="<?= e(APP_NAME) ?> - beranda">
      <span class="brand__logo">
        <img src="<?= e(asset('img/logo.png')) ?>" alt="">
      </span>
      <span class="brand__name"><?= e(APP_NAME) ?></span>
    </a>
<?php if ($brandTable !== null) { render_table_badge((int) $brandTable); } ?>
<?php if ($brandCta): ?>
    <a class="btn" href="<?= e($brandCta['href']) ?>"><?= e($brandCta['label']) ?></a>
<?php endif; ?>
  </header>
<?php endif; ?>
