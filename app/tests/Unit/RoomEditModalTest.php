<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RoomEditModalTest extends TestCase
{
    public function testAdminRoomEditModalIncludesHiddenRoomIdField(): void
    {
        $view = file_get_contents(__DIR__ . '/../../../public/views/admin/rooms/index/index.php');

        if ($view === false) {
            throw new \RuntimeException('Unable to read the room index view for testing.');
        }

        if (!str_contains($view, 'name="id"')) {
            throw new \RuntimeException('Hidden room id field is missing from the admin room edit modal.');
        }

        if (!str_contains($view, 'id="roomEditModal-')) {
            throw new \RuntimeException('The room edit modal was not found in the admin room list view.');
        }
    }
}
