<?php
class PythonService
{
    private const BASE_URL = 'http://127.0.0.1:5000';

    public static function health(): array
    {
        return self::request('/api/ml/health', null);
    }

    public static function recommend(array $stats): array
    {
        return self::request('/api/ml/recommend', $stats);
    }

    private static function request(string $path, ?array $payload): array
    {
        $method = $payload === null ? 'GET' : 'POST';
        $options = ['http' => [
            'method' => $method,
            'timeout' => 3,
            'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        ]];
        if ($payload !== null) {
            $options['http']['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        $response = @file_get_contents(self::BASE_URL . $path, false, stream_context_create($options));
        if ($response === false) {
            return ['status'=>'offline','difficulty'=>$stats['difficulty'] ?? 'medium','message'=>'ML offline: se mantiene la dificultad actual.'];
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status'=>'error','difficulty'=>'medium'];
    }
}
