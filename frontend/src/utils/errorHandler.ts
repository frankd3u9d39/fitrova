// API Error Handling Utility
import { CustomAlert } from '../components/common/CustomAlert';

export interface ApiError {
  message: string;
  status?: number;
  code?: string;
  data?: any;
}

export class ApiErrorHandler {
  static handleError(error: any): ApiError {
    let apiError: ApiError;
    
    // Parse different error types
    if (error.response) {
      // Axios-like response error
      apiError = {
        message: error.response.data?.message || 'Server error',
        status: error.response.status,
        code: error.response.data?.code,
        data: error.response.data,
      };
    } else if (error.request) {
      // Network error (no response)
      apiError = {
        message: 'Network error - please check your connection',
        status: 0,
        code: 'NETWORK_ERROR',
      };
    } else if (error.message) {
      // Generic error
      apiError = {
        message: error.message,
        status: error.status,
        code: error.code,
      };
    } else {
      // Unknown error
      apiError = {
        message: 'An unknown error occurred',
        status: 500,
      };
    }

    // Log the error (in development)
    if (process.env.NODE_ENV === 'development') {
      console.error('📊 API Error Details:', {
        message: apiError.message,
        status: apiError.status,
        code: apiError.code,
        originalError: error,
      });
    }

    return apiError;
  }

  static showUserFriendlyMessage(error: ApiError): void {
    const { message, status } = error;
    
    let userMessage = message;
    
    // Map status codes to user-friendly messages
    switch (status) {
      case 0:
      case 504:
        userMessage = 'Network timeout - please check your internet connection';
        break;
      case 401:
        userMessage = 'Session expired - please log in again';
        break;
      case 403:
        userMessage = 'You don\'t have permission to perform this action';
        break;
      case 404:
        userMessage = 'The requested resource was not found';
        break;
      case 422:
        userMessage = 'Please check your input and try again';
        break;
      case 429:
        userMessage = 'Too many requests - please wait a moment';
        break;
      case 500:
      case 502:
      case 503:
        userMessage = 'Server error - please try again later';
        break;
    }

    // Show alert to user
    CustomAlert.alert('Error', userMessage);
  }

  static shouldRetry(error: ApiError): boolean {
    // Retry on network errors or 5xx server errors
    return error.status === 0 || 
           (error.status && error.status >= 500 && error.status < 600);
  }

  static isAuthenticationError(error: ApiError): boolean {
    return error.status === 401 || error.code === 'AUTH_REQUIRED';
  }

  static isNetworkError(error: ApiError): boolean {
    return error.status === 0 || error.code === 'NETWORK_ERROR';
  }

  static formatForLogging(error: ApiError): Record<string, any> {
    return {
      timestamp: new Date().toISOString(),
      message: error.message,
      status: error.status,
      code: error.code,
      data: error.data,
      userAgent: navigator?.userAgent || 'unknown',
      platform: Platform?.OS || 'unknown',
    };
  }
}

// Convenience function
export const handleApiError = (error: any): ApiError => {
  return ApiErrorHandler.handleError(error);
};

export const showErrorAlert = (error: any): void => {
  const apiError = handleApiError(error);
  ApiErrorHandler.showUserFriendlyMessage(apiError);
};