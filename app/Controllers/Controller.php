<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\OfferingService;
use App\Services\Rules\RuleViolation;
use App\Services\Workflow\TransitionDenied;

/** Shared helpers for controllers. */
abstract class Controller
{
    protected function view(string $view, array $data = []): Response
    {
        return Response::view($view, $data);
    }

    protected function redirect(string $path, ?string $success = null): Response
    {
        if ($success !== null) {
            Session::flash('success', $success);
        }
        return Response::redirect($path);
    }

    protected function userId(): int
    {
        return (int) Auth::id();
    }

    protected function role(): string
    {
        return (string) Auth::activeRole();
    }

    protected function offering(): array
    {
        return OfferingService::current() ?? throw new HttpException(409, 'No open course offering');
    }

    protected function id(Request $request, string $key = 'id'): int
    {
        return (int) $request->param($key);
    }

    /**
     * Runs a state-changing action and turns business-rule failures into a flash message
     * and a redirect back, instead of an error page.
     */
    protected function attempt(Request $request, string $back, callable $action, ?string $success = null): Response
    {
        try {
            $result = $action();
            if ($result instanceof Response) {
                return $result;
            }
            return $this->redirect($back, $success);
        } catch (RuleViolation $e) {
            Session::flash('danger', self::ruleMessage($e->rule, $e->getMessage()));
        } catch (TransitionDenied $e) {
            Session::flash('danger', $e->reasonCode === 'reason_required' ? __('rule.reason_required') : __('rule.transition_denied'));
        }
        return Response::back($request, $back);
    }

    public static function ruleMessage(string $rule, string $fallback): string
    {
        $text = __('rule.' . $rule);
        return $text === 'rule.' . $rule ? $fallback : $text;
    }
}
