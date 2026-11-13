<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Upload;
use App\Core\ValidationError;
use App\Core\View;
use App\Models\User;

final class SettingsController
{
    public function show(): void
    {
        View::render('pages/settings', ['title' => 'Settings', 'active' => 'settings', 'user' => Auth::user()]);
    }

    public function profile(): void
    {
        $data = [
            'first_name' => input('first_name'),
            'last_name' => input('last_name'),
            'bio' => input('bio'),
            'location' => input('location'),
            'birth_date' => input('birth_date'),
        ];
        $errors = AuthController::validateName($data);
        if (mb_strlen($data['bio']) > 300) {
            $errors['bio'] = 'Keep your bio to 300 characters.';
        }
        if (mb_strlen($data['location']) > 100) {
            $errors['location'] = 'Keep the location to 100 characters.';
        }
        if ($data['birth_date'] !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['birth_date']);
            if (!$date || $date->format('Y-m-d') !== $data['birth_date'] || $date > new \DateTimeImmutable('today')
                || $date < new \DateTimeImmutable('1900-01-01')) {
                $errors['birth_date'] = 'Enter a real date in the past.';
            }
        }
        if ($errors) {
            back_with_errors($errors, $data);
        }
        User::updateProfile((int) Auth::id(), $data);
        flash('toast', 'Profile saved.');
        redirect('/settings');
    }

    public function avatar(): void
    {
        $this->photo('avatar_path', 'avatars', 'Profile photo');
    }

    public function cover(): void
    {
        $this->photo('cover_path', 'covers', 'Cover photo');
    }

    private function photo(string $column, string $dir, string $label): void
    {
        $user = Auth::user();
        if (isset($_POST['remove'])) {
            User::setPhoto((int) $user['id'], $column, null);
            Upload::delete($user[$column]);
            flash('toast', "$label removed.");
            redirect('/settings#photos');
        }
        try {
            $image = Upload::image($_FILES['photo'] ?? null, $dir);
        } catch (ValidationError $e) {
            back_with_errors([$column => $e->getMessage()]);
        }
        if ($image === null) {
            back_with_errors([$column => 'Choose an image first.']);
        }
        User::setPhoto((int) $user['id'], $column, $image['path']);
        Upload::delete($user[$column]);
        flash('toast', "$label updated.");
        redirect('/settings#photos');
    }

    public function email(): void
    {
        $user = Auth::user();
        $email = mb_strtolower(input('email'));
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) User::passwordHash((int) $user['id']))) {
            back_with_errors(['email_password' => 'Your current password is not correct.'], ['email' => $email]);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            back_with_errors(['email' => 'Enter a valid email address.'], ['email' => $email]);
        }
        if (User::emailTaken($email, (int) $user['id'])) {
            back_with_errors(['email' => 'Another account already uses this email.'], ['email' => $email]);
        }
        User::setEmail((int) $user['id'], $email);
        flash('toast', 'Email updated.');
        redirect('/settings#account');
    }

    public function password(): void
    {
        $id = (int) Auth::id();
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) User::passwordHash($id))) {
            back_with_errors(['current_password' => 'Your current password is not correct.']);
        }
        $new = (string) ($_POST['password'] ?? '');
        if ($error = AuthController::passwordProblem($new, (string) ($_POST['password_confirmation'] ?? ''))) {
            back_with_errors(['password' => $error]);
        }
        User::setPassword($id, $new);
        Auth::login($id); // other devices are now signed out; this one stays in
        flash('toast', 'Password changed. Other devices were signed out.');
        redirect('/settings#account');
    }
}
