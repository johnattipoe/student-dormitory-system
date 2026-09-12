<?php

namespace App\Controllers;

use App\Services\UserService;

class UserController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function index(): void
    {
        require_role(ROLE_ADMIN);

        $users = $this->userService->all();

        include __DIR__ . '/../../../public/views/admin/users/index/index.php';
    }

    public function create(): void
    {
        require_role(ROLE_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/admin/users/create/create.php';
            return;
        }

        $data = [
            'name' => sanitize($_POST['name'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'role' => sanitize($_POST['role'] ?? ''),
            'houseId' => sanitize($_POST['houseId'] ?? ''),
            'status' => 'active',
        ];

        $result = $this->userService->create($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/admin/users/index/index.php')
        );
    }

    public function edit(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_GET['id'] ?? '');

        if (!$id) {
            flash('error', 'User ID is required.');
            redirect(base_url('index.php?route=/views/admin/users/index/index.php'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = [
                'name' => sanitize($_POST['name'] ?? ''),
                'role' => sanitize($_POST['role'] ?? ''),
                'houseId' => sanitize($_POST['houseId'] ?? ''),
                'status' => sanitize($_POST['status'] ?? 'active'),
            ];

            $result = $this->userService->update($id, $data);

            flash(
                $result['success'] ? 'success' : 'error',
                $result['message']
            );

            redirect(
                base_url('index.php?route=/views/admin/users/index/index.php')
            );
        }

        $user = $this->userService->find($id);

        include __DIR__ . '/../../../public/views/admin/users/edit/edit.php';
    }

    public function delete(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_POST['id'] ?? '');

        if (!$id) {
            flash('error', 'User ID is required.');
            redirect(base_url('index.php?route=/views/admin/users/index/index.php'));
        }

        $result = $this->userService->delete($id);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/admin/users/index/index.php')
        );
    }
}