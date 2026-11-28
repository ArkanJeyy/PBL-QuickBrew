<?php
/**
 * Model pengguna staf (tabel: pengguna — role 'admin' dan 'kasir').
 */
final class PenggunaModel
{
    /**
     * Cari satu pengguna berdasarkan username.
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
     * Perbarui hash password pengguna.
     */
    public static function perbaruiPassword(int $id, string $hash): bool
    {
        $s = db()->prepare(
            "UPDATE pengguna SET password = ? WHERE id = ?"
        );
        $s->execute([$hash, $id]);

        return $s->rowCount() > 0;
    }

    /**
     * Ambil seluruh pengguna tanpa password.
     */
    public static function semua(): array
    {
        $s = db()->query(
            "SELECT id, nama, username, role
               FROM pengguna
              ORDER BY id ASC"
        );

        return $s->fetchAll();
    }

    /**
     * Cari pengguna berdasarkan ID tanpa password.
     */
    public static function cariId(int $id): ?array
    {
        $s = db()->prepare(
            "SELECT id, nama, username, role
               FROM pengguna
              WHERE id = ?
              LIMIT 1"
        );
        $s->execute([$id]);

        $row = $s->fetch();

        return $row ?: null;
    }

    /**
     * Tambahkan pengguna baru.
     */
    public static function tambah(
        string $nama,
        string $username,
        string $passwordHash,
        string $role
    ): int {
        $s = db()->prepare(
            "INSERT INTO pengguna (nama, username, password, role)
             VALUES (?, ?, ?, ?)
             RETURNING id"
        );

        $s->execute([
            $nama,
            $username,
            $passwordHash,
            $role
        ]);

        return (int) $s->fetchColumn();
    }

    /**
     * Perbarui data pengguna.
     * Password hanya diubah jika passwordHash diberikan.
     */
    public static function ubah(
        int $id,
        string $nama,
        string $username,
        string $role,
        ?string $passwordHash = null
    ): bool {
        if ($passwordHash !== null) {
            $s = db()->prepare(
                "UPDATE pengguna
                    SET nama = ?, username = ?, password = ?, role = ?
                  WHERE id = ?"
            );

            $s->execute([
                $nama,
                $username,
                $passwordHash,
                $role,
                $id
            ]);
        } else {
            $s = db()->prepare(
                "UPDATE pengguna
                    SET nama = ?, username = ?, role = ?
                  WHERE id = ?"
            );

            $s->execute([
                $nama,
                $username,
                $role,
                $id
            ]);
        }

        return $s->rowCount() > 0;
    }

    /**
     * Hapus pengguna berdasarkan ID.
     */
    public static function hapus(int $id): bool
    {
        $s = db()->prepare(
            "DELETE FROM pengguna WHERE id = ?"
        );
        $s->execute([$id]);

        return $s->rowCount() > 0;
    }
}
