<?php
/**
 * Reporter complaint workflow and administrator resolution controller.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 */
class ComplaintController extends Controller
{
    private ComplaintService $service;

    public function __construct()
    {
        $this->service = new ComplaintService();
    }

    public function index(): void
    {
        try {
            $user = Auth::requireLogin();
            $query = trim((string) ($_GET['q'] ?? ''));
            $status = trim((string) ($_GET['status'] ?? ''));
            $location = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
            $location = $location === false ? null : $location;
            $this->view('complaint/index', [
                'title' => 'Complaints',
                'complaints' => $this->service->searchVisible($user, $query, $status, $location),
                'statuses' => Complaint::statuses(),
                'locations' => Location::allAlphabetical(),
                'filters' => compact('query', 'status', 'location'),
                'user' => $user,
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function create(): void
    {
        try {
            UserPermissions::require('complaint.create');
            $this->renderForm([], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function store(): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $complaint = $this->service->create(
                Auth::requireLogin(),
                $_POST,
                $_FILES['attachment'] ?? null
            );
            Flash::set('success', 'Complaint #' . $complaint->getKey() . ' was submitted.');
            $this->redirect('complaint/show/' . $complaint->getKey());
        } catch (ValidationException $error) {
            http_response_code(422);
            $this->renderForm($error->getErrors(), $_POST);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function show(int $id): void
    {
        try {
            $user = Auth::requireLogin();
            $complaint = $this->service->findVisible($user, $id);
            if ($complaint === null) {
                $this->entityNotFound('Complaint');
                return;
            }
            // WEB SERVICE CONSUMPTION
            // Authoritative bin details are requested from the Bin & Location
            // module's REST service rather than read from its tables. If that
            // service is unavailable the page still renders from local data.
            $binClient = new BinServiceClient();
            $binInfo = $complaint->getBin() === null
                ? null
                : $binClient->getBinInfo((int) $complaint->getBin()->getKey());

            $this->view('complaint/show', [
                'title' => 'Complaint #' . $id,
                'complaint' => $complaint,
                'attachments' => $complaint->getAttachments(),
                'history' => $complaint->getHistory(),
                'statuses' => Complaint::allowedNextStatuses($complaint->getStatus()),
                'errors' => [],
                'user' => $user,
                'binInfo' => $binInfo,
                'binServiceError' => $binClient->getLastError(),
                'binServiceRequestId' => $binClient->getLastRequestId(),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function updateStatus(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $complaint = $this->service->updateStatus(
                $id,
                $this->input('complaint_status'),
                $this->input('remarks'),
                Auth::requireLogin()
            );
            Flash::set('success', 'Complaint status changed to ' . $complaint->getStatus() . '.');
            $this->redirect('complaint/show/' . $id);
        } catch (ValidationException $error) {
            $complaint = Complaint::find($id);
            if ($complaint === null) {
                $this->entityNotFound('Complaint');
                return;
            }
            http_response_code(422);
            $this->view('complaint/show', [
                'title' => 'Complaint #' . $id,
                'complaint' => $complaint,
                'attachments' => $complaint->getAttachments(),
                'history' => $complaint->getHistory(),
                'statuses' => Complaint::allowedNextStatuses($complaint->getStatus()),
                'errors' => $error->getErrors(),
                'user' => Auth::requireLogin(),
            ]);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Complaint');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function edit(int $id): void
    {
        try {
            $complaint = $this->service->findVisible(Auth::requireLogin(), $id);
            if ($complaint === null) { $this->entityNotFound('Complaint'); return; }
            $this->renderForm([], $complaint->toArray(), $id);
        } catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function update(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->update($id, $_POST, Auth::requireLogin());
            Flash::set('success', 'Complaint details updated.');
            $this->redirect('complaint/show/' . $id);
        } catch (ValidationException $error) {
            http_response_code(422);
            $this->renderForm($error->getErrors(), $_POST, $id);
        } catch (OutOfBoundsException $error) { $this->entityNotFound('Complaint'); }
        catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function delete(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->delete($id, Auth::requireLogin());
            Flash::set('success', 'Complaint deleted. Its history has been retained.');
            $this->redirect('complaint');
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('complaint/show/' . $id);
        } catch (OutOfBoundsException $error) { $this->entityNotFound('Complaint'); }
        catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function attachment(int $id): void
    {
        try {
            $user = Auth::requireLogin();
            $attachment = ComplaintAttachment::find($id);
            if ($attachment === null) {
                $this->entityNotFound('Attachment');
                return;
            }
            $complaint = $this->service->findVisible($user, $attachment->getComplaintId());
            if ($complaint === null) {
                $this->entityNotFound('Attachment');
                return;
            }
            $path = UPLOAD_PATH . DIRECTORY_SEPARATOR . basename($attachment->getStoredName());
            if (!is_file($path)) {
                $this->entityNotFound('Attachment');
                return;
            }
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $attachment->getOriginalName());
            header('Content-Type: ' . $attachment->getMimeType());
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: inline; filename="' . $safeName . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    private function renderForm(array $errors, array $values, ?int $editId = null): void
    {
        $this->view('complaint/form', [
            'title' => $editId === null ? 'Report Waste Issue' : 'Edit Complaint',
            'errors' => $errors,
            'values' => $values,
            'editId' => $editId,
            'bins' => Bin::findActive(),
            'types' => Complaint::types(),
        ]);
    }
}
