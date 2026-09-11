<?php

/**
 * Secure login/logout required by module role enforcement.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
class AuthController extends Controller {

    public function index(): void {
        if (Auth::check()) {
            $this->redirect('');
        }

        $this->view('auth/login', [
            'title' => 'Sign In',
            'errors' => [],
            'values' => [
                'email' => '',
            ],
            'email' => '',
        ]);
    }

    public function register(): void {
        if (Auth::check()) {
            $this->redirect('user/profile');
        }

        $this->view('auth/register', [
            'title' => 'Create Reporter Account',
            'errors' => [],
            'values' => [],
        ]);
    }

    public function storeRegistration(): void {
        $this->requirePost();

        try {
            Csrf::requireValid($_POST['_token'] ?? null);

            $user = (new UserService())->registerReporter($_POST);

            Auth::attempt($user->getEmail(), (string) ($_POST['password'] ?? ''));

            Flash::set('success', 'Your Reporter account was created.');
            $this->redirect('');
        } catch (ValidationException $error) {
            http_response_code(422);

            $this->view('auth/register', [
                'title' => 'Create Reporter Account',
                'errors' => $error->getErrors(),
                'values' => $_POST,
            ]);
        } catch (AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function login(): void {
        $this->requirePost();

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        $user = null;

        try {
            Csrf::requireValid($_POST['_token'] ?? null);
        } catch (AuthorizationException $error) {
            $errors['password'] = 'Unable to verify the login request. Please try again.';
        }

        /*
         * Validate email format first.
         */
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } else {
            /*
             * Check database even if password is empty.
             * This allows "Email address is not registered" to show together with
             * "Password is required."
             */
            $user = User::findByEmail($email);

            if ($user === null || $user->isDeleted()) {
                $errors['email'] = 'Email address is not registered.';
            } elseif (!$user->isActive()) {
                $errors['email'] = 'This account is inactive. Please contact an Administrator.';
            }
        }

        /*
         * Validate password separately.
         */
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        /*
         * Only check password correctness when:
         * - email is valid
         * - user exists
         * - user is active
         * - password is not empty
         */
        if (
                $errors === [] &&
                $user instanceof User &&
                !password_verify($password, $user->getPasswordHash())
        ) {
            $errors['password'] = 'Password is incorrect.';
        }

        /*
         * Login only when all validation passed.
         */
        if ($errors === [] && !Auth::attempt($email, $password)) {
            $errors['password'] = 'Unable to sign in. Please try again.';
        }

        if ($errors !== []) {
            http_response_code(422);

            $this->view('auth/login', [
                'title' => 'Sign In',
                'errors' => $errors,
                'email' => $email,
            ]);

            return;
        }

        Flash::set('success', 'Welcome back, ' . Auth::user()->getFullName() . '.');
        $this->redirect('');
    }

    public function logout(): void {
        $this->requirePost();

        try {
            Csrf::requireValid($_POST['_token'] ?? null);
        } catch (AuthorizationException $error) {
            $this->handleAccessFailure($error);
            return;
        }

        Auth::logout();

        Flash::set('success', 'You have signed out.');
        $this->redirect('auth');
    }
}