# Hostinger Cron Job Setup Guide

## Overview
This guide explains how to set up cron jobs in Hostinger to process the notification queue for lead assignments.

## System Architecture

1. **MySQL Trigger**: When a lead is assigned to an agent, the trigger `after_lead_assignment_insert` automatically inserts a record into the `notification_queue` table.

2. **Cron Job**: The `process_notifications.php` script runs periodically (every minute recommended) to:
   - Process pending notifications from the queue
   - Send push notifications via OneSignal
   - Process reminder notifications

3. **Notification Flow**:
   ```
   Lead Assignment → MySQL Trigger → notification_queue → Cron Job → OneSignal → Agent Device
   ```

## Step-by-Step Setup Instructions

### Step 1: Access Hostinger cPanel

1. Log in to your Hostinger account
2. Go to **hPanel** (Hostinger's control panel)
3. Navigate to **Advanced** → **Cron Jobs**

### Step 2: Find Your PHP Path

Before setting up the cron, you need to find the correct PHP path. Common paths in Hostinger:
- `/usr/bin/php` (most common)
- `/opt/alt/php81/usr/bin/php` (for PHP 8.1)
- `/opt/alt/php82/usr/bin/php` (for PHP 8.2)

**To find your PHP path:**
1. Create a test file: `test_php_path.php` in your `phpcode` directory
2. Add this content:
   ```php
   <?php
   echo exec('which php');
   ?>
   ```
3. Visit the file in your browser to see the PHP path

### Step 3: Find Your Script Path

Your script is located at:
```
/home/u675018328/domains/sandybrown-gull-863456.hostingersite.com/public_html/phpcode/process_notifications.php
```

**To verify the exact path:**
1. Create a test file: `test_path.php` in your `phpcode` directory
2. Add this content:
   ```php
   <?php
   echo __DIR__;
   ?>
   ```
3. Visit the file in your browser to see the exact path

### Step 4: Set Up the Cron Job

In Hostinger's Cron Jobs interface:

1. **Common Settings**: Select "Every Minute" or use custom cron expression
2. **Command**: Use one of these formats:

   **Option 1: Every Minute (Recommended)**
   ```
   * * * * * /usr/bin/php /home/u675018328/domains/sandybrown-gull-863456.hostingersite.com/public_html/phpcode/process_notifications.php
   ```

   **Option 2: Every 5 Minutes (Less frequent)**
   ```
   */5 * * * * /usr/bin/php /home/u675018328/domains/sandybrown-gull-863456.hostingersite.com/public_html/phpcode/process_notifications.php
   ```

   **Option 3: Every 2 Minutes (Balanced)**
   ```
   */2 * * * * /usr/bin/php /home/u675018328/domains/sandybrown-gull-863456.hostingersite.com/public_html/phpcode/process_notifications.php
   ```

3. **Important**: Replace `/usr/bin/php` with your actual PHP path from Step 2
4. **Important**: Replace the script path with your actual path from Step 3

### Step 5: Test the Cron Job Manually

Before relying on the cron, test the script manually:

1. **Via Browser**: Visit:
   ```
   https://sandybrown-gull-863456.hostingersite.com/phpcode/process_notifications.php
   ```
   You should see output like:
   ```
   No pending notifications.
   ```
   or
   ```
   Notification 1 → sent
   Notification 2 → sent
   ```

2. **Via SSH** (if available):
   ```bash
   /usr/bin/php /home/u675018328/domains/sandybrown-gull-863456.hostingersite.com/public_html/phpcode/process_notifications.php
   ```

### Step 6: Monitor the Cron Job

1. **Check Logs**: The script logs errors to:
   ```
   /home/u675018328/domains/sandybrown-gull-863456.hostingersite.com/public_html/phpcode/logs/notification_cron_errors.log
   ```

2. **Check Database**: Query the `notification_queue` table:
   ```sql
   SELECT * FROM notification_queue 
   WHERE status = 'pending' 
   ORDER BY created_at DESC 
   LIMIT 10;
   ```

3. **Check Cron Execution**: In Hostinger cPanel, you can view cron job execution logs

## Cron Expression Reference

```
* * * * *
│ │ │ │ │
│ │ │ │ └── Day of week (0-7, Sunday = 0 or 7)
│ │ │ └──── Month (1-12)
│ │ └────── Day of month (1-31)
│ └──────── Hour (0-23)
└────────── Minute (0-59)
```

**Examples:**
- `* * * * *` = Every minute
- `*/5 * * * *` = Every 5 minutes
- `*/2 * * * *` = Every 2 minutes
- `0 * * * *` = Every hour at minute 0
- `0 */6 * * *` = Every 6 hours

## Troubleshooting

### Issue: Cron job not running

**Solutions:**
1. Verify the PHP path is correct
2. Verify the script path is correct
3. Check file permissions (should be 644 or 755)
4. Check if cron jobs are enabled in your Hostinger plan

### Issue: Notifications not being sent

**Solutions:**
1. Check `notification_cron_errors.log` for errors
2. Verify OneSignal credentials in `includes/config.php`
3. Check if agents have `onesignal_player_id` in the `users` table
4. Verify the `notification_queue` table has pending records

### Issue: "No pending notifications" but leads are assigned

**Solutions:**
1. Check if the MySQL trigger is active:
   ```sql
   SHOW TRIGGERS LIKE 'lead_assignments';
   ```
2. Check if trigger is inserting into queue:
   ```sql
   SELECT * FROM notification_queue ORDER BY created_at DESC LIMIT 5;
   ```
3. Manually test the trigger by inserting a test assignment

### Issue: PHP path not found

**Solutions:**
1. Try common paths:
   - `/usr/bin/php`
   - `/opt/alt/php81/usr/bin/php`
   - `/opt/alt/php82/usr/bin/php`
2. Contact Hostinger support for the correct PHP path
3. Use `which php` command via SSH if available

## Code Review Summary

### ✅ What's Correct:

1. **MySQL Trigger**: The trigger correctly inserts into `notification_queue` when a lead is assigned
2. **Processing Script**: `process_notifications.php` correctly:
   - Fetches pending notifications
   - Sends via OneSignal
   - Updates queue status
   - Handles errors
3. **Notification Function**: `sendOneSignalNotificationToAgent()` correctly retrieves player IDs and sends notifications

### ⚠️ Improvements Made:

1. **Error Logging**: Changed from relative paths (`./reminder_errors.log`) to absolute paths using `getLogDirectory()` function
2. **Error Handling**: Added try-catch blocks around notification processing
3. **Trigger Safety**: Created improved trigger with existence check (optional upgrade)

## Recommended Settings

- **Frequency**: Every 1-2 minutes (for real-time notifications)
- **Batch Size**: 10 notifications per run (already configured)
- **Error Logging**: Enabled (logs to `logs/notification_cron_errors.log`)

## Testing Checklist

- [ ] Cron job is set up in Hostinger
- [ ] Script path is correct
- [ ] PHP path is correct
- [ ] Manual test via browser works
- [ ] Notifications appear in queue after lead assignment
- [ ] Notifications are processed and sent
- [ ] Error logs are being created
- [ ] Agents receive push notifications

## Support

If you encounter issues:
1. Check the error logs first
2. Test the script manually
3. Verify database connections
4. Contact Hostinger support for cron-related issues

