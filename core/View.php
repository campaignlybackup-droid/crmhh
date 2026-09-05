<?php

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $__viewFile = __DIR__ . '/../views/' . $view . '.php';
    if (!file_exists($__viewFile)) {
        fatal_error('View not found.');
    }
    require $__viewFile;
}

function render_page(string $view, array $data = [], string $title = ''): void
{
    $pageTitle = $title;
    $currentUser = Auth::user();
    require __DIR__ . '/../views/layout/header.php';
    require __DIR__ . '/../views/layout/sidebar.php';
    echo '<main class="main-content">';
    render_flashes();
    render($view, $data);
    echo '</main>';
    require __DIR__ . '/../views/layout/footer.php';
}

function render_flashes(): void
{
    foreach (Flash::all() as $f) {
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
}
