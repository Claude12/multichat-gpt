# Testing Guide - MultiChat GPT v1.1.0

## Pre-Installation Testing

### 1. File Integrity Check
```bash
# Verify all files are present
ls -la multichat-gpt.php
ls -la includes/
ls -la assets/js/widget.js
ls -la assets/css/widget.css

# Check PHP syntax
php -l multichat-gpt.php
find includes/ -name "*.php" -exec php -l {} \;

# Check JavaScript syntax
node -c assets/js/widget.js
```

## Installation Testing

### 1. Fresh Installation
1. Upload plugin to `/wp-content/plugins/multichat-gpt/`
2. Navigate to **Plugins** in WordPress admin
3. Click **Activate** on MultiChat GPT
4. Verify activation message appears
5. Check that **Settings > MultiChat GPT** menu item appears

**Expected Result:**
- ✅ Plugin activates without errors
- ✅ Admin menu item appears under Settings
- ✅ Default options created in database

### 2. Settings Configuration
1. Navigate to **Settings > MultiChat GPT**
2. Enter a test API key: `sk-test1234567890abcdef`
3. Select widget position: **Bottom Right**
4. Click **Save Changes**

**Expected Result:**
- ✅ Settings save successfully
- ✅ Success message appears
- ✅ Values persist after page refresh

### 3. API Key Validation Testing
1. Try saving invalid API key: `invalid-key`
2. Expected: Error message "Invalid API key format. API key should start with 'sk-'"
3. Try saving valid format: `sk-proj-1234567890abcdef`
4. Expected: Saves successfully

## Functionality Testing

### 1. Frontend Widget Display
1. Visit any frontend page
2. Look for chat widget button in bottom-right corner
3. Click the chat button to open

**Expected Result:**
- ✅ Chat toggle button appears
- ✅ Widget opens smoothly with animation
- ✅ Chat window displays correctly
- ✅ Input field and send button are visible
- ✅ Close button (×) is visible in header

### 2. Chat Functionality
**Setup:** Use a valid OpenAI API key

1. Type "What are your business hours?" in the input
2. Click **Send** or press **Enter**
3. Observe the behavior

**Expected Result:**
- ✅ User message appears immediately
- ✅ Typing indicator appears (animated dots)
- ✅ Send button shows "Sending..." with spinner
- ✅ Input is disabled during request
- ✅ Assistant response appears after API call
- ✅ Typing indicator disappears
- ✅ Send button returns to "Send"
- ✅ Chat scrolls to show new message

### 3. Error Handling
**Test 1: No API Key**
1. Clear API key from settings
2. Try sending a message
3. Expected: Error message "API key not configured"

**Test 2: Invalid API Key**
1. Set API key to `sk-invalid`
2. Try sending a message
3. Expected: Error message from OpenAI API

**Test 3: Empty Message**
1. Leave input field empty
2. Click send
3. Expected: Nothing happens (button disabled or validation prevents)

**Test 4: Long Message**
1. Type a message longer than 1000 characters
2. Send the message
3. Expected: Error "Message is too long (max 1000 characters)"

### 4. Rate Limiting
**Setup:** Use a valid API key

1. Send 10 messages rapidly (within 1 minute)
2. Send the 11th message
3. Expected: Error "Rate limit exceeded. Please try again later." (HTTP 429)
4. Wait 1 minute
5. Send another message
6. Expected: Message sent successfully

### 5. Caching
**Setup:** Use a valid API key

1. Send message: "What are your business hours?"
2. Note the response time (should be ~2-5 seconds)
3. Send the exact same message again
4. Note the response time (should be instant, <100ms)
5. Check browser console for "Cache hit" debug message (if WP_DEBUG enabled)

**Expected Result:**
- ✅ First request hits API (slower)
- ✅ Second request uses cache (instant)
- ✅ Same response returned both times

### 6. Multi-Language Support
1. Install WPML or Polylang
2. Switch site language to Spanish (es)
3. Reload frontend page
4. Open chat widget

**Expected Result:**
- ✅ Chat UI displays in Spanish
- ✅ Placeholder text: "Pregúntame lo que sea..."
- ✅ Send button: "Enviar"
- ✅ API receives language parameter: "es"
- ✅ Assistant responds in Spanish

### 7. Widget Position
1. Go to **Settings > MultiChat GPT**
2. Change position to **Bottom Left**
3. Save settings
4. Reload frontend page

**Expected Result:**
- ✅ Widget appears in bottom-left corner
- ✅ Position persists across pages

## Admin Panel Testing

### 1. Cache Management
1. Navigate to **Settings > MultiChat GPT**
2. Scroll to "Cache Management" section
3. Click **Clear All Caches**

**Expected Result:**
- ✅ Success message appears
- ✅ All transients with prefix `multichat_gpt_*` are deleted
- ✅ Next chat request is slower (cache miss)

### 2. Settings Validation
**Test Invalid Position:**
1. Inspect element on position dropdown
2. Manually add option: `<option value="invalid">Invalid</option>`
3. Select and save
4. Expected: Reverts to "bottom-right" with no error or shows validation error

## Performance Testing

### 1. Frontend Performance
**Metrics to Check:**
1. Page load time (should not increase significantly)
2. Time to Interactive (widget loads asynchronously)
3. Widget script loading (should have `defer` attribute)
4. Console errors (should be none)

**Tools:**
- Chrome DevTools > Lighthouse
- Chrome DevTools > Performance tab
- GTmetrix or WebPageTest

**Expected Results:**
- ✅ Widget script has `defer` attribute
- ✅ No render-blocking resources
- ✅ No JavaScript errors in console
- ✅ Widget loads without blocking page

### 2. API Response Times
**Setup:** Enable WP_DEBUG and WP_DEBUG_LOG

1. Send first message (cache miss)
   - Expected: 2-5 seconds (OpenAI API call)
2. Send same message (cache hit)
   - Expected: <100ms (transient retrieval)
3. Check debug.log for cache messages

### 3. Database Query Count
**Setup:** Install Query Monitor plugin

1. Load frontend page with widget
2. Check query count in Query Monitor
3. Open/close widget multiple times
4. Verify no additional queries per interaction

**Expected Result:**
- ✅ Widget loads without additional database queries
- ✅ Cache retrieval uses transients (1-2 queries max)

## Security Testing

### 1. SQL Injection
**Test REST API:**
```bash
curl -X POST https://yoursite.com/wp-json/multichat/v1/ask \
  -H "Content-Type: application/json" \
  -d '{"message": "test\"; DROP TABLE wp_posts;--", "language": "en"}'
```

**Expected Result:**
- ✅ Message is sanitized
- ✅ No SQL injection occurs
- ✅ Tables remain intact

### 2. XSS (Cross-Site Scripting)
1. Send message: `<script>alert('XSS')</script>`
2. Check frontend chat display

**Expected Result:**
- ✅ Script tags are escaped
- ✅ No alert popup
- ✅ Message displays as text: `<script>alert('XSS')</script>`

### 3. Rate Limit Bypass Attempt
**Test IP Spoofing:**
```bash
# Try with different X-Forwarded-For headers
for i in {1..15}; do
  curl -X POST https://yoursite.com/wp-json/multichat/v1/ask \
    -H "X-Forwarded-For: 1.2.3.$i" \
    -H "Content-Type: application/json" \
    -d '{"message": "test", "language": "en"}'
done
```

**Expected Result:**
- ✅ Each unique IP is rate-limited separately
- ✅ Same IP cannot bypass rate limit

### 4. API Key Exposure
1. View page source
2. Check JavaScript localized data
3. Search for "sk-"

**Expected Result:**
- ✅ API key is NOT exposed in HTML
- ✅ API key is NOT in JavaScript
- ✅ Only REST URL is visible

## Mobile Testing

### 1. Responsive Design
**Test on:**
- iPhone SE (375px)
- iPhone 12 Pro (390px)
- iPad (768px)
- Desktop (1920px)

**Verify:**
1. Widget button is visible
2. Chat window fits screen
3. Messages are readable
4. Input field is accessible
5. Keyboard doesn't cover input (iOS)

**Expected Result:**
- ✅ Widget adapts to screen size
- ✅ Chat window max height: 60vh on mobile
- ✅ No horizontal scroll
- ✅ Touch targets are >44x44px

### 2. Touch Events
1. Tap widget button to open
2. Tap input field (keyboard should appear)
3. Type message
4. Tap send button
5. Scroll through messages

**Expected Result:**
- ✅ All interactions work smoothly
- ✅ No double-tap delay
- ✅ Scrolling is smooth

## Browser Compatibility

### Test Browsers:
- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Safari iOS (latest)
- ✅ Chrome Android (latest)

### Check:
1. Widget displays correctly
2. Animations work
3. Chat functionality works
4. No console errors

## Accessibility Testing

### 1. Keyboard Navigation
1. Tab through page elements
2. Tab to widget button
3. Press Enter to open
4. Tab to input field
5. Type and press Enter to send
6. Tab to close button, press Enter

**Expected Result:**
- ✅ All elements are keyboard accessible
- ✅ Focus indicators are visible
- ✅ Enter key works for send and open/close

### 2. Screen Reader Testing
**Test with:**
- NVDA (Windows)
- JAWS (Windows)
- VoiceOver (Mac/iOS)

**Verify:**
1. Widget button has aria-label
2. Chat messages are announced
3. Input field has label
4. Send button has aria-label

### 3. Color Contrast
**Tools:** WAVE, axe DevTools

**Check:**
- Text colors have sufficient contrast (4.5:1 minimum)
- Dark mode colors are accessible

## Load Testing

### 1. Concurrent Users
**Setup:** Use Apache Bench or similar

```bash
# Simulate 50 concurrent requests
ab -n 50 -c 10 -p message.json -T application/json \
  https://yoursite.com/wp-json/multichat/v1/ask
```

**Expected Result:**
- ✅ Rate limiting prevents abuse
- ✅ Server doesn't crash
- ✅ Response times remain reasonable

### 2. Cache Performance
1. Clear all caches
2. Send 100 identical messages
3. Check cache hit rate

**Expected Result:**
- ✅ First request: cache miss
- ✅ Requests 2-100: cache hit
- ✅ 99% cache hit rate

## Regression Testing

### After Updates
1. Re-run all tests above
2. Verify no existing functionality broke
3. Check for new console errors
4. Verify settings persist
5. Test deactivation/reactivation

## Automated Testing

### PHP Unit Tests (Future)
```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Run tests
./vendor/bin/phpunit tests/
```

### JavaScript Tests (Future)
```bash
# Install Jest
npm install --save-dev jest

# Run tests
npm test
```

## Test Checklist Summary

### Installation ✅
- [ ] Fresh install works
- [ ] Settings page appears
- [ ] Default settings created

### Functionality ✅
- [ ] Widget displays on frontend
- [ ] Chat opens/closes smoothly
- [ ] Messages send successfully
- [ ] Responses display correctly
- [ ] Error handling works

### Security ✅
- [ ] API key validation works
- [ ] Rate limiting prevents abuse
- [ ] Input sanitization prevents XSS
- [ ] SQL injection prevented
- [ ] API key not exposed

### Performance ✅
- [ ] Caching reduces API calls
- [ ] Widget loads asynchronously
- [ ] No render-blocking resources
- [ ] Rate limiting works efficiently

### Compatibility ✅
- [ ] Works in all major browsers
- [ ] Responsive on mobile
- [ ] Touch events work
- [ ] Keyboard accessible
- [ ] Screen reader compatible

## Bug Reporting

When reporting bugs, include:
1. WordPress version
2. PHP version
3. Browser and version
4. Steps to reproduce
5. Expected vs. actual behavior
6. Screenshots/console errors
7. WP_DEBUG log output

---

**Last Updated:** 2026-02-12  
**Version:** 1.1.0  
**Tested By:** Development Team
