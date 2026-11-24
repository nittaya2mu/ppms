<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AcademicService;
use App\Services\EvaluationService;
use App\Services\Rules\RuleViolation;

/** Proposal Evaluation for committee members (Section 9). */
final class EvaluationController extends Controller
{
    public function edit(Request $r): Response
    {
        try {
            $ev = EvaluationService::ownEvaluation($this->id($r), $this->userId());
        } catch (RuleViolation) {
            throw \App\Core\HttpException::forbidden();   // another member's evaluation is never shown (9.4)
        }
        return $this->view('evaluations/form', [
            'ev' => $ev,
            'criteria' => AcademicService::rubric((int) $ev['offering_id'], true),
            'scores' => EvaluationService::scores((int) $ev['id']),
            'comments' => EvaluationService::comments((int) $ev['id']),
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
                array_map('intval', (array) ($all['rating'] ?? [])),
                isset($all['recommendation']) ? (string) $all['recommendation'] : null,
                array_map('strval', (array) ($all['comment'] ?? [])),
                array_map('strval', (array) ($all['critical'] ?? [])),
                $submit
            );
        }, $submit ? __('evaluations.submitted') : __('common.saved'));
    }
}
