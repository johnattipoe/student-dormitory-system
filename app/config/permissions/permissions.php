<?php
/**
 * Permission matrix mirrors the Role Permissions table in the design doc.
 * Levels: full | manage | view | own | limited | none
 */
$permissions = [
    ROLE_ADMIN => [
        'users' => 'full', 'students' => 'full', 'houses' => 'full', 'rooms' => 'full',
        'room_allocation' => 'full', 'attendance' => 'full', 'visitors' => 'full',
        'visitor_requests' => 'full', 'incidents' => 'full', 'medical_records' => 'full',
        'reports' => 'full', 'notifications' => 'full', 'activity_logs' => 'full',
        'settings' => 'full', 'announcements' => 'full', 'message_parents' => 'full',
        'emergency_alerts' => 'full', 'emergency_contacts' => 'full', 'health_reports' => 'full',
        'audit_trail' => 'full', 'backup_restore' => 'full', 'profile' => 'full',
    ],
    ROLE_HOUSE_MASTER => [
        'users' => 'view', 'students' => 'full', 'houses' => 'full', 'rooms' => 'full',
        'room_allocation' => 'manage', 'attendance' => 'manage', 'visitors' => 'view',
        'visitor_requests' => 'view', 'incidents' => 'manage', 'medical_records' => 'none',
        'reports' => 'full', 'notifications' => 'full', 'activity_logs' => 'view',
        'settings' => 'none', 'announcements' => 'full', 'message_parents' => 'manage',
        'emergency_alerts' => 'manage', 'emergency_contacts' => 'view', 'health_reports' => 'full',
        'audit_trail' => 'view', 'backup_restore' => 'none', 'profile' => 'own',
    ],
    ROLE_HOUSE_MISTRESS => [
        'users' => 'view', 'students' => 'full', 'houses' => 'full', 'rooms' => 'full',
        'room_allocation' => 'manage', 'attendance' => 'manage', 'visitors' => 'view',
        'visitor_requests' => 'view', 'incidents' => 'manage', 'medical_records' => 'none',
        'reports' => 'full', 'notifications' => 'full', 'activity_logs' => 'view',
        'settings' => 'none', 'announcements' => 'full', 'message_parents' => 'manage',
        'emergency_alerts' => 'manage', 'emergency_contacts' => 'view', 'health_reports' => 'full',
        'audit_trail' => 'view', 'backup_restore' => 'none', 'profile' => 'own',
    ],
    ROLE_SENIOR_HOUSEPARENT => [
        'users' => 'view', 'students' => 'full', 'houses' => 'view', 'rooms' => 'view',
        'room_allocation' => 'view', 'attendance' => 'manage', 'visitors' => 'manage',
        'visitor_requests' => 'manage', 'incidents' => 'manage', 'medical_records' => 'none',
        'reports' => 'full', 'notifications' => 'full', 'activity_logs' => 'view',
        'settings' => 'none', 'announcements' => 'full', 'message_parents' => 'manage',
        'emergency_alerts' => 'manage', 'emergency_contacts' => 'view', 'health_reports' => 'full',
        'audit_trail' => 'view', 'backup_restore' => 'none', 'profile' => 'own',
    ],
    ROLE_SECURITY => [
        'users' => 'none', 'students' => 'view', 'houses' => 'none', 'rooms' => 'none',
        'room_allocation' => 'none', 'attendance' => 'none', 'visitors' => 'full',
        'visitor_requests' => 'manage', 'incidents' => 'manage', 'medical_records' => 'none',
        'reports' => 'limited', 'notifications' => 'full', 'activity_logs' => 'none',
        'settings' => 'none', 'announcements' => 'view', 'message_parents' => 'none',
        'emergency_alerts' => 'view', 'emergency_contacts' => 'manage', 'health_reports' => 'none',
        'audit_trail' => 'none', 'backup_restore' => 'none', 'profile' => 'own',
    ],
    ROLE_NURSE => [
        'users' => 'none', 'students' => 'view', 'houses' => 'none', 'rooms' => 'none',
        'room_allocation' => 'none', 'attendance' => 'none', 'visitors' => 'none',
        'visitor_requests' => 'none', 'incidents' => 'manage', 'medical_records' => 'full',
        'reports' => 'limited', 'notifications' => 'full', 'activity_logs' => 'none',
        'settings' => 'none', 'announcements' => 'view', 'message_parents' => 'manage',
        'emergency_alerts' => 'view', 'emergency_contacts' => 'view', 'health_reports' => 'full',
        'audit_trail' => 'view', 'backup_restore' => 'none', 'profile' => 'own',
    ],
    ROLE_STUDENT => [
        'users' => 'none', 'students' => 'own', 'houses' => 'view', 'rooms' => 'own',
        'room_allocation' => 'view', 'attendance' => 'own', 'visitors' => 'own',
        'visitor_requests' => 'own', 'incidents' => 'own', 'medical_records' => 'own',
        'reports' => 'own', 'notifications' => 'own', 'activity_logs' => 'none',
        'settings' => 'none', 'announcements' => 'view', 'message_parents' => 'own',
        'emergency_alerts' => 'view', 'emergency_contacts' => 'view', 'health_reports' => 'own',
        'audit_trail' => 'none', 'backup_restore' => 'none', 'profile' => 'own',
    ],
];

// Custom permissions can be managed through Firestore (permissions collection)
// Merge with base permissions as needed
return $permissions;
