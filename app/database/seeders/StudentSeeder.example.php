<?php
/**
 * Example Seeder - Populate database with test/seed data
 * 
 * Seeders populate Firestore with sample data for development and testing.
 * Run: php app/database/seeders/run.php
 */

namespace App\Database\Seeders;

use App\Services\FirebaseService;

class StudentSeeder
{
    public function __construct(private FirebaseService $firebase) {}
    
    /**
     * Run the seeder
     */
    public function run(): void
    {
        $collection = $this->firebase->collection('students');
        
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
            $collection->add($student);
        }
        
        echo "StudentSeeder completed: " . count($students) . " records created.\n";
    }
}

class RoomSeeder
{
    public function __construct(private FirebaseService $firebase) {}
    
    public function run(): void
    {
        $collection = $this->firebase->collection('rooms');
        
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
            $collection->add($room);
        }
        
        echo "RoomSeeder completed: " . count($rooms) . " records created.\n";
    }
}
