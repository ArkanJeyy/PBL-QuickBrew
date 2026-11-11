<?php
/**
 * Model pengguna staf (tabel: pengguna — role 'admin' dan 'kasir').
 */
final class PenggunaModel
{
    /**
     * Cari satu pengguna berdasarkan username (sensitif huruf besar/kecil sesuai kolom UNIQUE).
     *
     * @return array{id:int, nama:string, username:string, password:string, role:string}|null
     */
    public static function cariUsername(string $username): ?array
    {
        $username = trim($username);
        if ($username === '' || strlen($username) > 50) {
            return null;
        }

        $s = db()->prepare(
            "SELECT id, nama, username, password, role
               FROM pengguna
              WHERE username = ?
              LIMIT 1"
        );
        $s->execute([$username]);
        $row = $s->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id'       => (int) $row['id'],
            'nama'     => (string) $row['nama'],
            'username' => (string) $row['username'],
            'password' => (string) $row['password'],
            'role'     => (string) $row['role'],
        ];
    }

    /**
     * Perbarui hash password pengguna (dipakai saat password_needs_rehash atau CLI ganti_password).
     */
    public static function perbaruiPassword(int $id, string $hash): bool
    {
        $s = db()->prepare("UPDATE pengguna SET password = ? WHERE id = ?");
        $s->execute([$hash, $id]);
        return $s->rowCount() > 0;
    }
}
