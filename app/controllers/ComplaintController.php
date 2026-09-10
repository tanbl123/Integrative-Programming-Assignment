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
                // Administrators also see reports collapsed by bin and issue,
                // so five people reporting one bin read as one problem.
                'duplicateGroups' => UserPermissions::can($user, 'complaint.manage')
                    ? Complaint::duplicateGroups()
                    : [],
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
            $this->view('complaint/show', $this->showData($complaint, $user));
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function updateStatus(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            // Reject array-valued fields before they reach input(), which would
            // otherwise cast them to the string "Array". ComplaintService's
            // validateComplaint() applies the same guard on the create/update
            // path, so both write paths behave identically.
            $this->requireScalar(['complaint_status', 'remarks']);
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
            $this->view('complaint/show', $this->showData(
                $complaint, Auth::requireLogin(), $error->getErrors()));
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

    /**
     * Closes a group of duplicate reports, keeping the one the administrator
     * chose. Each rejected complaint notifies its own reporter.
     */
    public function rejectDuplicates(): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->requireScalar(['bin_id', 'complaint_type', 'keep_id']);

            $binId  = filter_var($_POST['bin_id'] ?? null, FILTER_VALIDATE_INT);
            $keepId = filter_var($_POST['keep_id'] ?? null, FILTER_VALIDATE_INT);
            if ($binId === false || $keepId === false) {
                throw new ValidationException(['complaint' => 'Choose a valid group of reports.']);
            }

            $rejected = $this->service->rejectDuplicates(
                (int) $binId,
                $this->input('complaint_type'),
                (int) $keepId,
                Auth::requireLogin()
            );

            Flash::set('success', $rejected . ' duplicate report'
                . ($rejected === 1 ? '' : 's')
                . ' rejected. Complaint #' . (int) $keepId . ' remains open, and every '
                . 'reporter has been notified.');
            $this->redirect('complaint');
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('complaint');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
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

    /**
     * Everything the complaint detail view needs.
     *
     * Shared by show() and by the status-update error path, so that a failed
     * update re-renders exactly the same page. Previously the error path
     * omitted the web service fields and the view could not render.
     *
     * @param array<string,string> $errors
     * @return array<string,mixed>
     */
    private function showData(Complaint $complaint, User $user, array $errors = []): array
    {
        // WEB SERVICE CONSUMPTION
        // Authoritative bin details are requested from the Bin & Location
        // module's REST service rather than read from its tables. If that
        // service is unavailable the page still renders from local data.
        $binClient = new BinServiceClient();
        $binInfo = $complaint->getBin() === null
            ? null
            : $binClient->getBinInfo((int) $complaint->getBin()->getKey());

        return [
            'title'               => 'Complaint #' . $complaint->getKey(),
            'complaint'           => $complaint,
            'attachments'         => $complaint->getAttachments(),
            'history'             => $complaint->getHistory(),
            'statuses'            => Complaint::allowedNextStatuses($complaint->getStatus()),
            'errors'              => $errors,
            'user'                => $user,
            'canDelete'           => $this->service->canDelete($complaint, $user),
            'isAdmin'             => UserPermissions::can($user, 'complaint.manage'),
            'binInfo'             => $binInfo,
            'binServiceError'     => $binClient->getLastError(),
            'binServiceRequestId' => $binClient->getLastRequestId(),
        ];
    }

    /**
     * Rejects any of the named POST fields that arrived as an array.
     *
     * A crafted form can post complaint_status[]=New instead of
     * complaint_status=New. Casting that to a string yields "Array" and emits a
     * PHP warning, so it is refused here as a validation error instead.
     *
     * @param string[] $fields
     * @throws ValidationException
     */
    private function requireScalar(array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field]) && !is_scalar($_POST[$field])) {
                $errors[$field] = 'Enter a single value.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
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
            // Lets the form warn about issues already open for the chosen bin.
            'openByBin' => Complaint::openSummaryByBin(),
        ]);
    }
}
