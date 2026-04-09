// Email verification service using Abstract API
// Get your free API key from: https://www.abstractapi.com/api/email-verification-validation-api

const ABSTRACT_API_KEY = '6ea8da050ed2489cbc0ab07df4b4602a'; // Your API key

export interface EmailVerificationResult {
  isValid: boolean;
  isDisposable: boolean;
  isFreeEmail: boolean;
  deliverable: string; // 'DELIVERABLE', 'UNDELIVERABLE', 'UNKNOWN'
  qualityScore: number; // 0.0 to 1.0
}

export const verifyEmail = async (email: string): Promise<EmailVerificationResult> => {
  try {
    const response = await fetch(
      `https://emailreputation.abstractapi.com/v1/?api_key=${ABSTRACT_API_KEY}&email=${encodeURIComponent(email)}`
    );

    if (!response.ok) {
      throw new Error('Email verification failed');
    }

    const data = await response.json();

    // Parse the actual API structure
    const deliverability = data.email_deliverability?.status || 'unknown';
    const quality = data.email_quality || {};
    
    const result = {
      isValid: data.email_deliverability?.is_format_valid !== false,
      isDisposable: quality.is_disposable === true,
      isFreeEmail: quality.is_free_email === true,
      deliverable: deliverability === 'deliverable' ? 'DELIVERABLE' : 
                   deliverability === 'undeliverable' ? 'UNDELIVERABLE' : 'UNKNOWN',
      qualityScore: quality.score !== undefined ? quality.score : 0.5,
    };
    
    return result;
  } catch (error) {
    console.error('Email verification error:', error);
    return {
      isValid: true,
      isDisposable: false,
      isFreeEmail: false,
      deliverable: 'UNKNOWN',
      qualityScore: 0.5,
    };
  }
};

// Simple regex validation (instant, no API call)
export const validateEmailFormat = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};
