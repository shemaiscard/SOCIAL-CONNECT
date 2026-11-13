<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Mailer;
use App\Core\View;
use App\Models\Security;
use App\Models\User;

final class AuthController
{
    public function showLogin(): void
    {
        View::render('auth/login', [
            'title' => 'Log in',
            'next' => safe_next($_GET['next'] ?? ''),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $email = mb_strtolower(input('email', 190));
        $password = (string) ($_POST['password'] ?? '');
        $next = safe_next($_POST['next'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (Security::tooManyFailures($email, $ip)) {
            back_with_errors(['form' => 'Too many failed attempts. Wait 15 minutes, then try again.'], ['email' => $email]);
        }

        $user = $email !== '' ? User::findForLogin($email) : null;
        // Verify against a dummy hash when the email is unknown so both cases take the same time.
        $hash = $user['password_hash'] ?? '$2y$12$0000000000000000000000.0000000000000000000000000000000';
        if (!password_verify($password, $hash) || $user === null) {
            Security::recordFailure($email, $ip);
            back_with_errors(['form' => 'That email and password do not match an account.'], ['email' => $email]);
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::setPassword((int) $user['id'], $password);
        }
        Security::clearFailures($email, $ip);
        Auth::login((int) $user['id']);
        redirect($next !== '' ? $next : '/');
    }

    public function showRegister(): void
    {
        View::render('auth/register', ['title' => 'Create your account'], 'layouts/auth');
    }

    public function register(): void
    {
        $data = [
            'first_name' => input('first_name'),
            'last_name' => input('last_name'),
            'email' => mb_strtolower(input('email')),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $errors = self::validateName($data);

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (User::emailTaken($data['email'])) {
            $errors['email'] = 'An account already uses this email. Log in instead.';
        }
        if ($error = self::passwordProblem($password, (string) ($_POST['password_confirmation'] ?? ''))) {
            $errors['password'] = $error;
        }
        if ($errors) {
            back_with_errors($errors, $data);
        }

        $id = User::create($data['first_name'], $data['last_name'], $data['email'], $password);
        Auth::login($id);
        flash('toast', 'Welcome to Social Connect, ' . $data['first_name'] . '.');
        redirect('/');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    public function showForgot(): void
    {
        View::render('auth/forgot', ['title' => 'Reset your password'], 'layouts/auth');
    }

    public function forgot(): void
    {
        $email = mb_strtolower(input('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            back_with_errors(['email' => 'Enter the email address you signed up with.'], ['email' => $email]);
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (Security::tooManyResetRequests($email, $ip)) {
            back_with_errors(['email' => 'Too many reset requests. Wait an hour, then try again.'], ['email' => $email]);
        }
        Security::recordResetRequest($email, $ip);
        $user = User::findForLogin($email);
        if ($user !== null) {
            $link = absolute_url('/reset-password?token=' . Security::createResetToken((int) $user['id']));
            Mailer::send(
                $email,
                'Reset your Social Connect password',
                "Someone asked to reset the password for this account.\n\n"
                . "Open this link within 60 minutes to choose a new password:\n$link\n\n"
                . 'If it was not you, ignore this email. Your password stays the same.'
            );
            if (Env::bool('APP_DEBUG')) {
                flash('dev_link', $link);
            }
        }
        // Same answer either way, so the form does not reveal which emails have accounts.
        flash('status', 'If an account uses that email, a reset link is on its way. The link works for 60 minutes.');
        redirect('/forgot-password');
    }

    public function showReset(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        View::render('auth/reset', [
            'title' => 'Choose a new password',
            'token' => $token,
            'valid' => Security::resetTokenUser($token) !== null,
        ], 'layouts/auth');
    }

    public function reset(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $userId = Security::resetTokenUser($token);
        if ($userId === null) {
            flash('toast_error', 'That reset link is invalid or has expired. Ask for a new one.');
            redirect('/forgot-password');
        }
        $password = (string) ($_POST['password'] ?? '');
        if ($error = self::passwordProblem($password, (string) ($_POST['password_confirmation'] ?? ''))) {
            back_with_errors(['password' => $error]);
        }
        User::setPassword($userId, $password);
        Security::consumeResetToken($token);
        flash('status', 'Your password was changed and every device was signed out. Log in with the new one.');
        redirect('/login');
    }

    /** Shared by registration and the settings page. */
    public static function validateName(array $data): array
    {
        $errors = [];
        foreach (['first_name' => 'first name', 'last_name' => 'last name'] as $field => $label) {
            $length = mb_strlen($data[$field]);
            if ($length === 0) {
                $errors[$field] = "Enter your $label.";
            } elseif ($length > 50) {
                $errors[$field] = ucfirst($label) . ' can be up to 50 characters.';
            }
        }
        return $errors;
    }

    public static function passwordProblem(string $password, string $confirmation): ?string
    {
        return match (true) {
            strlen($password) < 8 => 'Use at least 8 characters.',
            strlen($password) > 72 => 'Use 72 characters or fewer.',
            $password !== $confirmation => 'The two passwords do not match.',
            default => null,
        };
    }
}
