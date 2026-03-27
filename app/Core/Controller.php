<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function url(string $path): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $prefix = rtrim((string) Config::get('app.base_path', ''), '/');
        $path = '/' . ltrim($path, '/');
        if ($prefix !== '') {
            return $base . $prefix . $path;
        }

        return $base . $path;
    }

    protected function asset(string $path): string
    {
        return $this->url('assets/' . ltrim($path, '/'));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = []): void
    {
        View::render($template, $data);
    }

    protected function redirect(string $path): never
    {
        Response::redirect($this->url($path));
    }

    /** @return array{user_id:int, organization_id:int, role:string}|null */
    protected function authContext(): ?array
    {
        $userId = Session::get('user_id');
        $orgId = Session::get('organization_id');
        $role = Session::get('role');
        if (!is_int($userId) && !is_numeric($userId)) {
            return null;
        }
        if (!is_int($orgId) && !is_numeric($orgId)) {
            return null;
        }
        if (!is_string($role) || $role === '') {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'organization_id' => (int) $orgId,
            'role' => $role,
        ];
    }

    protected function requireAuth(): array
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            Session::flash('error', 'Please sign in to continue.');
            $this->redirect('/login');
        }

        return $ctx;
    }

    /**
     * @param list<string> $roles
     * @return array{user_id:int, organization_id:int, role:string}
     */
    protected function requireAuthRole(array $roles): array
    {
        $ctx = $this->requireAuth();
        if (!in_array($ctx['role'], $roles, true)) {
            Session::flash('error', 'You do not have permission to do that.');
            $this->redirect('/dashboard');
        }

        return $ctx;
    }
}
