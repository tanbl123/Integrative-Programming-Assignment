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

        $email = mb_strtolower($this->input('email'));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];

        try {
            Csrf::requireValid($_POST['_token'] ?? null);
        } catch (AuthorizationException $error) {
            $errors['form'] = $error->getMessage();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }

        if ($errors === [] && !Auth::attempt($email, $password)) {
            $errors['form'] = 'The email or password is incorrect, or the account is inactive.';
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
