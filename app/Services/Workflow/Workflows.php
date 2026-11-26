<?php

declare(strict_types=1);

namespace App\Services\Workflow;

/**
 * The three controlled state machines of Section 12, matching the transition tables
 * in Phase 1 doc sections 4.1 (T1-T10) and 5.1 (P1-P16).
 */
final class Workflows
{
    public static function group(): StateMachine
    {
        return new StateMachine('group', ['draft', 'submitted', 'verified', 'active', 'inactive'], [
            'submit'     => ['from' => ['draft'],                         'to' => 'submitted', 'roles' => ['leader']],
            'reject'     => ['from' => ['submitted'],                     'to' => 'draft',     'roles' => ['admin'], 'reason' => true],
            'verify'     => ['from' => ['submitted'],                     'to' => 'verified',  'roles' => ['admin']],
            'activate'   => ['from' => ['verified'],                      'to' => 'active',    'roles' => ['admin', 'system']],
            'revoke_advisor' => ['from' => ['active'],                    'to' => 'verified',  'roles' => ['admin'], 'reason' => true],
            'deactivate' => ['from' => ['draft', 'submitted', 'verified', 'active'], 'to' => 'inactive', 'roles' => ['admin'], 'reason' => true],
            'reactivate' => ['from' => ['inactive'],                      'to' => 'active',    'roles' => ['admin'], 'reason' => true],
        ]);
    }

    public static function topic(): StateMachine
    {
        return new StateMachine('topic', [
            'draft', 'submitted', 'advisor_review', 'advisor_approved', 'revision_required',
            'admin_review', 'topic_approved', 'topic_rejected',
        ], [
            'submit'          => ['from' => ['draft'],             'to' => 'submitted',         'roles' => ['leader']],                 // T1
            'start_review'    => ['from' => ['submitted'],         'to' => 'advisor_review',    'roles' => ['advisor']],                // T2
            'advisor_approve' => ['from' => ['advisor_review'],    'to' => 'advisor_approved',  'roles' => ['advisor']],                // T3
            'forward_admin'   => ['from' => ['advisor_approved'],  'to' => 'admin_review',      'roles' => ['system']],                 // T4
            'advisor_revise'  => ['from' => ['advisor_review'],    'to' => 'revision_required', 'roles' => ['advisor'], 'reason' => true], // T5
            'advisor_reject'  => ['from' => ['advisor_review'],    'to' => 'topic_rejected',    'roles' => ['advisor'], 'reason' => true], // T6
            'admin_approve'   => ['from' => ['admin_review'],      'to' => 'topic_approved',    'roles' => ['admin']],                  // T7
            'admin_revise'    => ['from' => ['admin_review'],      'to' => 'revision_required', 'roles' => ['admin'], 'reason' => true],   // T8
            'admin_reject'    => ['from' => ['admin_review'],      'to' => 'topic_rejected',    'roles' => ['admin'], 'reason' => true],   // T9
            'resubmit'        => ['from' => ['revision_required'], 'to' => 'submitted',         'roles' => ['leader']],                 // T10
            'resubmit_admin'  => ['from' => ['revision_required'], 'to' => 'admin_review',      'roles' => ['leader']],                 // T10 route (b), OQ-10
        ]);
    }

    public static function proposal(): StateMachine
    {
        return new StateMachine('proposal', [
            'draft', 'submitted', 'advisor_review', 'advisor_approved', 'committee_assignment',
            'committee_evaluation', 'evaluation_completed', 'revision_required', 're_evaluation',
            'final_review', 'approved', 'approved_minor', 'rejected',
        ], [
            'submit'             => ['from' => ['draft'],                                  'to' => 'submitted',            'roles' => ['leader']],                     // P1
            'start_review'       => ['from' => ['submitted'],                              'to' => 'advisor_review',       'roles' => ['advisor']],                    // P2
            'advisor_ready'      => ['from' => ['advisor_review'],                         'to' => 'advisor_approved',     'roles' => ['advisor']],                    // P3
            'advisor_revise'     => ['from' => ['advisor_review'],                         'to' => 'revision_required',    'roles' => ['advisor'], 'reason' => true],  // P4
            'advisor_not_approve'=> ['from' => ['advisor_review'],                         'to' => 'final_review',         'roles' => ['advisor'], 'reason' => true],  // P5 (OQ-17)
            'await_committee'    => ['from' => ['advisor_approved'],                       'to' => 'committee_assignment', 'roles' => ['system']],                     // P6
            'assign_committee'   => ['from' => ['committee_assignment'],                   'to' => 'committee_evaluation', 'roles' => ['admin']],                      // P7
            'complete_evaluation'=> ['from' => ['committee_evaluation'],                   'to' => 'evaluation_completed', 'roles' => ['system']],                     // P8
            'open_final_review'  => ['from' => ['evaluation_completed'],                   'to' => 'final_review',         'roles' => ['admin']],                      // P9
            'final_approve'      => ['from' => ['final_review'],                           'to' => 'approved',             'roles' => ['admin']],                      // P10
            'final_minor'        => ['from' => ['final_review'],                           'to' => 'approved_minor',       'roles' => ['admin']],                      // P11
            'final_major'        => ['from' => ['final_review'],                           'to' => 'revision_required',    'roles' => ['admin']],                      // P12
            'final_reject'       => ['from' => ['final_review'],                           'to' => 'rejected',             'roles' => ['admin']],                      // P13
            'resubmit'           => ['from' => ['revision_required', 'approved_minor'],    'to' => 'submitted',            'roles' => ['leader']],                     // P14
            'mode_advisor_only'  => ['from' => ['advisor_approved'],                       'to' => 'final_review',         'roles' => ['admin']],                      // P15
            'mode_re_evaluation' => ['from' => ['advisor_approved'],                       'to' => 're_evaluation',        'roles' => ['admin']],                      // P16
            'start_re_evaluation'=> ['from' => ['re_evaluation'],                          'to' => 'committee_evaluation', 'roles' => ['admin']],                      // P16
        ]);
    }
}
