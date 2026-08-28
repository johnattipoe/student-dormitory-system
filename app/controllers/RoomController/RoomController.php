<?php

namespace App\Controllers;

use App\Services\RoomService;
use App\Services\RoomAllocationService;

class RoomController
{
    private RoomService $roomService;
    private RoomAllocationService $allocationService;

    public function __construct()
    {
        $this->roomService = new RoomService();
        $this->allocationService = new RoomAllocationService();
    }

    public function index(): void
    {
        require_role(ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT);

        $rooms = $this->roomService->all();

        include __DIR__ . '/../../../public/views/rooms/index/index.php';
    }

    public function create(): void
    {
        require_role(ROLE_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../../../public/views/rooms/create/create.php';
            return;
        }

        $data = [
            'roomNumber' => sanitize($_POST['roomNumber'] ?? ''),
            'houseId' => sanitize($_POST['houseId'] ?? ''),
            'capacity' => (int) ($_POST['capacity'] ?? 0),
            'status' => sanitize($_POST['status'] ?? 'available'),
        ];

        $result = $this->roomService->create($data);

        flash(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );

        redirect(
            base_url('index.php?route=/views/rooms/index/index.php')
        );
    }

    public function edit(): void
    {
        require_role(ROLE_ADMIN);

        $id = sanitize($_GET['id'] ?? '');

        $room = $this->roomService->find($id);

        include __DIR__ . '/../../../public/views/rooms/edit/edit.php';
    }

    public function allocation(): void
    {
        require_role(ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = [
                'studentId' => sanitize($_POST['studentId'] ?? ''),
                'roomId' => sanitize($_POST['roomId'] ?? ''),
                'allocatedBy' => current_user()['uid'] ?? null,
            ];

            $result = $this->allocationService->allocate($data);

            flash(
                $result['success'] ? 'success' : 'error',
                $result['message']
            );

            redirect(
                base_url('index.php?route=/views/rooms/allocation/allocation.php')
            );
        }

        $rooms = $this->roomService->available();

        include __DIR__ . '/../../../public/views/rooms/allocation/allocation.php';
    }

    public function occupancy(): void
    {
        require_role(ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT);

        $occupancy = $this->roomService->occupancy();

        include __DIR__ . '/../../../public/views/rooms/occupancy/occupancy.php';
    }
}