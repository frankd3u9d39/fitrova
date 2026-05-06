import { useState, useCallback } from 'react';
import { verifyEmail, validateEmailFormat } from '../services/api/emailVerification';

export const useEmailVerification = () => {
  const [isVerifying, setIsVerifying] = useState(false);
  const [verificationError, setVerificationError] = useState<string | null>(null);

  const verifyEmailAddress = useCallback(async (email: string): Promise<boolean> => {
    if (!validateEmailFormat(email)) {
      setVerificationError('Invalid email format');
      return false;
    }

    setIsVerifying(true);
    setVerificationError(null);

    try {
      const result = await verifyEmail(email);

      // 1. Check if email already exists in our database
      if (result.exists) {
        setVerificationError('This email is already associated with an account. Please use a different email or try logging in.');
        setIsVerifying(false);
        return false;
      }

      // 2. Check deliverability and quality
      if (!result.isValid) {
        setVerificationError('Invalid email format');
        setIsVerifying(false);
        return false;
      }

      if (result.isDisposable) {
        setVerificationError('Disposable email addresses are not allowed');
        setIsVerifying(false);
        return false;
      }

      if (result.deliverable === 'UNDELIVERABLE') {
        setVerificationError('This email address does not exist. Please check and try again.');
        setIsVerifying(false);
        return false;
      }

      if (result.qualityScore === 0) {
        setVerificationError('This email address appears to be invalid or high risk');
        setIsVerifying(false);
        return false;
      }

      setIsVerifying(false);
      return true;
    } catch (error) {
      console.warn('Email verification process encountered an issue:', error);
      setIsVerifying(false);
      // We return true as a fallback to let the user proceed if the verification service is down
      return true;
    }
  }, []);

  const clearError = useCallback(() => {
    setVerificationError(null);
  }, []);

  return {
    verifyEmailAddress,
    isVerifying,
    verificationError,
    clearError,
  };
};
