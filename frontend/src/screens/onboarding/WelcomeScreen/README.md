# Welcome Screen - AI FitTracker

A modern, clean welcome screen for the AI FitTracker mobile fitness application.

## Features

### Top Section
- Custom AI FitTracker logo with gradient and AI elements
- App name in bold, modern typography
- Tagline: "Your AI-powered fitness and nutrition companion"

### Middle Section
- Custom SVG illustration featuring:
  - Person in running pose
  - Heartbeat monitor widget
  - Steps counter widget
  - AI nodes with connecting lines
  - Background activity graph
  - Fitness icons (dumbbell, food)

### Bottom Section
- **Get Started** button (primary) - navigates to Registration
- **Login** button (secondary/outline) - navigates to Login
- Feature tags showing key app capabilities:
  - 💪 Track Workouts
  - 🥗 Monitor Diet
  - 🤖 AI Recognition

## Design Specifications

### Colors
- Primary: Blue (#4A90E2)
- Secondary: Green (#2ECC71)
- Accent: Orange (#FF6B35)
- Background: Light gradient (#F8F9FA → #E8F4F8)

### Typography
- App Name: 36px, Bold
- Tagline: 16px, Regular
- Button Text: 18px, Semibold

### Layout
- Mobile optimized (375px - 430px width)
- Responsive design using Dimensions API
- Safe area handling for notched devices

### Styling
- Rounded corners (12-16px)
- Soft shadows for depth
- Large, touch-friendly buttons (minimum 48px height)
- Minimalist fitness-tech aesthetic

## Installation

Install required dependencies:

```bash
cd frontend
npm install
```

Required packages (already added to package.json):
- `expo-linear-gradient` - For gradient backgrounds
- `react-native-svg` - For custom illustrations and logo

## Usage

```tsx
import WelcomeScreen from './src/screens/onboarding/WelcomeScreen';

// In your navigation stack
<Stack.Screen name="Welcome" component={WelcomeScreen} />
```

## Navigation

The screen expects navigation prop with these routes:
- `Register` - User registration screen
- `Login` - User login screen

## Customization

### Changing Colors
Edit `frontend/src/theme/colors.ts` to modify the color scheme.

### Modifying Illustration
Edit `WelcomeIllustration.tsx` to customize the SVG illustration.

### Updating Logo
Edit `AppLogo.tsx` to change the app logo design.

## File Structure

```
WelcomeScreen/
├── index.tsx                 # Main screen component
├── WelcomeIllustration.tsx   # SVG illustration
├── AppLogo.tsx               # App logo component
└── README.md                 # This file
```

## Accessibility

- Touch targets meet minimum size requirements (48x48dp)
- High contrast text for readability
- Semantic component structure
- StatusBar configured for optimal visibility

## Performance

- SVG components for scalable graphics
- Optimized illustration complexity
- Minimal re-renders with React.FC
- Efficient gradient rendering
