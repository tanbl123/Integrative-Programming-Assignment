<?php
/**
 * Administrator maintenance of the issue types a reporter may choose.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * The six types used to be fixed in Complaint::types() and again in an ENUM,
 * so adding a seventh meant a code change and a schema change. They now live
 * in complaint_types and this controller is how they are maintained.
 */
class ComplaintTypeController extends Controller
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
            UserPermissions::require('complaint.manage');

            // ?edit=N loads that type into the form above the table, so one
            // page both lists and edits rather than needing a second screen.
            $edit = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
            $type = $edit === false || $edit === null ? null : ComplaintType::find((int) $edit);

            $this->render($user, [], $type === null ? [] : [
                'type_name' => $type->getName(),
            ], $type?->getKey());
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function store(): void
    {
        $this->save(null);
    }

    public function update(int $id): void
    {
        $this->save($id);
    }

    public function toggle(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $type = $this->service->setTypeActive(
                $id,
                ($_POST['is_active'] ?? '0') === '1',
                Auth::requireLogin()
            );
            Flash::set('success', '"' . $type->getName() . '" is now '
                . ($type->isActive() ? 'available to reporters.' : 'withdrawn from use.'));
            $this->redirect('complaint-type');
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Issue type');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function delete(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->deleteType($id, Auth::requireLogin());
            Flash::set('success', 'Issue type deleted.');
            $this->redirect('complaint-type');
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('complaint-type');
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Issue type');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    private function save(?int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $type = $this->service->saveType($id, $_POST, Auth::requireLogin());
            Flash::set('success', 'Issue type "' . $type->getName() . '" saved.');
            $this->redirect('complaint-type');
        } catch (ValidationException $error) {
            http_response_code(422);
            $this->render(Auth::requireLogin(), $error->getErrors(), $_POST, $id);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Issue type');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed>  $values
     */
    private function render(User $user, array $errors = [], array $values = [], ?int $editId = null): void
    {
        $this->view('complaint/types', [
            'title'  => 'Issue types',
            'types'  => ComplaintType::allOrdered(),
            'errors' => $errors,
            'values' => $values,
            'editId' => $editId,
            'user'   => $user,
        ]);
    }
}
