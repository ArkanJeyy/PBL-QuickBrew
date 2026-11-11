<?php
/**
 * View placeholder halaman login staf (form polos, ber-CSRF, tanpa styling)
 * untuk diganti oleh tim front-end.
 *
 * Variabel dari AuthController::login():
 *   $error     string|null  Pesan kesalahan login
 *   $username  string       Isian username sebelumnya
 *   $redirect  string       Path tujuan balik internal (opsional)
 *   $csrfToken string       Token CSRF sesi
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Staf - <?= e(APP_NAME) ?></title>
</head>
<body>
  <main>
    <h1>Login Staf <?= e(APP_NAME) ?></h1>
<?php if (!empty($error)): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>
    <form method="post" action="<?= e(url('login')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? csrf_token()) ?>">
<?php if (!empty($redirect)): ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
<?php endif; ?>
      <div>
        <label for="login-username">Username</label>
        <input type="text" id="login-username" name="username" value="<?= e($username ?? '') ?>" maxlength="50" autocomplete="username" required>
      </div>
      <div>
        <label for="login-password">Password</label>
        <input type="password" id="login-password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit">Masuk</button>
    </form>
  </main>
</body>
</html>
