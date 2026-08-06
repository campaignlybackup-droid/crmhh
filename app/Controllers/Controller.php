<?php

namespace App\Controllers;

abstract class Controller {
    /**
     * Render a view file.
     * 
     * @param string $viewPath e.g. 'projects/index'
     * @param array $data Data to pass to the view
     */
    protected function render(string $viewPath, array $data = []) {
        extract($data);
        $file = __DIR__ . '/../Views/' . $viewPath . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            die("View not found: {$viewPath}");
        }
    }

    /**
     * Redirect to a specific URL.
     *
     * @param string $url
     */
    protected function redirect(string $url) {
        header("Location: " . $url);
        exit;
    }
}
