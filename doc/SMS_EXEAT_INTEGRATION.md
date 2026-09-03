# SMS Exeat Notification Integration

## What Was Implemented

SMS notifications are now sent to the parent/guardian phone number when an exeat is successfully created.

### File Modified
- `public/views/exeat/create/create.php`

### Key Features

1. **Automatic SMS on Exeat Creation**
   - When a user successfully creates an exeat, an SMS is automatically sent to the registered guardian phone number
   - SMS is only sent if the parent/guardian phone number is available
   - SMS is only sent if SMS notifications are enabled in app settings

2. **Message Content**
   - **Internal Exeat**: `"Your ward [Student Name] has been approved for internal exeat on [Date]. Dormitory Administration."`
   - **External Exeat**: `"Your ward [Student Name] has been approved for external exeat from [Start Date] to [End Date]. Dormitory Administration."`
   - Messages are optimized to fit within SMS 160 character limit

3. **Error Handling**
   - If SMS service is not configured or fails, the exeat is still created successfully
   - SMS failures are logged but don't block the exeat creation
   - Users receive appropriate success/error feedback in the UI

### Prerequisites for SMS to Work

#### 1. Environment Configuration
Your `.env` file must have:
```
BMS_ENABLED=true
BMS_API_KEY=jrTN1CRTIIec5ZhnVJztxzhDt
BMS_SENDER_ID=BMS Africa  (optional, defaults to "BMS Africa")
```

**Status**: ✅ Already configured in your `.env` file

#### 2. App Settings
SMS notifications must be enabled in Admin Settings:
- Go to **Admin Dashboard → Settings → Advanced**
- Enable **"SMS Notifications"**
- Save changes

**Current Status**: ❌ SMS notifications are currently **disabled** (default: 0)

#### 3. Student Guardian Data
Each student must have:
- A registered `guardianPhone` field
- This is auto-filled when selecting a student in the create exeat form

### How It Works

1. User creates an exeat and submits the form
2. Exeat is created in the database
3. **If exeat creation succeeds AND SMS is enabled AND guardian phone exists**:
   - An SMS is sent to the guardian phone via BMS Africa API
   - Notification includes student name and exeat dates
4. User is redirected to the exeat list view

### Testing the Integration

1. **Enable SMS Notifications**:
   - Go to Admin Settings → Advanced
   - Check "SMS Notifications"
   - Save

2. **Create a Test Exeat**:
   - Navigate to Exeat → Create Exeat
   - Select a student with a registered guardian phone
   - Fill in the form details
   - Submit the form

3. **Verify**:
   - Check if the exeat was created successfully
   - Verify that an SMS was sent to the guardian phone number
   - Check logs if SMS didn't send: `storage/logs/`

### SMS Service Details

- **Provider**: BMS Africa (mnotify.com API)
- **Character Limit**: 160 characters per SMS
- **Sender ID**: "BMS Africa" (configurable)
- **Timeout**: 15 seconds per request

### Error Logging

If SMS fails to send, the error is logged to:
- `storage/logs/` directory
- Check PHP error logs for detailed information

### Code Changes Summary

The following was added to the POST handler in `create.php`:

```php
// Send SMS notification to parent/guardian
if ($result['success']) {
    $guardianPhone = sanitize($_POST['guardianPhone'] ?? $requestStudent['guardianPhone'] ?? '');
    if ($guardianPhone !== '' && config('app.sms_notifications', '0') === '1') {
        try {
            $smsService = new BmsSmsService();
            $studentName = trim(($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? ''));
            $exeatType = sanitize($_POST['exeatType'] ?? 'external');
            
            // Build appropriate message based on exeat type
            // Send via BmsSmsService
        } catch (Throwable $e) {
            // Log error but don't block exeat creation
        }
    }
}
```

### Next Steps

To enable SMS notifications:
1. Open Admin Settings (if you have admin access)
2. Go to Settings → Advanced
3. Check the "SMS Notifications" checkbox
4. Save changes
5. Create a new exeat - SMS will now be sent to guardian phone

### Support

If SMS is not being sent:
1. Verify SMS notifications are enabled in Admin Settings
2. Verify the student has a guardian phone registered
3. Check the logs in `storage/logs/`
4. Verify BMS API key is correct in `.env`
