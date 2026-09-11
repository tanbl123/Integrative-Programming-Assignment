<?php
/**
 * Shared REST request/response security.
 *
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 */
abstract class ApiController extends Controller
{
    private ?string $apiRequestId = null;

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
                $this->apiErrorResponse('Malformed JSON.', 400);
            }
            if (!$object instanceof stdClass) {
                $this->apiErrorResponse('Send a JSON object.', 400);
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
        $this->apiErrorResponse('Use application/json.', 415);
    }

    /**
     * Enforces the request-tracking fields in the modules' Interface Agreement.
     * A caller may send either requestID or timeStamp. Both may be supplied in
     * the query string, JSON/form body, or the matching X-Request headers.
     */
    protected function apiRequireTracking(?array $payload = null): void
    {
        $requestId = trim((string) (
            $_GET['requestID']
            ?? $_GET['requestId']
            ?? $payload['requestID']
            ?? $payload['requestId']
            ?? $_SERVER['HTTP_X_REQUEST_ID']
            ?? ''
        ));
        $timeStamp = trim((string) (
            $_GET['timeStamp']
            ?? $payload['timeStamp']
            ?? $_SERVER['HTTP_X_REQUEST_TIMESTAMP']
            ?? ''
        ));

        if ($requestId === '' && $timeStamp === '') {
            throw new ValidationException([
                'requestID' => 'Provide requestID or timeStamp so the service call can be traced.',
            ]);
        }
        if ($requestId !== '' && !preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $requestId)) {
            throw new ValidationException([
                'requestID' => 'Use 1 to 100 letters, numbers, dots, underscores, colons, or hyphens.',
            ]);
        }
        if ($timeStamp !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timeStamp);
            $valid = $parsed !== false && $parsed->format('Y-m-d H:i:s') === $timeStamp;
            if (!$valid) {
                throw new ValidationException([
                    'timeStamp' => 'Use the format YYYY-MM-DD HH:MM:SS.',
                ]);
            }
        }

        $this->apiRequestId = $requestId !== '' ? $requestId : $this->newApiRequestId();
    }

    protected function apiWriteGuard(?array $payload = null): void
    {
        Auth::requireLogin();
        $payload ??= $this->apiPayload();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['_token'] ?? null);
        Csrf::requireValid(is_string($token) ? $token : null);
    }

    protected function apiRespond(
        mixed $data,
        int $httpStatus = 200,
        ?string $message = null,
        array $extra = []
    ): void
    {
        header('Cache-Control: no-store');
        $this->json(array_merge($extra, $this->apiEnvelope('S', true, $data, $message)), $httpStatus);
    }

    protected function apiAllow(array $methods): void
    {
        if (!in_array($this->apiMethod(), $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            $this->apiErrorResponse('Method not allowed.', 405);
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
        $result = $this->apiEnvelope(
            $status === 500 ? 'E' : 'F',
            false,
            null,
            $status === 500 ? 'The service is temporarily unavailable.' : $error->getMessage()
        );
        if ($error instanceof ValidationException) { $result['errors'] = $error->getErrors(); }
        if ($status === 500) { error_log((string) $error); }
        $this->json($result, $status);
    }

    private function apiErrorResponse(string $message, int $httpStatus): void
    {
        $this->json($this->apiEnvelope($httpStatus >= 500 ? 'E' : 'F', false, null, $message), $httpStatus);
    }

    private function apiEnvelope(string $status, bool $success, mixed $data, ?string $message): array
    {
        if ($this->apiRequestId === null) {
            $transportId = trim((string) (
                $_GET['requestID']
                ?? $_GET['requestId']
                ?? $_SERVER['HTTP_X_REQUEST_ID']
                ?? ''
            ));
            $this->apiRequestId = preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $transportId)
                ? $transportId
                : $this->newApiRequestId();
        }
        return [
            'status' => $status,
            'success' => $success,
            'requestID' => $this->apiRequestId,
            'timeStamp' => ifaTimestamp(),
            'data' => $data,
            'message' => $message,
        ];
    }

    private function newApiRequestId(): string
    {
        return 'ECO-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }
}
