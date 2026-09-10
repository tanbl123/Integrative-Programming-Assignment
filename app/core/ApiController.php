<?php
/** Shared REST request/response security.  */
abstract class ApiController extends Controller
{
    protected function apiMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    protected function apiPayload(): array
    {
        $type = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);
        if ($type === 'application/json') {
            try {
                $object = json_decode(file_get_contents('php://input'), false, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                $this->json(['success' => false, 'data' => null, 'message' => 'Malformed JSON.'], 400);
            }
            if (!$object instanceof stdClass) {
                $this->json(['success' => false, 'data' => null, 'message' => 'Send a JSON object.'], 400);
            }
            $payload = (array) $object;
            foreach ($payload as $field => $value) {
                if (!is_scalar($value) && $value !== null) {
                    throw new ValidationException([$field => 'Use a single text, number, boolean, or null value.']);
                }
            }
            return $payload;
        }
        if ($this->apiMethod() === 'POST') { return $_POST; }
        if ($type === 'application/x-www-form-urlencoded') {
            parse_str(file_get_contents('php://input'), $payload);
            return $payload;
        }
        if ($this->apiMethod() === 'DELETE') { return []; }
        $this->json(['success' => false, 'data' => null, 'message' => 'Use application/json.'], 415);
    }

    protected function apiWriteGuard(?array $payload = null): void
    {
        Auth::requireLogin();
        $payload ??= $this->apiPayload();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['_token'] ?? null);
        Csrf::requireValid(is_string($token) ? $token : null);
    }

    protected function apiRespond(mixed $data, int $status = 200, ?string $message = null): void
    {
        header('Cache-Control: no-store');
        $this->json(['success' => true, 'data' => $data, 'message' => $message], $status);
    }

    protected function apiAllow(array $methods): void
    {
        if (!in_array($this->apiMethod(), $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            $this->json(['success' => false, 'data' => null, 'message' => 'Method not allowed.'], 405);
        }
    }

    protected function apiFailure(Throwable $error): void
    {
        $status = match (true) {
            $error instanceof AuthenticationException => 401,
            $error instanceof AuthorizationException => 403,
            $error instanceof ValidationException => 422,
            $error instanceof OutOfBoundsException => 404,
            default => 500,
        };
        $result = ['success' => false, 'data' => null,
            'message' => $status === 500 ? 'The service is temporarily unavailable.' : $error->getMessage()];
        if ($error instanceof ValidationException) { $result['errors'] = $error->getErrors(); }
        if ($status === 500) { error_log((string) $error); }
        $this->json($result, $status);
    }
}
