<?php
/**
 * App - the router / front controller dispatcher.
 *
 * Add safe REST routing
 * Module : Shared core - EcoCampus Waste Management System
 *
 * Turns a URL into a controller call:
 *
 *   /EcoCampus/complaint/view/7
 *        |         |     |
 *        |         |     +-- parameters passed to the method
 *        |         +-------- method  -> view()
 *        +------------------ controller -> ComplaintController
 *
 * A missing controller or method produces a 404 instead of a PHP error.
 */
class App
{
    private string $controller = 'HomeController';
    private string $method     = 'index';
    private array  $params     = [];

    public function run(): void
    {
        $segments = $this->parseUrl();

        // ---- Controller ----
        if (!empty($segments[0])) {
            $candidate = $this->studly(array_shift($segments)) . 'Controller';

            if (!$this->controllerExists($candidate)) {
                $this->notFound();
                return;
            }
            $this->controller = $candidate;
        }

        require_once APP_ROOT . '/app/controllers/' . $this->controller . '.php';
        $instance = new $this->controller();

        // REST collections and numeric item URLs share a verb-aware action.
        if ($instance instanceof ApiController && method_exists($instance, 'resource')
            && ($segments === [] || (count($segments) === 1 && ctype_digit($segments[0])))) {
            $instance->resource($segments === [] ? null : (int) $segments[0]);
            return;
        }

        // ---- Method ----
        if (!empty($segments[0])) {
            $candidate = lcfirst($this->studly(array_shift($segments)));

            if (!method_exists($instance, $candidate)
                || !(new ReflectionMethod($instance, $candidate))->isPublic()
                || str_starts_with($candidate, '__') || $candidate === 'resource') {
                $this->notFound();
                return;
            }
            $this->method = $candidate;
        }

        // ---- Parameters ----
        $this->params = array_values($segments);

        $action = new ReflectionMethod($instance, $this->method);
        if (count($this->params) < $action->getNumberOfRequiredParameters()
            || count($this->params) > $action->getNumberOfParameters()) {
            $this->notFound();
            return;
        }
        foreach ($action->getParameters() as $index => $parameter) {
            if (isset($this->params[$index]) && (string) $parameter->getType() === 'int'
                && !ctype_digit($this->params[$index])) {
                $this->notFound();
                return;
            }
        }

        call_user_func_array([$instance, $this->method], $this->params);
    }

    /** Splits the ?url= value into clean segments. */
    private function parseUrl(): array
    {
        $url = trim((string) ($_GET['url'] ?? ''), '/');

        if ($url === '') {
            return [];
        }

        // Strip anything that is not a safe URL character, so a crafted URL
        // can never reach the filesystem as ../ path traversal.
        $url = preg_replace('/[^A-Za-z0-9\/_-]/', '', $url);

        return explode('/', filter_var($url, FILTER_SANITIZE_URL));
    }

    /** 'complaint-report' -> 'ComplaintReport' */
    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private function controllerExists(string $name): bool
    {
        return file_exists(APP_ROOT . '/app/controllers/' . $name . '.php');
    }

    private function notFound(): void
    {
        http_response_code(404);
        require_once APP_ROOT . '/app/controllers/HomeController.php';
        (new HomeController())->notFound();
    }
}
