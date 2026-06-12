<?php
/**
 * Base Controller for API v1
 */

class BaseController {
    protected $db;
    protected $userId;
    
    public function __construct() {
        // Database connection
        require_once __DIR__ . '/../../../../config/db_config.php';
        $this->db = $pdo ?? $conn ?? null;
        
        // Get authenticated user ID
        $this->userId = $this->getAuthenticatedUserId();
    }
    
    protected function getAuthenticatedUserId() {
        // Get user ID from session or JWT token
        session_start();
        return $_SESSION['user_id'] ?? null;
    }
    
    protected function requireAuthentication() {
        if (!$this->userId) {
            $this->jsonResponse(['error' => 'Authentication required'], 401);
            exit();
        }
    }
    
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    protected function successResponse($data = null, $message = 'Success') {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->jsonResponse($response, 200);
    }
    
    protected function errorResponse($message = 'Error', $statusCode = 400, $errors = []) {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        $this->jsonResponse($response, $statusCode);
    }
    
    protected function validateRequired($data, $requiredFields) {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "Field '$field' is required";
            }
        }
        
        if (!empty($errors)) {
            $this->errorResponse('Validation failed', 422, $errors);
        }
    }
    
    protected function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    protected function getInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    protected function getQueryParam($key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    protected function rateLimit($key, $limit = 100, $window = 60) {
        // Simple rate limiting implementation
        // In production, use Redis or similar
        $rateKey = "rate_limit_{$key}_" . floor(time() / $window);
        $count = $_SESSION[$rateKey] ?? 0;
        
        if ($count >= $limit) {
            $this->errorResponse('Rate limit exceeded', 429);
        }
        
        $_SESSION[$rateKey] = $count + 1;
    }
}