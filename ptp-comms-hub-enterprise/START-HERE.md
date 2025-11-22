# PTP Comms Hub Enterprise - Quick Start Guide

## 🎉 Welcome!

You've just installed **PTP Comms Hub Enterprise** - a complete communication platform for PTP Soccer Camps with SMS, Voice, HubSpot, Slack, and intelligent automations.

## 🚀 Quick Setup (5 Minutes)

### Step 1: Activate Plugin
1. Upload to `/wp-content/plugins/ptp-comms-hub/`
2. Activate in WordPress admin

### Step 2: Configure Integrations
Go to **PTP Comms > Settings** and add:

**Twilio (Required for SMS/Voice):**
- Account SID
- Auth Token  
- Phone Number

**HubSpot (Optional but recommended):**
- API Key

**Slack (Optional but recommended):**
- Webhook URL

### Step 3: Review Templates
Go to **PTP Comms > Templates** - 8 professional templates are pre-loaded!

### Step 4: Create Your First Automation
1. Go to **PTP Comms > Automations**
2. Click "Add New"
3. Choose trigger (e.g., "Order Placed")
4. Select template (e.g., "Registration Confirmation")
5. Set delay (0 for immediate)
6. Activate!

## 📊 What You Get

### Core Features
- ✅ SMS & Voice messaging via Twilio
- ✅ Contact management
- ✅ Campaign builder
- ✅ 8 pre-built templates
- ✅ Two-way inbox
- ✅ Intelligent automations
- ✅ Communication logs

### Integrations
- ✅ HubSpot sync (contacts + deals + timeline)
- ✅ Slack notifications (real-time alerts)
- ✅ WooCommerce (auto-sync orders)

### Automation Triggers
- Order Placed
- Event Approaching (7 days)
- Event Approaching (1 day)
- Event Completed
- New Contact

## 🎯 Common Use Cases

### Scenario 1: Order Confirmation
**Trigger:** Order Placed  
**Template:** Registration Confirmation  
**Delay:** 0 minutes (immediate)  
**Result:** Customer gets instant SMS confirmation

### Scenario 2: Event Reminders
**Trigger:** Event Approaching (7 days)  
**Template:** Event Reminder - 7 Days  
**Delay:** 0 minutes  
**Result:** Parents reminded one week before event

### Scenario 3: Follow-up
**Trigger:** Event Completed  
**Template:** Thank You Follow-up  
**Delay:** 60 minutes  
**Result:** Thank you message sent after event

## 📱 Features Overview

### Dashboard
View stats: total contacts, opted-in users, messages sent, active automations

### Contacts
- Add/edit contacts manually
- Auto-import from WooCommerce orders
- Opt-in/opt-out management
- Phone normalization

### Campaigns
- Send bulk messages to segments
- Track delivery and status
- Schedule for later

### Templates
- 8 pre-built templates
- Create custom templates
- Use variables: `{parent_first_name}`, `{child_name}`, `{event_date}`, etc.
- Track usage

### Inbox
- View all conversations
- See inbound messages
- Track unread count
- Real-time updates

### Automations
- Visual automation builder
- Multiple trigger types
- Delay options
- Active/inactive status

### Communication Logs
- Complete message history
- Filter by type, direction, status
- Export capability

## 🔧 Template Variables

Use these in any template:

**Contact Variables:**
- `{parent_first_name}`, `{parent_last_name}`
- `{parent_phone}`, `{parent_email}`
- `{child_name}`, `{child_age}`

**Event Variables:**
- `{event_name}`, `{event_date}`, `{event_location}`
- `{market_slug}`, `{program_type}`

## ⚙️ Technical Details

### Database Tables (8)
- `ptp_contacts` - Contact information
- `ptp_registrations` - Event registrations
- `ptp_campaigns` - Campaign data
- `ptp_communication_logs` - Message history
- `ptp_templates` - Message templates
- `ptp_conversations` - Inbox threads
- `ptp_automations` - Automation rules
- `ptp_product_settings` - Product-specific config

### Cron Jobs
- `ptp_comms_process_automations` - Hourly (process pending automations)
- `ptp_comms_sync_hubspot` - Daily (sync contacts to HubSpot)

### REST API Endpoints
- `/ptp-comms/v1/send-sms` - Send SMS
- `/ptp-comms/v1/contacts` - Get contacts
- `/ptp-comms/v1/twiml` - TwiML generation

### Webhooks
- `/ptp-comms/sms-webhook` - Inbound SMS handler
- `/ptp-comms/voice-webhook` - Inbound voice handler

## 🎓 Best Practices

1. **Test First** - Send test messages to your own phone before going live
2. **Use Delays** - Add small delays to automations to avoid overwhelming customers
3. **Monitor Inbox** - Check the inbox regularly for inbound messages
4. **Track Logs** - Review communication logs for delivery issues
5. **Update Templates** - Customize templates to match your brand voice
6. **Segment Carefully** - Use market-specific segments for targeted campaigns
7. **HubSpot Sync** - Enable HubSpot for better customer tracking
8. **Slack Alerts** - Connect Slack for real-time team notifications

## 🆘 Troubleshooting

### Messages Not Sending
1. Verify Twilio credentials in Settings
2. Check phone number format (+1234567890)
3. Ensure contacts are opted-in
4. Review Communication Logs for errors

### Automations Not Triggering
1. Confirm automation is "Active"
2. Check trigger conditions
3. Verify template is assigned
4. Check cron jobs are running

### HubSpot Not Syncing
1. Verify API key in Settings
2. Check HubSpot portal permissions
3. Review contact data completeness

## 📚 Additional Resources

- **Full Documentation:** See ENTERPRISE-SETUP-GUIDE.md
- **Integration Guide:** See INTEGRATION-WORKFLOW-GUIDE.md
- **Debug Reference:** See DEBUG-REFERENCE.md
- **Feature Summary:** See ENTERPRISE-FEATURES-SUMMARY.md

## 🎉 You're Ready!

Your PTP Comms Hub is fully configured and ready to go. Start by:

1. Creating your first automation
2. Importing your contacts
3. Sending a test campaign
4. Monitoring the inbox for replies

**Questions?** Check the full documentation or contact support.

---

**Built for PTP Soccer Camps**  
Version 1.0.0 - Enterprise Edition
