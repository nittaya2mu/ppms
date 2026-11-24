<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP templates in resources/views. Output escaping is done with e() (NFR-SEC-04).
 * A view may call $this->layout('layouts/app', [...]) to be wrapped in a layout.
 */
final class View
{
    private ?string $layout = null;
    private array $layoutData = [];
    private array $sections = [];

    public static function render(string $view, array $data = []): string
    {
        return (new self())->renderFile($view, $data);
    }

    private function renderFile(string $view, array $data): string
    {
        $file = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view");
        }
        $content = (function () use ($file, $data) {
            extract($data, EXTR_SKIP);
            ob_start();
            try {
                include $file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })->call($this);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            return $this->renderFile($layout, array_merge($data, $this->layoutData, ['content' => $content]));
        }
        return $content;
    }

    public function layout(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function partial(string $view, array $data = []): string
    {
        return self::render($view, $data);
    }
}
