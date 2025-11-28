# WhatsApp Chat UI Testing Guide

## Quick Test Steps

### 1. **Access the Chat Interface**
Navigate to: `php/admin/whatsapp_conversations.php`

You should see:
- ✅ Teal sidebar on the left (modern AiSensy style)
- ✅ Three-column layout: Conversations | Chat Messages | Profile
- ✅ "Live Chat Intervention" title with user avatars
- ✅ Three tabs: Active, Requesting, Intervened

### 2. **Check the Layout**
The page should have:
- **Left Panel (320px)**: Conversation list with search and filters
- **Center Panel**: Chat messages area with header and input
- **Right Panel (360px)**: Contact profile with accordions

### 3. **Test Conversations List**
- Click on different tabs (Active, Requesting, Intervened)
- Conversations should filter accordingly
- Each conversation item should show:
  - Avatar with initial
  - Contact name/phone
  - Last message preview
  - Unread count (if any)

### 4. **Test Chat Messages**
- Click on a conversation
- Messages should load in the center panel
- Should see:
  - Message bubbles (incoming on left, outgoing on right)
  - Avatars for each message
  - Timestamps
  - Proper colors (white for incoming, green for outgoing)

### 5. **Test Chat Profile (Right Panel)**
Click on each accordion section:

#### **Tags Section** (should be open by default)
- View existing tags as colored pills
- Click X on a tag to remove it
- Use dropdown to add existing tags
- Click "Create & Add Tag" to create new tags

#### **Customer Journey**
- Should show timeline with icons
- Connecting lines between events
- Event details (type, user, time)

#### **Attributes**
- Shows contact details
- Click "Edit" button to modify
- Save changes

### 6. **Test Interactions**

#### **Sending Messages**
- Type in the input box at bottom
- Click Send button or press Enter
- Message should appear in chat

#### **Intervene/Resolve**
- Click "Intervene" button in header
- Status should change to "Intervened"
- "Resolve" button should appear
- Click "Resolve" to restore

#### **Format Toolbar**
- Icons should appear above input box
- Bold, Italic, Strikethrough, Emoji, Attach, Image buttons

### 7. **Test Responsive Design**

#### **Desktop (1920px+)**
- All three panels visible
- Wider columns for better space

#### **Laptop (1200px - 1600px)**
- Three panels with adjusted widths
- Everything functional

#### **Tablet/Mobile (< 992px)**
- Only center panel visible
- Sidebar hidden (toggleable)
- Touch-friendly interactions

### 8. **Test Dark Mode**
- Click "Theme" button in navbar
- All colors should adjust
- Teal sidebar becomes darker
- Chat background changes
- Messages maintain contrast

## Common Issues & Fixes

### Issue 1: Layout Broken / Panels Not Showing
**Symptom**: Panels overlapping or missing
**Fix**: 
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh (Ctrl+F5)
3. Check console for CSS errors

### Issue 2: Sidebar Not Styled
**Symptom**: Sidebar is light gray instead of teal
**Fix**: 
- Make sure `css/admin.css` is loaded
- Check that `.sidebar` class has `background: #0e7c7b !important`

### Issue 3: Chat Container Too Narrow
**Symptom**: Three columns are cramped
**Fix**:
- Adjust CSS in `chats.css`
- Change `grid-template-columns` values
- Recommended: `320px 1fr 360px`

### Issue 4: JavaScript Not Working
**Symptom**: Buttons don't respond, accordions don't toggle
**Fix**:
1. Open browser console (F12)
2. Look for JavaScript errors
3. Verify jQuery/Bootstrap JS is loaded
4. Check `whatsapp_api.php` is accessible

### Issue 5: Tags Not Showing/Adding
**Symptom**: Tag operations fail
**Fix**:
1. Check database table `whatsapp_conversation_tags` exists
2. Verify API endpoint: `whatsapp_api.php?action=get_tags`
3. Check browser console for errors

### Issue 6: Messages Not Loading
**Symptom**: Empty chat area or errors
**Fix**:
1. Verify `whatsapp_api.php?action=list_conversations` works
2. Check `whatsapp_api.php?action=get_messages&conversation_id=X`
3. Verify database connection
4. Check PHP error logs

## Browser Compatibility

### Tested Browsers:
- ✅ Chrome 90+ (Recommended)
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ⚠️ IE 11 (Not supported)

### Required Features:
- CSS Grid
- CSS Flexbox
- CSS Custom Properties (variables)
- ES6 JavaScript (arrow functions, template literals)

## Performance Tips

1. **Conversation List**
   - Loads on page load
   - Refreshes every 30 seconds
   - Click to open individual conversations

2. **Messages**
   - Load when conversation selected
   - Auto-scroll to bottom
   - Real-time updates via Pusher (if configured)

3. **Profile Data**
   - Loads on conversation selection
   - Cached until conversation changes

## API Endpoints Used

```php
// List all conversations
GET whatsapp_api.php?action=list_conversations

// Get messages for a conversation
GET whatsapp_api.php?action=get_messages&conversation_id=123

// Get tags
GET whatsapp_api.php?action=get_tags&conversation_id=123

// Add tag
POST whatsapp_api.php
  action=add_tag
  conversation_id=123
  tag=Warm Lead

// Remove tag
POST whatsapp_api.php
  action=remove_tag
  conversation_id=123
  tag=Warm Lead

// Intervene
POST whatsapp_api.php
  action=intervene
  conversation_id=123

// Resolve
POST whatsapp_api.php
  action=resolve
  conversation_id=123

// Send message
POST whatsapp_api.php
  action=send_text
  to=919999999999
  text=Hello

// Get journey
GET whatsapp_api.php?action=get_journey&conversation_id=123
```

## Color Reference

```css
Primary Teal:     #0e7c7b
Teal Dark:        #0a5e5d  
Teal Light:       #12a09f
Accent Green:     #25d366 (WhatsApp)
Background Light: #f0f2f5
Background White: #ffffff
Chat Background:  #e5ddd5
Text Dark:        #111827
Text Muted:       #6b7280
Border Color:     #e5e7eb
```

## Success Checklist

- [ ] Page loads without errors
- [ ] Sidebar is teal colored
- [ ] Three columns visible (desktop)
- [ ] Conversations list populates
- [ ] Can click and open conversations
- [ ] Messages display correctly
- [ ] Chat profile shows data
- [ ] Tags can be added/removed
- [ ] Customer journey displays
- [ ] Attributes editable
- [ ] Messages can be sent
- [ ] Intervene/Resolve works
- [ ] Dark mode toggles
- [ ] Responsive on mobile
- [ ] No console errors

## Need Help?

If issues persist:
1. Check browser console (F12) for errors
2. Review PHP error logs
3. Verify database tables exist
4. Test API endpoints directly
5. Clear all caches and try again

## Screenshots to Verify

Your UI should look like:
- **Sidebar**: Teal background, white text, modern icons
- **Conversation List**: Clean cards with avatars
- **Chat Messages**: WhatsApp-style bubbles
- **Profile Panel**: Organized sections with accordions
- **Tags**: Colorful pills with dropdown selector
- **Journey**: Timeline with connecting lines
- **Overall**: Modern, clean, professional AiSensy style

---
Last Updated: November 27, 2025



