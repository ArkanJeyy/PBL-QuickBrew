<?php
// Arahkan akses ke root proyek menuju front controller di public/ (query string ikut dibawa, mis. ?meja=MEJA01).
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: public/' . ($qs !== '' ? '?' . $qs : ''));
exit;

