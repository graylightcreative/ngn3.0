# Beta Tester Onboarding Guide

**For**: Board members (Chairman, Directors) + Early adopters
**Duration**: 15-30 minutes to get started
**Support**: #ngn-beta Slack channel

---

## 🎯 WELCOME TO NGN 2.0.1 BETA!

You're helping us launch the next generation of NGN with **Chapter 31: Governance System**.

### What You're Testing
- ✅ Standardized Input Request (SIR) tracking
- ✅ Board member workflow automation
- ✅ Mobile push notifications
- ✅ One-tap verification on mobile
- ✅ Enhanced dashboards & features
- ✅ Stripe payment integration

### Expected Beta Duration
- **Week 1**: Core testing (your critical feedback)
- **Weeks 2-3**: Feature expansion
- **Week 4**: Stability & polish
- **Week 5+**: Full production release

---

## 📋 GETTING STARTED (5 minutes)

### Step 1: Access the Beta
**URL**: https://beta.ngn.local
**Existing Credentials**: Use your current NGN login

### Step 2: First Login
- [ ] Visit the URL above
- [ ] Log in with your credentials
- [ ] You should see the governance dashboard

### Step 3: Download Mobile App (Optional but Recommended)
- **iOS**: Install PWA: Share → Add to Home Screen
- **Android**: Menu → Install App
- This allows one-tap verification from notifications

### Step 4: Join Support Channel
- **Slack**: #ngn-beta
- Post questions here
- We monitor 24/7 during beta

---

## 🎓 GOVERNANCE WORKFLOW WALKTHROUGH

### For Chairman (Jon Brock Lamb)

#### Task 1: Create Your First SIR (5 mins)

1. **Navigate to Governance Dashboard**
   - Dashboard → Governance
   - OR: `/dashboard/governance`

2. **Click "New SIR" (+ button)**

3. **Fill in the Four Pillars**:
   - **Objective**: "Verify Escrow Compliance" (one sentence goal)
   - **Context**: "Ensure Rights Ledger dispute handling meets institutional standards"
   - **Deliverable**: "One-page technical critique"
   - **Threshold**: "Before Jan 26" (or pick a date)

4. **Assign to Director**
   - Select: Brandon Lamb, Pepper Gomez, or Erik Baker
   - Each handles a different registry division

5. **Set Priority**
   - Recommended: "critical" for first test

6. **Click "Create SIR"**

7. **Verify**:
   - ✅ SIR appears in your dashboard
   - ✅ Assigned director receives push notification (mobile)

#### Task 2: Monitor Workflow

- [ ] Watch director change status to "In Review"
- [ ] See them add feedback (Rant Phase)
- [ ] Watch them verify the SIR
- [ ] See final status change to "Closed"

---

### For Directors (Brandon, Pepper, Erik)

#### Task 1: Receive & Claim SIR (3 mins)

1. **Desktop**: You'll see a notification in your dashboard
2. **Mobile**: You'll receive a push notification
3. **Click notification** → Opens SIR detail page
4. **Click "Claim" button** → Status changes to "In Review"

#### Task 2: Review & Add Feedback (5 mins)

1. **Read SIR details** (The Four Pillars)
2. **Click "Add Feedback"** (Rant Phase)
3. **Type your technical review**
   - Example: "The escrow logic needs a 7-day dispute window"
4. **Submit feedback**
5. **Status changes** → "Rant Phase"

#### Task 3: One-Tap Verification (1 min - Mobile!)

**On Mobile Device**:
1. You'll receive a push notification: "Ready for Verification"
2. Click "Verify" button in the notification (don't open app)
3. That's it! SIR marked as VERIFIED
4. Status changes automatically

**On Desktop**:
1. Click "Verify" button in SIR detail
2. Confirm in dialog
3. SIR marked as VERIFIED

---

## 🧪 TESTING CHECKLIST

### Core Governance Flow
- [ ] **SIR Creation** (Chairman only)
  - Create a SIR with all four pillars
  - Assign to each director type
  - Verify notifications sent

- [ ] **Status Workflow**
  - OPEN → IN_REVIEW (director claims)
  - IN_REVIEW → RANT_PHASE (director adds feedback)
  - RANT_PHASE → VERIFIED (director verifies)
  - VERIFIED → CLOSED (chairman closes)

- [ ] **Feedback Thread**
  - Add feedback from both chairman & director
  - Verify threaded discussion works
  - Check timestamps are correct

- [ ] **Mobile Verification**
  - Receive push notification on mobile
  - Tap "Verify" action directly from notification
  - Verify SIR status updates immediately

- [ ] **Audit Trail**
  - View SIR detail page
  - Scroll to "Audit Trail" section
  - Verify all status changes are logged

### Dashboard Features (Bonus)
- [ ] **Artist Dashboard**
  - Create a post → Verify displays
  - Delete a post → Verify removed
  - Check analytics view

- [ ] **Payment Flow**
  - Click "Upgrade Tier" button
  - Use Stripe test card: `4242 4242 4242 4242`
  - Exp: any future date, CVC: any 3 digits
  - Verify redirect back to dashboard

- [ ] **Mobile PWA**
  - Install app to home screen
  - Use offline functionality
  - Check one-tap verification works

---

## 💡 WHAT TO TEST & HOW TO REPORT

### Bugs vs Feature Requests

**Report as Bug if**:
- Functionality doesn't work as described
- Error messages appear
- Data doesn't save correctly
- Performance is very slow (> 5 seconds)
- Mobile features crash

**Report as Feature Request if**:
- Wish list item ("would be nice if...")
- Enhancement suggestion
- UI/UX improvement
- Non-critical workflow issue

### How to Report Issues

**In Slack** (#ngn-beta):
```
🐛 BUG: [Title]
Environment: [Desktop/Mobile/Both]
Steps to reproduce:
1. Step 1
2. Step 2
3. ...
Expected: [What should happen]
Actual: [What happened]
Screenshot: [If applicable]
```

**Via GitHub** (optional):
- Create issue: https://github.com/your-org/ngn/issues
- Use template provided
- Include same info as Slack

### Priority Levels

| Level | Example | Response Time |
|-------|---------|---|
| P0 (Critical) | Can't create SIR, system crashes | 1 hour |
| P1 (High) | Feature doesn't work but workaround exists | 4 hours |
| P2 (Medium) | UI issue, minor workflow problem | 1 day |
| P3 (Low) | Typo, cosmetic issue, nice-to-have | Next sprint |

---

## 📊 SUCCESS METRICS

We're tracking:

1. **Functionality**
   - Can SIRs be created/updated/verified? ✅
   - Do push notifications arrive? ✅
   - Does audit trail work? ✅

2. **Performance**
   - Page load time (target: < 2s) ✅
   - API response time (target: < 250ms) ✅
   - Payment processing (target: < 5s) ✅

3. **Stability**
   - Zero crashes per session
   - Data persists correctly
   - No permission errors

4. **User Experience**
   - One-tap verification is smooth
   - Mobile PWA works offline
   - Notifications feel timely

---

## 🆘 TROUBLESHOOTING

### "I don't see the governance dashboard"
**Solution**:
- Refresh page (Cmd+R / Ctrl+F5)
- Clear browser cache
- Try in incognito/private window
- Check Slack #ngn-beta for known issues

### "I didn't receive a push notification"
**Solution**:
- Check notification settings (browser/phone)
- Ensure permissions granted
- Refresh the page
- Try on mobile device
- Check if notification was dismissed

### "One-tap verification isn't working on mobile"
**Solution**:
- Make sure you're on mobile device
- Tap the notification action (not the notification itself)
- If using PWA, ensure service worker is registered
- Try on both iOS and Android

### "Stripe test card is declined"
**Solution**:
- Use card: `4242 4242 4242 4242`
- Exp: Any future date
- CVC: Any 3 digits
- Email: Any email
- If still fails, check browser console for errors

### "Status change didn't take effect"
**Solution**:
- Refresh page
- Check if you have permission (only assigned director can verify)
- Look for error message at top of page
- Report in Slack if error message appears

### "My SIR disappeared"
**Solution**:
- Check if it was closed (status = CLOSED)
- Filter dashboard to show "All" statuses
- Check if you're logged in as correct user
- Report in Slack with SIR number if still missing

---

## 📞 SUPPORT CHANNELS

### Get Help
1. **Slack** (#ngn-beta) - Fastest response
   - Live support during business hours
   - 24/7 monitoring during beta

2. **Email** - beta@ngn.local
   - For detailed questions
   - Response within 4 hours

3. **Phone** - [Emergency number for P0 issues]
   - Critical production issues only

### Expected Response Times
- **P0 (Critical)**: 1 hour
- **P1 (High)**: 4 hours
- **P2 (Medium)**: 1 business day
- **P3 (Low)**: Best effort

---

## 🎁 THANK YOU!

Your testing directly impacts NGN's success. Here's what we appreciate:

✅ **Detailed bug reports** - Helps us fix quickly
✅ **Trying edge cases** - Catches issues we missed
✅ **Mobile testing** - Critical for user experience
✅ **Feedback on UX** - Makes product better
✅ **Patience** - Beta means things might break

### Beta Tester Rewards
- **Week 1**: Early access to features
- **Week 2**: Customized training session
- **Launch**: VIP recognition in docs
- **Post-Launch**: Early adopter discount (if applicable)

---

## 🚀 QUICK START COMMANDS

### Desktop
```
1. Visit: https://beta.ngn.local
2. Login with your credentials
3. Go to Dashboard → Governance
4. Click "New SIR" or wait for notification
```

### Mobile
```
1. Open browser
2. Visit: https://beta.ngn.local
3. Go to menu → "Install App" (Android) or Share → Add to Home Screen (iOS)
4. Open app
5. Push notifications should arrive automatically
```

---

## 📋 DAY 1 TESTING PLAN (30 mins)

Suggested testing order:

| Time | Activity | Expected Result |
|------|----------|---|
| 0-5 min | Install app (mobile) | App on home screen |
| 5-10 min | Create SIR (chairman) | SIR appears in dashboard |
| 10-15 min | Receive notification (director) | Notification arrives on mobile |
| 15-20 min | Claim & add feedback (director) | Status changes, feedback visible |
| 20-25 min | One-tap verify (mobile) | Status = VERIFIED immediately |
| 25-30 min | Close SIR (chairman) | Status = CLOSED, no edits allowed |

**Report any issues in #ngn-beta** 🎯

---

## ✅ CHECKLIST BEFORE YOU START

- [ ] You have access to https://beta.ngn.local
- [ ] You can log in with existing credentials
- [ ] You have Slack and can see #ngn-beta
- [ ] You understand the four pillars (Objective, Context, Deliverable, Threshold)
- [ ] You have optional: Mobile device for testing PWA
- [ ] You know where to report issues
- [ ] You understand the status workflow

---

**Beta Start Date**: 2026-01-25 (Friday)
**Beta Duration**: 2-4 weeks
**Support**: 24/7 monitoring
**Slack Channel**: #ngn-beta

**Welcome aboard! Let's ship something great together! 🚀**
