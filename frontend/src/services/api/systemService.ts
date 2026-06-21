import { endpoints } from './apiClient';

export interface AppUpdateData {
  version: string;
  is_active: boolean;
  force_update: boolean;
  message: string | null;
}

export interface OnboardingSlideData {
  id: number;
  title: string;
  description: string;
  sort_order: number;
}

/**
 * Fetches the latest active app update from the PHP backend.
 * @returns AppUpdateData or null if no update is configured.
 */
export const getAppUpdate = async (): Promise<AppUpdateData | null> => {
  try {
    const response = await fetch(endpoints.getAppUpdate);
    const result = await response.json();
    
    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Failed to check app update');
    }
    
    return result.data;
  } catch (error) {
    console.warn('⚠️ App update fetch failed (offline or network error):', error);
    return null; // Fail-safe: assume no update if network is unavailable
  }
};

/**
 * Fetches the active onboarding slides from the PHP backend.
 * @returns Array of OnboardingSlideData.
 */
export const getOnboardingSlides = async (): Promise<OnboardingSlideData[]> => {
  try {
    const response = await fetch(endpoints.getOnboardingSlides);
    const result = await response.json();
    
    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Failed to fetch onboarding slides');
    }
    
    return result.data || [];
  } catch (error) {
    console.error('Onboarding slides fetch error:', error);
    throw error;
  }
};

/**
 * Syncs onboarding completion state for the authenticated user to the backend database.
 * @param userId The ID of the current authenticated user.
 */
export const completeOnboarding = async (userId: number): Promise<boolean> => {
  try {
    const response = await fetch(endpoints.completeOnboarding, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId }),
    });
    
    const result = await response.json();
    return response.ok && result.status === 'success';
  } catch (error) {
    console.warn('⚠️ Could not sync onboarding completion to server (cached locally):', error);
    return false;
  }
};

/**
 * Syncs the latest app update version seen/dismissed by the user to the database.
 * @param userId The ID of the current authenticated user.
 * @param version The version string seen.
 */
export const updateVersionSeen = async (userId: number, version: string): Promise<boolean> => {
  try {
    const response = await fetch(endpoints.updateVersionSeen, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId, version }),
    });
    
    const result = await response.json();
    return response.ok && result.status === 'success';
  } catch (error) {
    console.warn('⚠️ Could not sync version seen status to server:', error);
    return false;
  }
};
