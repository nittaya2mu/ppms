<?php

declare(strict_types=1);

namespace App\Services\Workflow;

/**
 * Declarative state machine (DD-10). A definition maps
 *   action => ['from' => [states...], 'to' => state, 'roles' => [role codes], 'reason' => bool]
 * 'roles' lists who may trigger it ('system' = performed by the application itself).
 * 'reason' = true means a comment/reason is mandatory.
 */
final class StateMachine
{
    /** @param array<string,array{from:string[],to:string,roles:string[],reason?:bool}> $transitions */
    public function __construct(
        public readonly string $entity,
        public readonly array $states,
        private readonly array $transitions,
    ) {
        foreach ($transitions as $action => $t) {
            foreach (array_merge($t['from'], [$t['to']]) as $state) {
                if (!in_array($state, $states, true)) {
                    throw new \LogicException("$entity.$action references unknown state $state");
                }
            }
        }
    }

    public function has(string $action): bool
    {
        return isset($this->transitions[$action]);
    }

    public function transition(string $action): array
    {
        return $this->transitions[$action] ?? throw new \InvalidArgumentException("Unknown action {$this->entity}.$action");
    }

    /** Throws TransitionDenied when the action is not allowed. Returns the target state. */
    public function check(string $current, string $action, string $role, ?string $reason = null): string
    {
        $t = $this->transition($action);
        if (!in_array($current, $t['from'], true)) {
            throw new TransitionDenied("{$this->entity}: cannot '$action' from '$current'");
        }
        if (!in_array($role, $t['roles'], true)) {
            throw new TransitionDenied("{$this->entity}: role '$role' may not '$action'");
        }
        if (($t['reason'] ?? false) && trim((string) $reason) === '') {
            throw new TransitionDenied("{$this->entity}: '$action' requires a reason", 'reason_required');
        }
        return $t['to'];
    }

    /** @return string[] actions available to $role from $current */
    public function available(string $current, string $role): array
    {
        $out = [];
        foreach ($this->transitions as $action => $t) {
            if (in_array($current, $t['from'], true) && in_array($role, $t['roles'], true)) {
                $out[] = $action;
            }
        }
        return $out;
    }

    public function all(): array
    {
        return $this->transitions;
    }
}
