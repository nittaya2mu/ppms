<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccessPolicy;
use App\Services\AuditLogger;
use App\Services\FileService;

/** Controlled download endpoint with authorization checks (Section 15, NFR-SEC). */
final class FileController extends Controller
{
    public function download(Request $request): Response
    {
        $file = FileService::find($this->id($request)) ?? throw HttpException::notFound();
        if (!AccessPolicy::canDownload($file)) {
            throw HttpException::forbidden();
        }
        $path = FileService::path($file);
        if (!is_file($path)) {
            throw HttpException::notFound();
        }
        AuditLogger::log('file.download', 'document_file', (int) $file['id']);

        $name = $file['original_name'];
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $inline = $file['mime_type'] === 'application/pdf' && $request->query('dl') === null;
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type'           => $file['mime_type'],
            'Content-Length'         => (string) filesize($path),
            'Content-Disposition'    => ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }
}
