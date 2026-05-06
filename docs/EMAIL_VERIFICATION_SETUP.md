# Email Verification Setup

## Overview
The app uses Abstract API for real-time email verification during registration to ensure users provide valid, deliverable email addresses.

## Features
- ✅ Format validation (instant)
- ✅ Real email existence check
- ✅ Blocks disposable/temporary emails
- ✅ Checks email deliverability
- ✅ Quality score validation

## Setup Instructions

### Step 1: Get Your API Key
1. Go to [Abstract API Email Verification](https://www.abstractapi.com/api/email-verification-validation-api)
2. Click "Get Started Free"
3. Sign up with your email
4. Copy your API key from the dashboard

### Step 2: Add API Key to Your Project
Open `frontend/src/services/api/emailVerification.ts` and replace:
```typescript
const ABSTRACT_API_KEY = 'YOUR_API_KEY_HERE';
```
with your actual API key:
```typescript
const ABSTRACT_API_KEY = 'abcd1234your-actual-key-here';
```

### Step 3: Test It
1. Run your app
2. Go to the Sign Up screen
3. Enter an email address
4. Click "Continue"
5. The app will verify the email before proceeding

## Free Tier Limits
- **100 verifications per month** (free)
- Upgrade to paid plans for more verifications

## Alternative Services (if you need more verifications)

### EmailValidation.io
- Free tier: 1,000 verifications/month
- Website: https://emailvalidation.io/
- Update the API endpoint in `emailVerification.ts`

### Hunter.io
- Free tier: 50 verifications/month
- Website: https://hunter.io/email-verifier
- Update the API endpoint in `emailVerification.ts`

## How It Works

1. **User enters email** → Format validation (instant)
2. **User clicks Continue** → API verification starts
3. **API checks:**
   - Email format is valid
   - Email domain exists
   - Mailbox exists and can receive emails
   - Not a disposable/temporary email
   - Quality score > 0.7
4. **If valid** → User proceeds to next step
5. **If invalid** → Error message shown

## Validation Rules

The email is rejected if:
- ❌ Invalid format
- ❌ Disposable email (e.g., temp-mail.org)
- ❌ Email doesn't exist
- ❌ Undeliverable
- ❌ Quality score < 0.7

## Customization

Edit `frontend/src/hooks/useEmailVerification.ts` to adjust validation rules:

```typescript
// Adjust quality score threshold (0.0 to 1.0)
if (result.qualityScore < 0.7) {  // Change 0.7 to your preferred threshold
  // ...
}

// Allow/block free emails (Gmail, Yahoo, etc.)
if (result.isFreeEmail) {
  // Add custom logic here
}
```

## Troubleshooting

### API Not Working?
- Check your API key is correct
- Verify you haven't exceeded free tier limit (100/month)
- Check internet connection
- The app will allow registration if API fails (fallback behavior)

### Want to Disable Verification?
Comment out the verification in `SignUpScreen.tsx`:
```typescript
// const isValid = await verifyEmailAddress(email);
// if (!isValid) {
//   Alert.alert('Invalid Email', verificationError || 'Please enter a valid email address');
//   return;
// }
```
