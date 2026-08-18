<?php
/**
 * Example Feature Test
 * 
 * Feature tests verify real workflows across multiple services/models.
 * Integration tests that use real dependencies or in-memory stubs.
 * 
 * Run: phpunit app/tests/Feature/StudentRegistrationTest.php
 */

namespace App\Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\StudentService;
use App\Services\AuthService;
use App\DTO\CreateStudentDTO;

class StudentRegistrationTest extends TestCase
{
    private StudentService $studentService;
    private AuthService $authService;
    
    protected function setUp(): void
    {
        // Initialize real services (or use stubs for Firestore)
        $this->studentService = new StudentService();
        $this->authService = new AuthService();
    }
    
    public function testStudentCanRegister()
    {
        // Prepare test data
        $dto = CreateStudentDTO::fromArray([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'student_id' => 'STU001',
            'house_id' => 'HOUSE1',
            'room_id' => 'ROOM101',
        ]);
        
        // Register student
        $studentId = $this->studentService->create($dto);
        $this->assertNotEmpty($studentId);
        
        // Verify student was created
        $student = $this->studentService->getById($studentId);
        $this->assertEquals('John', $student['first_name']);
        $this->assertEquals('STU001', $student['student_id']);
    }
    
    public function testStudentCanLogin()
    {
        // Create a test student account
        $user = $this->authService->register([
            'email' => 'student@example.com',
            'password' => 'password123',
            'role' => 'student',
        ]);
        
        // Login
        $token = $this->authService->login('student@example.com', 'password123');
        $this->assertNotEmpty($token);
        
        // Verify token is valid
        $this->assertTrue($this->authService->verifyToken($token));
    }
    
    public function testStudentRegistrationRequiresValidEmail()
    {
        // Expect exception for invalid email
        $this->expectException(\InvalidArgumentException::class);
        
        $dto = CreateStudentDTO::fromArray([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'invalid-email',  // Invalid
            'phone' => '1234567890',
            'student_id' => 'STU002',
            'house_id' => 'HOUSE1',
            'room_id' => 'ROOM102',
        ]);
        
        $this->studentService->create($dto);
    }
}
