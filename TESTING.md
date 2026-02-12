# Testing Guide for MultiChat GPT Plugin

This document provides comprehensive testing procedures for all the features and improvements made to the MultiChat GPT plugin.

## Prerequisites

- WordPress installation (5.6+)
- PHP 7.4+
- OpenAI API key
- Browser developer tools enabled
- WP_DEBUG enabled for logging tests

## 1. Installation Testing

### Test 1.1: Plugin Activation
1. Upload plugin to `/wp-content/plugins/`
2. Navigate to Plugins page
3. Click "Activate" on MultiChat GPT
4. **Expected**: Plugin activates without errors
5. **Expected**: Default options created (api_key='', position='bottom-right')

### Test 1.2: Database Options
```sql
SELECT * FROM wp_options WHERE option_name LIKE 'multichat_gpt%';
```
**Expected**: `multichat_gpt_api_key` and `multichat_gpt_widget_position` entries exist

## 2. Security Testing

### Test 2.1: Rate Limiting
**Objective**: Verify IP-based rate limiting works

1. Open browser console
2. Run this script to send 15 rapid requests:
```javascript
for (let i = 0; i < 15; i++) {
    fetch('/wp-json/multichat/v1/ask', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: 'test ' + i, language: 'en' })
    }).then(r => r.json()).then(console.log);
}
```
**Expected**: First 10 succeed, remaining 5 return 429 (rate limit exceeded)

### Test 2.2: Input Validation - Empty Message
```javascript
fetch('/wp-json/multichat/v1/ask', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: '', language: 'en' })
}).then(r => r.json()).then(console.log);
```
**Expected**: 400 error "Message must be a non-empty string"

### Test 2.3: Input Validation - Message Too Long
```javascript
fetch('/wp-json/multichat/v1/ask', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'a'.repeat(2001), language: 'en' })
}).then(r => r.json()).then(console.log);
```
**Expected**: 400 error "Message must not exceed 2000 characters"

### Test 2.4: Input Validation - Invalid Language
```javascript
fetch('/wp-json/multichat/v1/ask', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'test', language: 'xx' })
}).then(r => r.json()).then(console.log);
```
**Expected**: 400 error "Language must be one of: en, ar, es, fr"

### Test 2.5: API Key Validation
1. Go to Settings → MultiChat GPT
2. Enter invalid API key: "invalid-key"
3. Save settings
4. Send a chat message
**Expected**: API request fails with "Invalid API key" error

### Test 2.6: XSS Prevention
```javascript
// Try to inject script via message
fetch('/wp-json/multichat/v1/ask', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
        message: '<script>alert("XSS")</script>', 
        language: 'en' 
    })
}).then(r => r.json()).then(console.log);
```
**Expected**: Message is sanitized, no script execution in UI

## 3. Performance Testing

### Test 3.1: API Response Caching
1. Send a message: "What are your business hours?"
2. Note response time (should be ~1-3 seconds)
3. Send same message again
4. **Expected**: Second request returns in <100ms (from cache)

### Test 3.2: Cache Verification
```php
// Check if transient exists
$key = 'multichat_gpt_' . md5($system_message . '|' . $user_message);
$cached = get_transient($key);
var_dump($cached); // Should return cached response
```

### Test 3.3: Knowledge Base Caching
```php
// First load
$kb = new MultiChat_GPT_Knowledge_Base($logger);
$chunks = $kb->get_chunks('en'); // Loads from code

// Second load (should hit cache)
$chunks2 = $kb->get_chunks('en'); // Loads from transient
```

### Test 3.4: Frontend Performance
1. Open Chrome DevTools → Performance
2. Start recording
3. Click chat button to open widget
4. Stop recording
5. **Expected**: 
   - Layout shift < 0.1
   - First paint < 100ms
   - Smooth 60fps animations

### Test 3.5: Event Delegation
1. Open widget
2. Check event listeners in DevTools
3. **Expected**: Only 2 listeners on container (click, keypress)
4. **Not expected**: Individual listeners on buttons

## 4. Functionality Testing

### Test 4.1: Widget Appearance
1. Load any frontend page
2. **Expected**: Chat button appears in bottom-right corner
3. **Expected**: CSS loaded correctly with proper styling

### Test 4.2: Widget Positioning
1. Go to Settings → MultiChat GPT
2. Change position to "Bottom Left"
3. Save and reload frontend
4. **Expected**: Widget moves to bottom-left

### Test 4.3: Chat Interaction
1. Click chat button
2. **Expected**: Window opens smoothly
3. Type a message and send
4. **Expected**: 
   - Loading state shows
   - Response appears
   - Scroll to bottom
   - Message history saved

### Test 4.4: Language Detection (WPML)
If WPML installed:
1. Switch to Arabic language
2. Open chat widget
3. **Expected**: Chat title and placeholders in Arabic
4. Send message
5. **Expected**: Response in Arabic

### Test 4.5: Multilingual Knowledge Base
```javascript
// Test Arabic
fetch('/wp-json/multichat/v1/ask', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
        message: 'ما هي ساعات العمل', 
        language: 'ar' 
    })
}).then(r => r.json()).then(console.log);
```
**Expected**: Response uses Arabic knowledge base

## 5. Admin Interface Testing

### Test 5.1: Settings Page Access
1. Go to Settings → MultiChat GPT
2. **Expected**: Page loads without errors
3. **Expected**: Two sections: Configuration and Cache Management

### Test 5.2: API Key Storage
1. Enter API key in settings
2. Save
3. Check database:
```sql
SELECT option_value FROM wp_options WHERE option_name = 'multichat_gpt_api_key';
```
**Expected**: API key stored correctly

### Test 5.3: Cache Clearing
1. Send some chat messages to populate cache
2. Go to Settings → MultiChat GPT
3. Click "Clear All Caches"
4. **Expected**: Success message appears
5. Verify transients deleted:
```sql
SELECT COUNT(*) FROM wp_options 
WHERE option_name LIKE '_transient_multichat_gpt_%';
```
**Expected**: Count = 0

## 6. Error Handling Testing

### Test 6.1: Missing API Key
1. Clear API key from settings
2. Send chat message
3. **Expected**: User-friendly error "API key not configured"

### Test 6.2: API Timeout
Modify timeout in `class-api-handler.php`:
```php
'timeout' => 1, // Very short timeout
```
**Expected**: Retry logic activates, then error message

### Test 6.3: Invalid API Response
**Expected**: Graceful error handling with user-friendly message

### Test 6.4: Network Error
1. Disconnect from internet
2. Send message
3. **Expected**: Error message shown to user

## 7. Logging Testing

### Test 7.1: Enable Debug Mode
In `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Test 7.2: Check Error Logs
1. Trigger an error (e.g., invalid API key)
2. Check `wp-content/debug.log`
3. **Expected**: Error logged with context

### Test 7.3: View Logs Programmatically
```php
$logger = new MultiChat_GPT_Logger();
$logs = $logger->get_logs(50, 'error');
print_r($logs);
```
**Expected**: Array of recent error log entries

## 8. Code Quality Testing

### Test 8.1: WordPress Coding Standards
```bash
# If PHPCS installed
phpcs --standard=WordPress includes/
```
**Expected**: No major violations

### Test 8.2: Security Scan
```bash
# CodeQL scan (already run)
```
**Expected**: 0 vulnerabilities (✓ Verified)

### Test 8.3: JavaScript Linting
```bash
# If ESLint configured
eslint assets/js/widget.js
```
**Expected**: No errors

## 9. Browser Compatibility Testing

Test in:
- ✓ Chrome (latest)
- ✓ Firefox (latest)
- ✓ Safari (latest)
- ✓ Edge (latest)
- ✓ Mobile Safari (iOS)
- ✓ Chrome Mobile (Android)

**Expected**: Consistent behavior across all browsers

## 10. Load Testing

### Test 10.1: Concurrent Requests
Use tool like Apache Bench:
```bash
ab -n 100 -c 10 -p post.json -T application/json \
   http://your-site.com/wp-json/multichat/v1/ask
```
**Expected**: 
- Rate limiting works correctly
- No server crashes
- Proper error responses

### Test 10.2: Cache Performance Under Load
1. Send 100 identical requests
2. **Expected**: 
   - First request: ~2s
   - Subsequent 99: <100ms each

## Summary Checklist

- [ ] Installation works correctly
- [ ] Rate limiting prevents abuse
- [ ] Input validation works
- [ ] XSS prevention effective
- [ ] API caching works
- [ ] Knowledge base caching works
- [ ] Frontend performance optimized
- [ ] Event delegation implemented
- [ ] Widget appears and functions
- [ ] Multilingual support works
- [ ] Admin settings functional
- [ ] Cache clearing works
- [ ] Error handling graceful
- [ ] Logging system works
- [ ] Code quality verified
- [ ] Security scan passed (✓)
- [ ] Browser compatibility verified
- [ ] Load testing completed

## Known Limitations

1. Sleep() used in retry logic (blocking) - Intentional for API scenarios
2. Rate limiting per IP - May affect users behind NAT (configurable via filter)
3. Maximum 2000 character message length

## Recommendations

1. Monitor API usage in OpenAI dashboard
2. Adjust cache TTL based on content freshness needs
3. Configure rate limits based on expected traffic
4. Enable object cache (Redis/Memcached) for better performance
5. Regularly clean up old logs and transients
