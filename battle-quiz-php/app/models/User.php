<?php
require_once __DIR__ . '/../core/Database.php';

class User
{
    public static function findOrCreate(string $username): array
    {
        $username = trim($username);
        if ($username === '' || mb_strlen($username) > 50) {
            throw new InvalidArgumentException('El nombre debe tener entre 1 y 50 caracteres.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, nickname, puntos_totales, partidas_jugadas, victorias, derrotas, mejor_racha, creado_en
             FROM usuarios WHERE nickname = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user) {
            return self::normalize($user);
        }

        $stmt = $pdo->prepare('INSERT INTO usuarios (nickname) VALUES (?)');
        $stmt->execute([$username]);
        return [
            'id' => (int)$pdo->lastInsertId(),
            'username' => $username,
            'nickname' => $username,
            'total_score' => 0,
            'puntos_totales' => 0,
            'partidas_jugadas' => 0,
            'victorias' => 0,
            'derrotas' => 0,
            'mejor_racha' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public static function refresh(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nickname, puntos_totales, partidas_jugadas, victorias, derrotas, mejor_racha, creado_en
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user ? self::normalize($user) : null;
    }

    private static function normalize(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'username' => $user['nickname'],
            'nickname' => $user['nickname'],
            'total_score' => (int)$user['puntos_totales'],
            'puntos_totales' => (int)$user['puntos_totales'],
            'partidas_jugadas' => (int)$user['partidas_jugadas'],
            'victorias' => (int)$user['victorias'],
            'derrotas' => (int)$user['derrotas'],
            'mejor_racha' => (int)$user['mejor_racha'],
            'created_at' => $user['creado_en'],
        ];
    }
}
