<?php

declare(strict_types=1);

use App\Controllers\DispatchController;

require dirname(__DIR__) . '/vendor/autoload.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/swagger/assets/')) {
    $assetDirectory = realpath(__DIR__ . '/swagger/assets');
    $assetPath = realpath(__DIR__ . $path);

    if (
        $assetDirectory !== false
        && $assetPath !== false
        && str_starts_with($assetPath, $assetDirectory . DIRECTORY_SEPARATOR)
        && is_file($assetPath)
    ) {
        return false;
    }
}

match ([$method, $path]) {
    ['POST', '/api/v1/dispatch'] => (new DispatchController())->handle(),
    ['GET', '/api/v1/docs'] => (static function (): never {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/swagger/index.html');
        exit;
    })(),
    ['GET', '/swagger/openapi.yaml'] => (static function (): never {
        header('Content-Type: application/yaml; charset=utf-8');
        readfile(__DIR__ . '/swagger/openapi.yaml');
        exit;
    })(),
    default => (static function (): never {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(
            ['error' => 'Not found.'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit;
    })(),
};
