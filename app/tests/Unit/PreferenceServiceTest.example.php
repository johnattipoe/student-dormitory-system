<?php
/**
 * Example Unit Test
 * 
 * Unit tests verify individual classes/methods in isolation.
 * Mock external dependencies.
 * 
 * Run: phpunit app/tests/Unit/PreferenceServiceTest.php
 */

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\PreferenceService;

class PreferenceServiceTest extends TestCase
{
    private PreferenceService $service;
    
    protected function setUp(): void
    {
        // Mock dependencies
        $this->service = new PreferenceService(
            // Pass mock FirebaseService if needed
        );
    }
    
    public function testIsInQuietHours()
    {
        // Set preferences: quiet hours 22:00-08:00
        $prefs = [
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ];
        
        // Test during quiet hours
        $time = '23:30';
        $this->assertTrue($this->service->isInQuietHours($prefs, $time));
        
        // Test outside quiet hours
        $time = '14:00';
        $this->assertFalse($this->service->isInQuietHours($prefs, $time));
    }
    
    public function testGetNotificationFrequency()
    {
        $prefs = ['notification_frequency' => 'daily'];
        $this->assertEquals('daily', $this->service->getFrequency($prefs));
    }
}
