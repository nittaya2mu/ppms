<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\XlsxWriter;
use App\Services\AuditLogger;
use App\Services\ReportService;

/** Reports and exports (Section 18). */
final class ReportController extends Controller
{
    public function index(Request $r): Response
    {
        $keys = array_keys(ReportService::definitions());
        $keys = array_values(array_filter($keys, fn ($k) => ReportService::allowed($k, $this->role())));
        if ($keys === []) {
            throw HttpException::forbidden();
        }
        return $this->view('reports/index', ['keys' => $keys, 'offering' => $this->offering()]);
    }

    public function show(Request $r): Response
    {
        $key = (string) $r->param('key');
        $data = $this->data($key);
        $format = (string) $r->query('format', 'html');
        $o = $this->offering();
        $title = __('report.' . $key);
        $filename = 'PPMS_' . $key . '_' . $o['term_no'] . '-' . $o['year_be'] . '_' . date('Ymd');

        if ($format === 'xlsx' || $format === 'csv') {
            AuditLogger::log('report.export', 'report', null, null, ['report' => $key, 'format' => $format]);
        }
        if ($format === 'xlsx') {
            return new Response(XlsxWriter::build(mb_substr($title, 0, 31), $data['columns'], $data['rows']), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.xlsx"',
                'Cache-Control' => 'private, no-store',
            ]);
        }
        if ($format === 'csv') {
            $fh = fopen('php://temp', 'r+');
            fwrite($fh, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows Thai correctly
            fputcsv($fh, $data['columns'], ',', '"', '\\');
            foreach ($data['rows'] as $row) {
                fputcsv($fh, $row, ',', '"', '\\');
            }
            rewind($fh);
            return new Response((string) stream_get_contents($fh), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            ]);
        }
        $view = $format === 'print' ? 'reports/print' : 'reports/show';
        return $this->view($view, $data + ['key' => $key, 'title' => $title, 'offering' => $o]);
    }

    private function data(string $key): array
    {
        if (!ReportService::allowed($key, $this->role())) {
            throw HttpException::forbidden();
        }
        return ReportService::run($key, (int) $this->offering()['id'], $this->role(), $this->userId());
    }
}
