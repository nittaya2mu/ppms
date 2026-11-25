<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\EvaluationService;
use App\Services\FileService;
use App\Services\Rules\RuleViolation;

/** Presentation evaluation (แบบฟอร์ม 2) for the advisor and committee members. */
final class EvaluationController extends Controller
{
    public function edit(Request $r): Response
    {
        try {
            $ev = EvaluationService::ownEvaluation($this->id($r), $this->userId());
        } catch (RuleViolation) {
            throw HttpException::forbidden();   // another evaluator's form is never shown
        }
        $version = Database::connection()->fetch('SELECT * FROM proposal_versions WHERE id = :v', ['v' => (int) $ev['proposal_version_id']]);
        $files = [];
        foreach ([1 => 'file_id', 2 => 'ch2_file_id', 3 => 'ch3_file_id'] as $ch => $col) {
            if (!empty($version[$col])) {
                $files[$ch] = FileService::find((int) $version[$col]);
            }
        }
        return $this->view('evaluations/form', [
            'ev' => $ev,
            'grades' => EvaluationService::grades($ev),
            'version' => $version,
            'files' => $files,
            'peer' => EvaluationService::peerVisible((int) $ev['assignment_id']) ? EvaluationService::summary((int) $ev['assignment_id']) : null,
        ]);
    }

    public function save(Request $r): Response
    {
        $id = $this->id($r);
        $submit = $r->input('do') === 'submit';
        $all = $r->all();
        return $this->attempt($r, "/evaluations/$id", function () use ($id, $all, $submit) {
            EvaluationService::save(
                $id, $this->userId(),
                array_map('strval', (array) ($all['grade'] ?? [])),
                array_map('strval', (array) ($all['check'] ?? [])),
                isset($all['result']) ? (string) $all['result'] : null,
                isset($all['suggestions']) ? (string) $all['suggestions'] : null,
                $submit
            );
        }, $submit ? __('evaluations.submitted') : __('common.saved'));
    }
}
