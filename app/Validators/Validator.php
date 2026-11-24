<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Database;

/**
 * Small rule-based validator. Messages are Thai via __().
 *
 * Rules: required, email, min:n, max:n, in:a,b,c, integer, between:a,b, confirmed,
 *        unique:table,column[,ignoreId], exists:table,column, regex:/pattern/
 */
final class Validator
{
    /** @var array<string,string[]> */
    private array $errors = [];

    public function __construct(private readonly array $data, private readonly array $rules)
    {
    }

    public static function make(array $data, array $rules): self
    {
        $v = new self($data, $rules);
        $v->run();
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $isEmpty = $value === null || (is_string($value) && trim($value) === '') || $value === [];

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name !== 'required' && $isEmpty) {
                    continue;
                }
                $ok = match ($name) {
                    'required'  => !$isEmpty,
                    'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'min'       => mb_strlen((string) $value) >= (int) $arg,
                    'max'       => mb_strlen((string) $value) <= (int) $arg,
                    'in'        => in_array((string) $value, explode(',', (string) $arg), true),
                    'integer'   => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'between'   => $this->between($value, (string) $arg),
                    'confirmed' => $value === ($this->data[$field . '_confirmation'] ?? null),
                    'unique'    => $this->unique($value, (string) $arg),
                    'exists'    => !$this->unique($value, (string) $arg),
                    'regex'     => preg_match((string) $arg, (string) $value) === 1,
                    default     => throw new \LogicException("Unknown rule $name"),
                };
                if (!$ok) {
                    $this->errors[$field][] = __('validation.' . $name, ['field' => __('field.' . $field), 'arg' => (string) $arg]);
                    if ($name === 'required') {
                        break;
                    }
                }
            }
        }
    }

    private function between(mixed $value, string $arg): bool
    {
        [$min, $max] = array_map('floatval', explode(',', $arg));
        return is_numeric($value) && (float) $value >= $min && (float) $value <= $max;
    }

    private function unique(mixed $value, string $arg): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $arg), 3, null);
        foreach ([$table, $column] as $identifier) {
            if (!preg_match('/^[a-z_]+$/', (string) $identifier)) {
                throw new \LogicException('Invalid identifier in unique/exists rule');
            }
        }
        $sql = "SELECT COUNT(*) FROM $table WHERE $column = :v";
        $params = ['v' => $value];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = (int) $ignoreId;
        }
        return (int) Database::connection()->value($sql, $params) === 0;
    }
}
