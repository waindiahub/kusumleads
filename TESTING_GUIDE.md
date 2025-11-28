# WhatsApp Chat UI/UX Testing Guide

## 🧪 Test Cases

### Test Case 1: Search Functionality
**Objective:** Verify search works correctly

**Steps:**
1. Navigate to WhatsApp Conversations page
2. Type a contact name in the search box
3. Verify conversations filter in real-time
4. Click the clear (X) button
5. Verify all conversations reappear

**Expected Results:**
- ✅ Search filters conversations instantly
- ✅ Clear button appears when typing
- ✅ Clear button resets the search
- ✅ "No results found" shown when no matches

---

### Test Case 2: Tab Navigation with Counters
**Objective:** Verify tab switching and counters

**Steps:**
1. Observe the three tabs (Active, Requesting, Intervened)
2. Note the counter badges on each tab
3. Click each tab
4. Verify conversations filter correctly

**Expected Results:**
- ✅ Counters show correct numbers
- ✅ "Requesting" tab has yellow badge
- ✅ Active tab is highlighted
- ✅ Conversations filter per tab

---

### Test Case 3: Conversation Selection
**Objective:** Verify conversation loads correctly

**Steps:**
1. Click on a conversation in the list
2. Verify conversation highlights
3. Check messages load in center panel
4. Verify profile loads in right panel

**Expected Results:**
- ✅ Selected conversation has teal accent
- ✅ Messages display with proper formatting
- ✅ Profile shows correct details
- ✅ Timestamps are formatted correctly

---

### Test Case 4: Message Formatting
**Objective:** Verify message formatting works

**Steps:**
1. Select a conversation
2. Type: `*bold text* _italic text_ ~strikethrough~`
3. Include a URL: `https://example.com`
4. Send the message

**Expected Results:**
- ✅ Bold text appears bold
- ✅ Italic text appears italic
- ✅ Strikethrough works
- ✅ URLs are clickable

---

### Test Case 5: Send Button States
**Objective:** Verify send button behavior

**Steps:**
1. Click in message input (empty)
2. Observe send button is disabled
3. Type some text
4. Observe send button enables
5. Click send button
6. Verify message sends and input clears

**Expected Results:**
- ✅ Send button disabled when empty
- ✅ Send button enabled when text present
- ✅ Button scales on hover
- ✅ Input clears after sending

---

### Test Case 6: Keyboard Shortcuts
**Objective:** Verify keyboard controls work

**Steps:**
1. Type a message
2. Press Enter (without Shift)
3. Verify message sends
4. Type multi-line message
5. Press Shift+Enter
6. Verify new line is added

**Expected Results:**
- ✅ Enter sends message
- ✅ Shift+Enter adds new line
- ✅ Focus remains in input

---

### Test Case 7: Intervene/Resolve Actions
**Objective:** Verify intervention workflow

**Steps:**
1. Select an active conversation
2. Click "Intervene" button
3. Verify status changes to "Intervened"
4. Verify tab moves to "Intervened"
5. Click "Resolve" button
6. Verify returns to "Active" tab

**Expected Results:**
- ✅ Intervene button works
- ✅ Status badge updates
- ✅ Conversation moves to correct tab
- ✅ Resolve button works
- ✅ Tab counters update

---

### Test Case 8: Tag Management
**Objective:** Verify tag functionality

**Steps:**
1. Select a conversation
2. In profile panel, open "Tags" accordion
3. Select a tag from dropdown
4. Click "Add" button
5. Verify tag appears
6. Click X on tag to remove
7. Verify tag is removed

**Expected Results:**
- ✅ Tag dropdown works
- ✅ Tags are added
- ✅ Tags display with styling
- ✅ Tags can be removed
- ✅ Journey updates with tag events

---

### Test Case 9: Attributes Editing
**Objective:** Verify attribute editing works

**Steps:**
1. Select a conversation
2. In profile panel, open "Attributes" accordion
3. Click "Edit" button
4. Fill in contact name, email, city
5. Click "Save"
6. Verify attributes update

**Expected Results:**
- ✅ Edit form appears
- ✅ Fields are editable
- ✅ Save updates attributes
- ✅ Display updates with new values

---

### Test Case 10: Responsive Design - Mobile
**Objective:** Verify mobile layout works

**Steps:**
1. Resize browser to 768px width or less
2. Verify layout switches to mobile view
3. Test all functionality on mobile

**Expected Results:**
- ✅ Single column layout
- ✅ Touch targets are adequate (44px+)
- ✅ All features remain accessible
- ✅ Text is readable
- ✅ Buttons are clickable

---

### Test Case 11: Dark Mode
**Objective:** Verify dark mode works

**Steps:**
1. Click "Theme" toggle in navbar
2. Verify entire UI switches to dark mode
3. Check all panels and components
4. Toggle back to light mode

**Expected Results:**
- ✅ Dark theme applies everywhere
- ✅ Text remains readable
- ✅ Proper contrast maintained
- ✅ Theme persists on reload

---

### Test Case 12: Loading States
**Objective:** Verify loading indicators work

**Steps:**
1. Refresh the page
2. Observe skeleton loaders in conversation list
3. Wait for conversations to load
4. Verify smooth transition

**Expected Results:**
- ✅ Skeleton loaders appear
- ✅ Shimmer animation plays
- ✅ Smooth transition to actual content
- ✅ No layout shift

---

### Test Case 13: Empty States
**Objective:** Verify empty states display correctly

**Steps:**
1. Switch to "Requesting" tab (if empty)
2. Verify empty state shows correct icon/message
3. Search for non-existent contact
4. Verify "No results" empty state
5. Open conversation with no messages
6. Verify "No messages" empty state

**Expected Results:**
- ✅ Contextual icons display
- ✅ Helpful messages show
- ✅ Proper styling applied
- ✅ Good visual hierarchy

---

### Test Case 14: Time Formatting
**Objective:** Verify time displays correctly

**Steps:**
1. View conversation list
2. Check time stamps on conversations
3. Open a conversation
4. Check message timestamps
5. Verify date separators

**Expected Results:**
- ✅ Relative time for recent (2m, 5h, 3d)
- ✅ "Just now" for <1 minute
- ✅ Full dates for old messages
- ✅ Date separators show (Today, Yesterday, etc.)
- ✅ 12-hour format in messages

---

### Test Case 15: Status Indicators
**Objective:** Verify status dots and badges work

**Steps:**
1. Find requesting conversation
2. Verify yellow pulsing dot
3. Check status badge in header
4. Observe message status icons

**Expected Results:**
- ✅ Pulsing dots animate smoothly
- ✅ Status badges show correct color
- ✅ Message status shows (✓, ✓✓)
- ✅ Read receipts work (blue ✓✓)

---

## 🎯 Browser Compatibility Testing

### Chrome/Edge (Chromium)
- ✅ Test all features
- ✅ Check animations
- ✅ Verify performance

### Firefox
- ✅ Test all features
- ✅ Check CSS compatibility
- ✅ Verify scrolling

### Safari
- ✅ Test all features
- ✅ Check iOS Safari
- ✅ Verify touch interactions

---

## 📱 Device Testing

### Desktop (1920x1080)
- ✅ Three-panel layout
- ✅ All features visible
- ✅ Proper spacing

### Laptop (1366x768)
- ✅ Adjusted panel widths
- ✅ Content fits properly
- ✅ No overflow issues

### Tablet (768x1024)
- ✅ Responsive layout
- ✅ Touch-friendly
- ✅ Proper scaling

### Mobile (375x667)
- ✅ Single column
- ✅ Large touch targets
- ✅ Readable text
- ✅ No horizontal scroll

---

## ⚡ Performance Testing

### Load Time
- ✅ Page loads within 2 seconds
- ✅ Skeleton shows immediately
- ✅ No blocking resources

### Animation Performance
- ✅ 60fps animations
- ✅ No jank or stutter
- ✅ Smooth transitions

### Memory Usage
- ✅ No memory leaks
- ✅ Efficient DOM updates
- ✅ Proper cleanup

---

## ♿ Accessibility Testing

### Keyboard Navigation
- ✅ Tab through all elements
- ✅ Enter activates buttons
- ✅ Escape closes modals
- ✅ Focus visible

### Screen Reader
- ✅ Proper labels
- ✅ ARIA attributes
- ✅ Semantic HTML
- ✅ Alt text on icons

### Color Contrast
- ✅ WCAG AA compliance
- ✅ Text readable
- ✅ Interactive elements clear
- ✅ Focus indicators visible

---

## 🐛 Known Issues / Edge Cases

### Issue 1: Auto-play Notification Sound
**Description:** Browsers may block notification sound
**Workaround:** User must interact with page first
**Status:** Expected behavior

### Issue 2: Very Long Messages
**Description:** Extremely long messages without spaces may overflow
**Workaround:** CSS word-break applied
**Status:** Fixed

### Issue 3: Rapid Tab Switching
**Description:** Very fast tab switching may cause brief flicker
**Workaround:** Debounce implemented
**Status:** Minor, acceptable

---

## 🔄 Regression Testing Checklist

After any code changes, verify:

- [ ] Search still works
- [ ] Tab switching works
- [ ] Messages display correctly
- [ ] Send button states work
- [ ] Tags can be added/removed
- [ ] Attributes can be edited
- [ ] Intervene/Resolve works
- [ ] Responsive design intact
- [ ] Dark mode works
- [ ] No console errors
- [ ] No visual glitches
- [ ] Performance acceptable

---

## 📊 Test Results Template

```markdown
## Test Session: [Date]
**Tester:** [Name]
**Browser:** [Browser & Version]
**OS:** [Operating System]
**Device:** [Device Type]

### Results:
| Test Case | Status | Notes |
|-----------|--------|-------|
| Search    | ✅ Pass | Works perfectly |
| Tabs      | ✅ Pass | Counters accurate |
| Messages  | ✅ Pass | Formatting good |
| ...       | ...    | ... |

### Issues Found:
1. [Issue description]
2. [Issue description]

### Overall Score: [X/15 test cases passed]
```

---

## 🚀 Quick Smoke Test (5 minutes)

For rapid verification after deployment:

1. ✅ Load page - shows skeleton loaders
2. ✅ Search for a contact
3. ✅ Switch tabs - counters update
4. ✅ Click conversation - messages load
5. ✅ Type and send message
6. ✅ Toggle dark mode
7. ✅ Resize to mobile view
8. ✅ Check for console errors

If all 8 steps pass → ✅ **Ready for production**

---

## 📝 Bug Report Template

```markdown
**Title:** [Brief description]

**Severity:** [Critical / High / Medium / Low]

**Steps to Reproduce:**
1. Step 1
2. Step 2
3. Step 3

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happens]

**Screenshots:**
[Attach if applicable]

**Environment:**
- Browser: [Browser & version]
- OS: [Operating system]
- Device: [Desktop/Mobile/Tablet]
- Screen Resolution: [e.g., 1920x1080]

**Additional Notes:**
[Any other relevant information]
```

---

*Testing Guide Version: 1.0*
*Last Updated: November 27, 2025*
*Total Test Cases: 15*
*Estimated Testing Time: 45-60 minutes (full suite)*

