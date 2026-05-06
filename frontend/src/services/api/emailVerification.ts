import { endpoints } from './apiClient';

export interface EmailVerificationResult {
  isValid: boolean;
  isDisposable: boolean;
  isFreeEmail: boolean;
  deliverable: string; // 'DELIVERABLE', 'UNDELIVERABLE', 'UNKNOWN'
  qualityScore: number; // 0.0 to 1.0
  exists?: boolean; // New: track if email already exists in DB
}

export const verifyEmail = async (email: string): Promise<EmailVerificationResult> => {
  try {
    const response = await fetch(endpoints.checkEmail, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email }),
    });

    if (!response.ok) {
      throw new Error('Email verification request failed');
    }

    const data = await response.json();
    
    // The backend now returns both existence and verification details
    if (data.exists) {
      return {
        isValid: true,
        isDisposable: false,
        isFreeEmail: false,
        deliverable: 'DELIVERABLE',
        qualityScore: 1.0,
        exists: true
      };
    }

    const verification = data.verification || {};
    
    return {
      isValid: verification.isValid !== false,
      isDisposable: verification.isDisposable === true,
      isFreeEmail: verification.isFreeEmail === true,
      deliverable: verification.deliverable || 'UNKNOWN',
      qualityScore: verification.qualityScore !== undefined ? verification.qualityScore : 0.5,
      exists: false
    };
  } catch (error) {
    console.warn('Email verification fallback used due to error:', error);
    return {
      isValid: true,
      isDisposable: false,
      isFreeEmail: false,
      deliverable: 'UNKNOWN',
      qualityScore: 0.5,
      exists: false
    };
  }
};

// Simple regex validation (instant, no network call)
export const validateEmailFormat = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};
