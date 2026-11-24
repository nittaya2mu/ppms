<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuditLogger;
use Throwable;

/** Bootstraps a web request: config, session, auth, routing, error pages. */
final class App
{
    public static function run(): void
    {
        date_default_timezone_set((string) config('timezone', 'Asia/Bangkok'));
        mb_internal_encoding('UTF-8');
        self::ensureStorage();
        self::securityHeaders();

        $debug = (bool) config('debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        try {
            Session::start(config('session'));
            Session::ageFlashData();
            $request = Request::capture((string) config('base_path', ''));
            AuditLogger::setRequestContext($request->ip(), $request->userAgent());
            Auth::load();

            $router = new Router();
            require dirname(__DIR__, 2) . '/routes/web.php';
            $response = $router->dispatch($request);
            if (!$response instanceof Response) {
                $response = new Response((string) $response);
            }
        } catch (HttpException $e) {
            $response = self::errorResponse($e->status);
        } catch (Throwable $e) {
            self::logError($e);
            $response = $debug
                ? new Response('<pre>' . e((string) $e) . '</pre>', 500)
                : self::errorResponse(500);
        }
        $response->send();
    }

    private static function errorResponse(int $status): Response
    {
        $view = in_array($status, [403, 404, 405, 409, 419], true) ? (string) $status : '500';
        try {
            return Response::view('errors/error', ['status' => $status, 'key' => $view], $status);
        } catch (Throwable) {
            return new Response("Error $status", $status);
        }
    }

    private static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        // All front-end assets are self-hosted in public/assets/vendor (works on intranets, no CDN dependency).
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'");
    }

    /**
     * Recreates storage folders (and the deny-all .htaccess) when an upload tool skipped
     * them; folders that only contain dot-files are often dropped on upload.
     */
    private static function ensureStorage(): void
    {
        $base = dirname(__DIR__, 2) . '/storage';
        foreach (['', '/uploads', '/logs', '/cache'] as $sub) {
            if (!is_dir($base . $sub)) {
                @mkdir($base . $sub, 0775, true);
            }
        }
        if (is_dir($base) && !is_file($base . '/.htaccess')) {
            @file_put_contents($base . '/.htaccess', "Require all denied\n");
        }
    }

    public static function logError(Throwable $e): void
    {
        $dir = (string) config('storage.logs');
        if (is_dir($dir) && is_writable($dir)) {
            error_log(sprintf("[%s] %s\n", date('c'), (string) $e), 3, $dir . '/app-' . date('Y-m-d') . '.log');
        }
    }
}
