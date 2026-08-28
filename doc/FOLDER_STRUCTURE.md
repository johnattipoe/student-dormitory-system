# Student Dormitory System - Enhanced Folder Structure

## Overview
This document outlines the expanded, production-ready folder structure following modern PHP framework conventions.

---

## Core Application Structure (`/app`)

### Controllers (`/app/controllers`)
- One subfolder per controller: `AdminController/AdminController.php`, `SecurityController/SecurityController.php`, `StudentController/StudentController.php`, etc.
- Handle HTTP requests and coordinate with services/models
- Keep logic thin; delegate to services

### Models (`/app/models`)
- Firestore document models: Student, User, Room, Attendance, etc.
- Contain document structure and relationships
- Provide basic query methods
- One subfolder per model: `Student/Student.php`, `Room/Room.php`, etc.

### Services (`/app/services`)
- Business logic: NotificationService, AttendanceService, AuthService, etc.
- Orchestrate models and external integrations (Firebase, email)
- Handle complex operations
- Examples: PreferenceService, ReportService, AuditService
- One subfolder per service: `AuthService/AuthService.php`, `StudentService/StudentService.php`, etc.

### Repositories (`/app/repositories`) - **NEW**
- Data access abstraction layer
- Wrap Firestore queries and operations
- Examples: StudentRepository, RoomRepository, VisitorRepository
- Usage: `$students = $studentRepo->findActive()`

### Middleware (`/app/middleware`)
- One subfolder per middleware: `AuthMiddleware/AuthMiddleware.php`, `RoleMiddleware/RoleMiddleware.php`, etc.
- Examples: AuthMiddleware, RoleMiddleware, AdminMiddleware
- Verify permissions before controller execution

### Helpers (`/app/helpers`)
- Utility functions: auth, session, validation, response, functions
- No classes; pure procedural helpers
- Keep reusable logic here
- One subfolder per helper: `auth/auth.php`, `functions/functions.php`, etc.

### Traits (`/app/traits`) - **NEW**
- Shared behavior across classes
- Examples: Timestampable, Auditable, HasOwner
- Usage: `use HasTimestamps;` in models/services

### DTOs (`/app/dto`) - **NEW**
- Data Transfer Objects for type-safe data passing
- Examples: CreateStudentDTO, UpdatePreferenceDTO, AttendanceReportDTO
- Validate and structure data before use

### Resources (`/app/resources`) - **NEW**
- **Requests/** - Validate/transform incoming HTTP data
  - Examples: CreateStudentRequest, UpdateRoomRequest
  - Handle form validation before controller processing
- **Responses/** - Transform outgoing data to consistent format
  - Examples: StudentResponse, IncidentResponse
  - Format API responses and form data

### Enums (`/app/enums`) - **NEW**
- Type-safe constants and enumerations
- Examples: UserRole, IncidentSeverity, AttendanceStatus
- Prevent "magic string" bugs
- Usage: `UserRole::ADMIN`, `IncidentSeverity::HIGH`

### Exceptions (`/app/exceptions`) - **NEW**
- Custom exception classes
- Examples: UnauthorizedException, ValidationException, ResourceNotFoundException
- Better error handling and HTTP status mapping

### Jobs (`/app/jobs`) - **NEW**
- Queued tasks for background processing
- Examples: SendNotificationJob, GenerateReportJob, ProcessAttendanceJob
- Keep web requests fast
- Integrate with queue system

### Events (`/app/events`) - **NEW**
- Domain events for decoupled systems
- Examples: StudentCreatedEvent, IncidentReportedEvent, VisitorArrivedEvent
- Listeners subscribe and react
- Enable notifications, logging, audits

### Config (`/app/config`)
- Application configuration: `app/app.php`, `constants/constants.php`
- Firebase settings: `firebase/firebase.php`, `firebase-auth/firebase-auth.php`
- Email settings: `mail/mail.php`
- Permissions and feature flags: `permissions/permissions.php`

### Migrations (`/app/migrations`)
- Firestore collection initialization and setup
- One subfolder per migration: `CreateNotificationPreferencesCollection/CreateNotificationPreferencesCollection.php`, etc.
- Executable via `php Runner.php up`

### Seeders (`/app/database/seeders`) - **NEW**
- Populate database with sample/test data
- Examples: StudentSeeder, RoomSeeder, UserSeeder
- Run during development and testing

### Tests (`/app/tests`) - **NEW**
- **Unit/** - Test individual classes/methods in isolation
  - Examples: PreferenceServiceTest, ValidationTest
  - Mock external dependencies
- **Feature/** - Integration tests for real workflows
  - Examples: StudentRegistrationTest, VisitorCheckInTest
  - Test across multiple services/models

---

## Public Web Root (`/public`)

### Views (`/public/views`)
- Role-based subdirectories: admin/, student/, nurse/, security/
- Blade-like or plain PHP templates
- Global components are organized as one subfolder per file: `components/header/header.php`, `components/footer/footer.php`, etc.
- Admin views use one subfolder per PHP file, for example `admin/attendance/index/index.php` and `admin/rooms/allocation/allocation.php`.

### Assets (`/public/assets`)
- **css/** - Bootstrap + custom styles, with one subfolder per stylesheet (`css/style/style.css`, etc.)
- **js/** - Global and role-specific scripts, with one subfolder per script (`js/app/app.js`, etc.)
- **images/** - Icons, logos, etc.
- **uploads/** - User-uploaded files

### API (`/public/api`) - **NEW**
- RESTful endpoints (if used)
- Examples: api/students, api/attendance, api/notifications
- JSON request/response
- Can mirror controller structure

### AJAX (`/public/ajax`)
- Endpoint scripts for dynamic updates
- One subfolder per endpoint (`attendance/mark_attendance/mark_attendance.php`, etc.)
- Leverage existing architecture

---

## Supporting Directories

### Firebase (`/firebase`)
- Firestore rules, indexes, configuration
- firebase.json, firestore.rules, firestore.indexes.json

### Storage (`/storage`)
- Logs, reports, exports, temp files
- logs/, reports/, exports/, temp/

### Scripts (`/scripts`)
- CLI utilities: overstay_check.php, cron helpers
- Admin tasks, batch operations

### Vendor (`/vendor`)
- Composer dependencies (auto-generated)

---

## Usage Guidelines

### Adding a New Feature

1. **Create a Model** in `/app/models`
   ```php
   class MyEntity { ... }
   ```

2. **Create a Repository** in `/app/repositories`
   ```php
   class MyEntityRepository { 
     public function findById($id) { ... }
   }
   ```

3. **Create a Service** in `/app/services`
   ```php
   class MyEntityService {
     public function __construct(MyEntityRepository $repo) { ... }
   }
   ```

4. **Create Request/Response** in `/app/resources`
   ```php
   class CreateMyEntityRequest { 
     public function validate() { ... }
   }
   ```

5. **Add to Controller** in `/app/controllers`
   ```php
   class MyController {
     public function store(CreateMyEntityRequest $request) {
       $service->create($request->validated());
     }
   }
   ```

6. **Create Events** (optional) in `/app/events`
   ```php
   class MyEntityCreatedEvent { ... }
   ```

7. **Create Tests** in `/app/tests/{Unit|Feature}`
   ```php
   class MyEntityServiceTest extends TestCase { ... }
   ```

### Naming Conventions

| Category | Pattern | Example |
|----------|---------|---------|
| Models | Singular, PascalCase | `Student`, `MedicalRecord` |
| Services | Singular + "Service" | `StudentService`, `AttendanceService` |
| Controllers | Singular + "Controller" | `StudentController`, `AdminController` |
| Repositories | Singular + "Repository" | `StudentRepository`, `RoomRepository` |
| Requests | Action + Entity + "Request" | `CreateStudentRequest`, `UpdateRoomRequest` |
| Responses | Entity + "Response" | `StudentResponse`, `IncidentResponse` |
| Traits | Capitalized behavior | `HasTimestamps`, `Auditable`, `Loggable` |
| Enums | Singular, uppercase constants | `UserRole::ADMIN`, `IncidentSeverity::HIGH` |
| Events | Entity + Action + "Event" | `StudentCreatedEvent`, `IncidentReportedEvent` |
| Jobs | Action + Entity + "Job" | `SendNotificationJob`, `GenerateReportJob` |
| Exceptions | Specific, descriptive | `UnauthorizedException`, `ValidationException` |

---

## Autoloader Configuration

Update `public/bootstrap.php` to auto-load new namespaces:

```php
// Repositories
if (str_starts_with($class, 'App\\Repositories\\')) {
    $className = str_replace('App\\Repositories\\', '', $class);
    $path = __DIR__ . '/../app/repositories/' . $className . '.php';
    if (file_exists($path)) { require_once $path; return true; }
}

// DTOs, Enums, Traits, Exceptions, Jobs, Events (similar pattern)
```

---

## Benefits of This Structure

✅ **Scalability** - Easy to add features without cluttering existing code  
✅ **Maintainability** - Clear separation of concerns  
✅ **Testability** - Isolated units and integration tests  
✅ **Reusability** - Traits, utilities, and shared logic  
✅ **Type Safety** - DTOs and Enums prevent magic strings  
✅ **Performance** - Background jobs keep web requests fast  
✅ **Decoupling** - Events enable async, non-blocking operations  
✅ **Professional** - Follows Laravel/Symfony conventions  

---

## Migration Path

1. ✅ Folder structure created
2. ⏳ Gradually move existing helpers into Traits
3. ⏳ Create DTOs for major data flows
4. ⏳ Add Enums for constants
5. ⏳ Build custom Exceptions
6. ⏳ Write unit tests in `/app/tests/Unit`
7. ⏳ Write feature tests in `/app/tests/Feature`
8. ⏳ Extract data access into Repositories
9. ⏳ Create Events for notifications/audits
10. ⏳ Move background tasks to Jobs

---

Generated: 2026-08-18
