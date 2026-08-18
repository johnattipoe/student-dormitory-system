<?php

namespace App\Migrations;

use App\Services\FirebaseService;

/**
 * Migration: Create medical_record_audits collection for tracking severity changes
 * Timestamp: 2026-08-18
 * Description: Stores audit trail of all changes to medical records (severity changes, updates, etc.)
 */
class CreateMedicalRecordAuditsCollection
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function up(): void
    {
        echo "Creating medical_record_audits collection...\n";
        
        // Create collection with sample document to ensure it exists
        $sampleAudit = [
            'recordId' => '__sample__',
            'studentId' => '__sample__',
            'action' => 'created',
            'changedBy' => '__system__',
            'changes' => [
                'severity' => ['from' => '', 'to' => 'normal']
            ],
            'reason' => 'Initial creation',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        try {
            $docId = $this->firebase->addDocument('medical_record_audits', $sampleAudit);
            
            // Remove sample document
            $this->firebase->deleteDocument('medical_record_audits', $docId);
            
            echo "✓ medical_record_audits collection created successfully.\n";
        } catch (\Throwable $e) {
            echo "✗ Error creating medical_record_audits collection: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down(): void
    {
        echo "Dropping medical_record_audits collection...\n";
        
        try {
            $docs = $this->firebase->getCollection('medical_record_audits', [], 1000);
            foreach ($docs as $doc) {
                $this->firebase->deleteDocument('medical_record_audits', (string) ($doc['id'] ?? ''));
            }
            echo "✓ medical_record_audits collection dropped successfully.\n";
        } catch (\Throwable $e) {
            echo "✗ Error dropping medical_record_audits collection: " . $e->getMessage() . "\n";
        }
    }
}
