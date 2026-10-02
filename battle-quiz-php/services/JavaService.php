<?php
class JavaService
{
    private const BASE_URL = 'http://127.0.0.1:8081/api/battle';

    public static function health(): array
    {
        return self::request('GET', self::BASE_URL . '/health');
    }

    public static function startBattle(string $difficulty = 'medium'): array
    {
        $difficulty = in_array($difficulty, ['easy','medium','hard'], true) ? $difficulty : 'medium';
        return self::request('GET', self::BASE_URL . '/start?difficulty=' . urlencode($difficulty));
    }

    public static function answer(int $answer): array
    {
        return self::request('POST', self::BASE_URL . '/answer', ['answer' => $answer]);
    }

    public static function adapt(string $difficulty): array
    {
        return self::request('POST', self::BASE_URL . '/adapt', ['difficulty' => $difficulty]);
    }

    private static function request(string $method, string $url, ?array $payload = null): array
    {
        $options = ['http' => [
            'method' => $method,
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        ]];
        if ($payload !== null) {
            $options['http']['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        $response = @file_get_contents($url, false, stream_context_create($options));
        if ($response === false) {
            return ['status'=>'error','message'=>'Java no responde en el puerto 8081. Ejecuta mvnw.cmd spring-boot:run.'];
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status'=>'error','message'=>'Java devolvió una respuesta inválida.'];
    }
}
