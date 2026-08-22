<?php
/**
 * Controller - base class for every controller in the system.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Shared core - EcoCampus Waste Management System
 *
 * In MVC the Controller is the middle layer: it receives the request,
 * asks Models for data, then hands that data to a View to be displayed.
 * A controller should contain NO SQL and NO HTML.
 */
abstract class Controller
{
    /**
     * Renders a view file and sends it to the browser.
     *
     * @param string $view path under app/views without .php, e.g. 'complaint/list'
     * @param array  $data variables made available inside the view
     */
    protected function view(string $view, array $data = []): void
    {
        $file = APP_ROOT . '/app/views/' . $view . '.php';

        if (!file_exists($file)) {
            http_response_code(500);
            die(DEBUG ? 'View not found: ' . $view : 'Page unavailable.');
        }

        // Turns ['title' => 'Bins'] into a $title variable inside the view.
        extract($data, EXTR_SKIP);

        require APP_ROOT . '/app/views/layout/header.php';
        require $file;
        require APP_ROOT . '/app/views/layout/footer.php';
    }

    /**
     * Sends a JSON response and stops. Used by the web-service endpoints.
     *
     * @param array $payload data to encode
     * @param int   $status  HTTP status code
     */
    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Redirects to a path inside the application, then stops. */
    protected function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
        exit;
    }

    /** True when the current request was submitted as a form POST. */
    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Reads a POST field as a trimmed string.
     *
     * This is convenience only - it is NOT validation. Every module must
     * still validate what it receives before storing or displaying it.
     */
    protected function input(string $field, string $default = ''): string
    {
        return trim((string) ($_POST[$field] ?? $default));
    }
}
