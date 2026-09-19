<?php
/**
 * Every endpoint replies through these two methods, so the front end can
 * always rely on the same envelope shape: { "data": ... } or
 * { "error": "message" }.
 */
class Response
{
    public static function json($data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok($data, int $status = 200): never
    {
        self::json(['data' => $data], $status);
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['error' => $message], $status);
    }
}
