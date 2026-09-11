<?php
/**
 * Web service EXPOSURE for the Complaint / Report Management module.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management - Web Service Technologies
 *
 * Endpoints
 *   GET    /complaint-api                 list complaints visible to the caller
 *   GET    /complaint-api/{id}            one complaint
 *   POST   /complaint-api                 submit a complaint
 *   PUT    /complaint-api/{id}            update details, or move the lifecycle
 *   DELETE /complaint-api/{id}            soft-delete a complaint
 *   GET    /complaint-api/unresolved      open complaints, consumed by Scheduling
 *
 * INTERFACE AGREEMENT (IFA)
 * Every response carries:
 *   status     S = success, F = fail (caller error), E = error (server fault)
 *   timeStamp  YYYY-MM-DD HH:MM:SS, when the response was generated
 *   requestId  echoed from the request, or generated when the caller omits it
 * The legacy success/data/message keys are kept alongside so that existing
 * consumers written against the earlier draft continue to work unchanged.
 */
class ComplaintApiController extends ApiController
{
    /** REST CRUD on one URL, routed by HTTP method. */
    public function resource(?int $id = null): void
    {
        try {
            $user = Auth::requireLogin();
            $service = new ComplaintService();
            $method = $this->apiMethod();
            $this->apiAllow($id === null ? ['GET', 'POST'] : ['GET', 'PUT', 'PATCH', 'DELETE']);

            if ($method === 'GET') {
                if ($id === null) {
                    $location = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
                    $rows = $service->searchVisible(
                        $user,
                        trim((string) ($_GET['q'] ?? '')),
                        (string) ($_GET['status'] ?? ''),
                        $location === false ? null : $location
                    );
                    $this->ifaSuccess(
                        array_map(static fn(Complaint $row): array => $row->toArray(), $rows),
                        200,
                        null,
                        ['count' => count($rows)]
                    );
                } else {
                    $row = $service->findVisible($user, $id);
                    if ($row === null) {
                        throw new OutOfBoundsException('Complaint not found.');
                    }
                    $this->ifaSuccess($row->toArray());
                }
                return;
            }

            $data = $this->apiPayload();
            $this->apiWriteGuard($data);
            unset($data['_token']);

            if ($method === 'POST' && $id === null) {
                $this->ifaSuccess($service->create($user, $data, null)->toArray(), 201, 'Complaint submitted.');
            } elseif (in_array($method, ['PUT', 'PATCH'], true) && $id !== null) {
                if (isset($data['complaint_status'])
                    && array_intersect(['bin_id', 'complaint_type', 'description'], array_keys($data)) !== []) {
                    throw new ValidationException([
                        'complaint' => 'Update complaint details and lifecycle status in separate requests.',
                    ]);
                }
                $row = isset($data['complaint_status'])
                    ? $service->updateStatus($id, (string) $data['complaint_status'], (string) ($data['remarks'] ?? ''), $user)
                    : $service->update($id, $data, $user);
                $this->ifaSuccess($row->toArray(), 200, 'Complaint updated.');
            } elseif ($method === 'DELETE' && $id !== null) {
                $service->delete($id, $user);
                $this->ifaSuccess(null, 200, 'Complaint deleted; history retained.');
            } else {
                $this->ifaFailure(new BadFunctionCallException('Method not allowed.'), 405);
            }
        } catch (Throwable $error) {
            $this->ifaFailure($error);
        }
    }

    /**
     * Open complaints, for the Collection Scheduling & Assignment module.
     * This is the endpoint the Scheduling module consumes when deciding
     * which bins genuinely need attention.
     */
    public function unresolved(): void
    {
        $this->apiAllow(['GET']);
        try {
            UserPermissions::require('schedule.manage');
            $complaints = Complaint::unresolved();

            $this->ifaSuccess(
                array_map(static fn(Complaint $complaint): array => [
                    'id'          => $complaint->getKey(),
                    'type'        => $complaint->getType(),
                    'status'      => $complaint->getStatus(),
                    'description' => $complaint->getDescription(),
                    'created_at'  => $complaint->getCreatedAt(),
                    'bin'         => [
                        'id'       => $complaint->getBin()?->getKey(),
                        'code'     => $complaint->getBin()?->getBinCode(),
                        'location' => $complaint->getBin()?->getLocation()?->getFullLabel(),
                    ],
                ], $complaints),
                200,
                null,
                ['count' => count($complaints)]
            );
        } catch (Throwable $error) {
            $this->ifaFailure($error);
        }
    }

    // ------------------------------------------------------------------
    // IFA envelope
    // ------------------------------------------------------------------

    /** Sends an IFA-compliant success response and stops. */
    private function ifaSuccess(mixed $data, int $status = 200, ?string $message = null, array $meta = []): void
    {
        header('Cache-Control: no-store');
        $this->json($this->envelope('S', $data, $message, $meta), $status);
    }

    /** Maps an exception to the correct HTTP code and IFA status, then stops. */
    private function ifaFailure(Throwable $error, ?int $forceStatus = null): void
    {
        $status = $forceStatus ?? match (true) {
            $error instanceof AuthenticationException => 401,
            $error instanceof AuthorizationException  => 403,
            $error instanceof ValidationException     => 422,
            $error instanceof OutOfBoundsException    => 404,
            default                                   => 500,
        };

        // F = the caller can fix it. E = the server faulted.
        $ifaStatus = $status >= 500 ? 'E' : 'F';
        $message   = $status >= 500 ? 'The service is temporarily unavailable.' : $error->getMessage();

        $payload = $this->envelope($ifaStatus, null, $message);
        if ($error instanceof ValidationException) {
            $payload['errors'] = $error->getErrors();
        }
        if ($status >= 500) {
            error_log((string) $error);
        }
        if ($status === 405) {
            header('Allow: GET, POST, PUT, PATCH, DELETE');
        }

        $this->json($payload, $status);
    }

    /** Builds the shared response body. */
    private function envelope(string $ifaStatus, mixed $data, ?string $message, array $meta = []): array
    {
        return [
            // IFA mandatory fields.
            'status'    => $ifaStatus,
            'timeStamp' => ifaTimestamp(),
            'requestId' => $this->requestId(),
            // Retained for consumers written against the earlier draft.
            'success'   => $ifaStatus === 'S',
            'data'      => $data,
            'message'   => $message,
            'meta'      => $meta,
        ];
    }

    /**
     * The caller's requestId, echoed back so they can correlate the reply.
     * When a caller omits it, one is generated so the field is never absent.
     */
    private function requestId(): string
    {
        $supplied = $_GET['requestId']
            ?? $_POST['requestId']
            ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? null);

        if (is_string($supplied) && $supplied !== '' && mb_strlen($supplied) <= 64) {
            return preg_replace('/[^A-Za-z0-9._-]/', '', $supplied) ?: $this->newRequestId();
        }
        return $this->newRequestId();
    }

    /** Generates an IFA request id of the form CMP-YYYYMMDDHHMMSS-xxxxxxxx. */
    private function newRequestId(): string
    {
        return 'CMP-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }
}
