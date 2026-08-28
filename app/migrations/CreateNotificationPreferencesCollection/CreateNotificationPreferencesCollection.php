<?php

namespace App\Migrations;

use App\Services\FirebaseService;

/**
 * Migration: Create notification_preferences collection for user notification settings
 * Timestamp: 2026-08-18
 * Description: Stores user notification preferences including email, frequency, and quiet hours
 */
class CreateNotificationPreferencesCollection
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function up(): void
    {
        echo "Creating notification_preferences collection...\n";
        
        // Create collection with sample document to ensure it exists
        $samplePreferences = [
            'userId' => '__sample__',
            'emailNotifications' => true,
            'attendanceAlerts' => true,
            'visitorUpdates' => true,
            'incidentAlerts' => true,
            'medicalAlerts' => true,
            'systemNotifications' => true,
            'quietHours' => '',
            'notificationFrequency' => 'immediate',
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
        ];
        
        try {
            $docId = $this->firebase->addDocument('notification_preferences', $samplePreferences);
            
            // Remove sample document
            $this->firebase->deleteDocument('notification_preferences', $docId);
            
            echo "✓ notification_preferences collection created successfully.\n";
        } catch (\Throwable $e) {
            echo "✗ Error creating notification_preferences collection: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down(): void
    {
        echo "Dropping notification_preferences collection...\n";
        
        try {
            $docs = $this->firebase->getCollection('notification_preferences', [], 1000);
            foreach ($docs as $doc) {
                $this->firebase->deleteDocument('notification_preferences', (string) ($doc['id'] ?? ''));
            }
            echo "✓ notification_preferences collection dropped successfully.\n";
        } catch (\Throwable $e) {
            echo "✗ Error dropping notification_preferences collection: " . $e->getMessage() . "\n";
        }
    }
}
