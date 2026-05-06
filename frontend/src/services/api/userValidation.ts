import { API_BASE_URL } from './apiClient';

export interface EmailCheckResult {
  exists: boolean;
  message: string;
}

export const checkEmailExists = async (email: string): Promise<EmailCheckResult> => {
  try {
    const response = await fetch(`${API_BASE_URL}/app/controllers/auth/check_email.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Failed to check email');
    }

    return {
      exists: data.exists,
      message: data.message,
    };
  } catch (error) {
    console.error('Email check error:', error);
    return {
      exists: false,
      message: 'Unable to verify email availability',
    };
  }
};
