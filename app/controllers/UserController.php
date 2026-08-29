<?php
/**
 * User administration and personal profile controller.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : User & Access Management
 */
class UserController extends Controller
{
    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    public function index(): void
    {
        try {
            $query = trim((string) ($_GET['q'] ?? ''));
            $role = trim((string) ($_GET['role'] ?? ''));
            $status = trim((string) ($_GET['status'] ?? ''));
            $this->view('user/index', [
                'title' => 'Users',
                'users' => $this->service->search($query, $role, $status),
                'roles' => User::roles(),
                'filters' => compact('query', 'role', 'status'),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function create(): void
    {
        try {
            UserPermissions::require('user.manage');
            $this->renderAdminForm('create', null, [], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function store(): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $user = $this->service->createByAdministrator($_POST);
            Flash::set('success', $user->getFullName() . ' was added.');
            $this->redirect('user');
        } catch (ValidationException $error) {
            http_response_code(422);
            $this->renderAdminForm('create', null, $error->getErrors(), $_POST);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function edit(int $id): void
    {
        try {
            $user = $this->service->find($id);
            if ($user === null) {
                $this->entityNotFound('User');
                return;
            }
            $this->renderAdminForm('edit', $user, [], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function update(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $user = $this->service->updateByAdministrator($id, $_POST);
            Flash::set('success', $user->getFullName() . ' was updated.');
            $this->redirect('user');
        } catch (ValidationException $error) {
            $user = User::find($id);
            if ($user === null) {
                $this->entityNotFound('User');
                return;
            }
            http_response_code(422);
            $this->renderAdminForm('edit', $user, $error->getErrors(), $_POST);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('User');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function profile(): void
    {
        try {
            $user = Auth::requireLogin();
            $this->view('user/profile', [
                'title' => 'My Profile',
                'user' => $user,
                'errors' => [],
                'values' => [],
                'permissions' => UserPermissions::for($user)->permissions(),
            ]);
        } catch (AuthenticationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function updateProfile(): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $user = $this->service->updateOwnProfile(Auth::requireLogin(), $_POST);
            Flash::set('success', 'Your profile was updated.');
            $this->redirect('user/profile');
        } catch (ValidationException $error) {
            $user = Auth::requireLogin();
            http_response_code(422);
            $this->view('user/profile', [
                'title' => 'My Profile',
                'user' => $user,
                'errors' => $error->getErrors(),
                'values' => $_POST,
                'permissions' => UserPermissions::for($user)->permissions(),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    private function renderAdminForm(string $mode, ?User $user, array $errors, array $submitted): void
    {
        $values = $submitted !== [] ? $submitted : [
            'full_name' => $user?->getFullName() ?? '',
            'email' => $user?->getEmail() ?? '',
            'phone_no' => $user?->getPhoneNo() ?? '',
            'role' => $user?->getRole() ?? User::ROLE_REPORTER,
            'account_status' => $user?->getAccountStatus() ?? 'Active',
        ];
        $this->view('user/form', [
            'title' => $mode === 'create' ? 'Add User' : 'Edit User',
            'mode' => $mode,
            'user' => $user,
            'values' => $values,
            'errors' => $errors,
            'roles' => User::roles(),
        ]);
    }
}
