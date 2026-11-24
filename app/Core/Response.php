<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function view(string $view, array $data = [], int $status = 200): self
    {
        return new self(View::render($view, $data), $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $path, int $status = 302): self
    {
        $target = preg_match('#^https?://#', $path) ? $path : url($path);
        return new self('', $status, ['Location' => $target]);
    }

    /** Redirect back with old input and validation errors. */
    public static function back(Request $request, string $fallback, array $errors = []): self
    {
        Session::setOld($request->all());
        if ($errors) {
            Session::setErrors($errors);
        }
        return self::redirect($fallback);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
