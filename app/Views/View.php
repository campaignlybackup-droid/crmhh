<?php

namespace App\Views;

class View {
    /**
     * Render a view component and return its HTML as a string.
     * Useful for reusable components.
     *
     * @param string $componentPath e.g. 'Components/Button'
     * @param array $data
     * @return string
     */
    public static function renderComponent(string $componentPath, array $data = []): string {
        extract($data);
        $file = __DIR__ . '/' . $componentPath . '.php';
        if (file_exists($file)) {
            ob_start();
            require $file;
            return ob_get_clean();
        }
        return "<!-- Component {$componentPath} not found -->";
    }
}
