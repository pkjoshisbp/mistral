# Organization Deletion - Missing Relationships Fix

## Problem
When trying to delete an organization from the admin panel, a second error occurred:
```
Call to undefined method App\Models\Organization::chatSessions()
```

## Root Cause
The `Organization` model was missing several relationship methods that were being called during the deletion process:
- `chatSessions()` - relationship to ChatSession model
- `chatConversations()` - relationship to ChatConversation model  
- `integrations()` - relationship to Integration model

## Solution

### 1. Added Missing Relationships to Organization Model
**File**: `laravel/app/Models/Organization.php`

Added three new relationship methods:
```php
public function chatSessions()
{
    return $this->hasMany(ChatSession::class);
}

public function chatConversations()
{
    return $this->hasMany(ChatConversation::class);
}

public function integrations()
{
    return $this->hasMany(Integration::class);
}
```

### 2. Enhanced Deletion Logic - OrganizationManager
**File**: `laravel/app/Livewire/OrganizationManager.php`

Updated the deletion process to properly handle cascading deletes:
```php
// Delete related data
$org->organizationData()->delete();

// Delete chat sessions and their messages
foreach ($org->chatSessions as $session) {
    $session->messages()->delete();
}
$org->chatSessions()->delete();

// Delete chat conversations and their messages
foreach ($org->chatConversations as $conversation) {
    $conversation->messages()->delete();
}
$org->chatConversations()->delete();

$org->tokenUsageLogs()->delete();
$org->integrations()->delete();

// Delete the organization
$org->delete();
```

### 3. Enhanced Deletion Logic - IntegrationController
**File**: `laravel/app/Http/Controllers/IntegrationController.php`

Applied the same enhanced deletion logic to the Shopify uninstall webhook handler.

## Data Models & Relationships

### Organization Model Now Has:
- ✅ `organizationData()` - hasMany OrganizationData
- ✅ `users()` - belongsToMany User
- ✅ `dataSources()` - hasMany DataSource
- ✅ `subscriptions()` - hasMany Subscription
- ✅ `tokenUsageLogs()` - hasMany TokenUsageLog
- ✅ `chatSessions()` - hasMany ChatSession (NEW)
- ✅ `chatConversations()` - hasMany ChatConversation (NEW)
- ✅ `integrations()` - hasMany Integration (NEW)
- ✅ `customerReviews()` - hasMany CustomerReview

### Deletion Order (Cascading)
1. **Qdrant Collection** - Vector database cleanup
2. **Users** - Delete or detach based on organization count
3. **Organization Data** - FAQs, services, info
4. **Chat Sessions** - Delete sessions first
   - Then delete associated ChatMessages
5. **Chat Conversations** - Delete conversations first
   - Then delete associated ChatMessages
6. **Token Usage Logs** - Usage tracking data
7. **Integrations** - Shopify/WordPress connections
8. **Organization** - Finally delete the organization itself

## Why the Cascading Delete is Important

### Without Proper Cascade:
- Chat messages would become orphaned (no parent session/conversation)
- Foreign key constraints could fail
- Database inconsistency

### With Proper Cascade:
- ✅ All child records deleted before parent
- ✅ No orphaned records
- ✅ Database integrity maintained
- ✅ No foreign key violations

## Testing Checklist

- [ ] Delete organization with chat sessions
- [ ] Delete organization with chat conversations
- [ ] Delete organization with integrations
- [ ] Verify all chat messages are deleted
- [ ] Verify Qdrant collection is deleted
- [ ] Verify users are handled correctly
- [ ] Check database for orphaned records
- [ ] Test Shopify uninstall webhook

## Database Tables Affected

### Direct Deletions:
1. `organization_data` - Organization content
2. `chat_sessions` - Chat session records
3. `chat_messages` - Messages from sessions
4. `chat_conversations` - Conversation records  
5. `chat_messages` - Messages from conversations
6. `token_usage_logs` - Usage tracking
7. `integrations` - Integration records
8. `organizations` - Organization record

### Relationship Updates:
- `organization_user` - Pivot table (detach)
- `users` - May be deleted if single-org user

## Error Prevention

### Before Fix:
```
BadMethodCallException
Call to undefined method App\Models\Organization::chatSessions()
```

### After Fix:
✅ All relationships defined
✅ Proper cascading delete
✅ No orphaned records
✅ Database integrity maintained

## Performance Considerations

### Potential Improvement for Large Datasets:
For organizations with thousands of chat messages, consider using database-level cascading deletes instead of PHP loops:

```php
// Alternative approach for very large datasets
DB::table('chat_messages')
    ->whereIn('session_id', $org->chatSessions()->pluck('session_id'))
    ->delete();
    
DB::table('chat_messages')
    ->whereIn('conversation_id', $org->chatConversations()->pluck('id'))
    ->delete();
```

Current implementation is fine for typical usage (hundreds to thousands of messages).

## Files Modified

1. ✅ `laravel/app/Models/Organization.php` - Added 3 relationships
2. ✅ `laravel/app/Livewire/OrganizationManager.php` - Enhanced deletion
3. ✅ `laravel/app/Http/Controllers/IntegrationController.php` - Enhanced deletion

## Compatibility

- ✅ No breaking changes
- ✅ No database migrations required
- ✅ Works with existing data
- ✅ Backward compatible

## Future Enhancements

### Option 1: Database Foreign Key Cascades
Add cascade deletes at database level in migrations:
```php
$table->foreignId('organization_id')
    ->constrained()
    ->onDelete('cascade');
```

### Option 2: Model Events
Use Eloquent model events for automatic cleanup:
```php
protected static function booted()
{
    static::deleting(function ($organization) {
        // Automatic cleanup on delete
    });
}
```

### Option 3: Soft Deletes
Implement soft deletes for recovery:
```php
use SoftDeletes;
```

---

**Date**: October 7, 2025  
**Status**: ✅ Fixed and Ready for Testing  
**Breaking Changes**: None  
**Database Changes**: None required
