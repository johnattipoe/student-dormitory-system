<?php
/**
 * Example Seeder - Populate database with test/seed data
 * 
 * Seeders populate Firestore with sample data for development and testing.
 * Run: php app/database/seeders/run.php
 */

namespace App\Database\Seeders;

use App\Services\FirebaseService;

/**
 * Example Seeder - Populate database with test data
 * 
 * Usage: php app/database/seeders/run.php
 */
class StudentSeeder
{
    public function run(): void
    {
        $firebase = FirebaseService::getInstance();
        
        $students = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '555-1001',
                'student_id' => 'STU001',
                'house_id' => 'HOUSE1',
                'room_id' => 'ROOM101',
                'status' => 'active',
                'created_at' => date('c'),
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'phone' => '555-1002',
                'student_id' => 'STU002',
                'house_id' => 'HOUSE1',
                'room_id' => 'ROOM102',
                'status' => 'active',
                'created_at' => date('c'),
            ],
        ];
        
        foreach ($students as $student) {
            $firebase->addDocument('students', $student);
        }
        
        echo "StudentSeeder completed: " . count($students) . " records created.\n";
    }
}

class RoomSeeder
{
    public function run(): void
    {
        $firebase = FirebaseService::getInstance();
        
        $rooms = [
            [
                'room_number' => '101',
                'house_id' => 'HOUSE1',
                'capacity' => 2,
                'occupied' => 1,
                'status' => 'active',
            ],
            [
                'room_number' => '102',
                'house_id' => 'HOUSE1',
                'capacity' => 2,
                'occupied' => 1,
                'status' => 'active',
            ],
        ];
        
        foreach ($rooms as $room) {
            $firebase->addDocument('rooms', $room);
        }
        
        echo "RoomSeeder completed: " . count($rooms) . " records created.\n";
    }
}
