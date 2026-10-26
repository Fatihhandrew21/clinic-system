<?php
declare(strict_types=1);

namespace Clinic\Service;

use Clinic\Exception\AuthorizationException;
use Clinic\Exception\ValidationException;
use Clinic\Repository\UserRepository;

final class AccountService
{
    public function __construct(private readonly UserRepository $users) {}

    public function registerPatient(string $name, string $email, string $password, string $phone): int
    {
        $name = trim($name); $email = strtolower(trim($email)); $phone = trim($phone);
        if ($name === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Name, phone and a valid email are required.');
        }
        $this->assertPassword($password);
        if ($this->users->findByEmail($email) !== null) {
            throw new ValidationException('Email is already registered.');
        }
        return $this->users->insertPatient($name, $email, password_hash($password, PASSWORD_DEFAULT), $phone);
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new AuthorizationException('Invalid email or password.');
        }
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['role']    = $user['role'];
        unset($user['password_hash']);
        return $user;
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION = [];
        session_destroy();
    }

    public function updateProfile(int $userId, string $name, string $phone, ?string $newPassword = null): void
    {
        if (trim($name) === '' || trim($phone) === '') {
            throw new ValidationException('Name and phone are required.');
        }
        $hash = null;
        if ($newPassword !== null && $newPassword !== '') {
            $this->assertPassword($newPassword);
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $this->users->updateProfile($userId, trim($name), trim($phone), $hash);
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new ValidationException('Password must be at least 8 characters.');
        }
    }
}
