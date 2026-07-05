<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Tukar ke 1 untuk debug

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $host = 'localhost';
    $dbname = 'hourawebsystemdb';
    $username = 'root';
    $password = ''; 
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

class AIResponse {
    public static function success($data) {
        return json_encode(array_merge([
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s')
        ], $data));
    }
    
    public static function error($message, $code = 400) {
        http_response_code($code);
        return json_encode([
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

class GroqAI {
    private $apiKey;
    private $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    private $model;
    
    public function __construct($apiKey, $model = 'llama-3.1-8b-instant') {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }
    
    public function generateResponse($userMessage, $context = [], $systemPrompt = '') {
        $startTime = microtime(true);
        
        $messages = [];
        
        if (!empty($systemPrompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt
            ];
        }
        
        $recentContext = array_slice($context, -3);
        foreach ($recentContext as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }
    
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];
        
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'top_p' => 0.95,
        ];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if (is_resource($ch) || (is_object($ch) && get_class($ch) === 'CurlHandle')) {
            curl_close($ch);
        }
        
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $curlError,
                'response_time' => $responseTime
            ];
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = isset($errorData['error']['message']) 
                ? $errorData['error']['message'] 
                : 'HTTP Error ' . $httpCode;
            return [
                'success' => false,
                'error' => $errorMsg,
                'response_time' => $responseTime,
                'http_code' => $httpCode
            ];
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'response_time' => $responseTime
            ];
        }
        
        if (isset($data['error'])) {
            return [
                'success' => false,
                'error' => $data['error']['message'],
                'response_time' => $responseTime
            ];
        }
        
        $aiText = '';
        if (isset($data['choices'][0]['message']['content'])) {
            $aiText = $data['choices'][0]['message']['content'];
        }
        
        if (empty($aiText)) {
            return [
                'success' => false,
                'error' => 'Empty response from AI',
                'response_time' => $responseTime
            ];
        }
        
        $tokensUsed = $data['usage']['total_tokens'] ?? 0;
        
        return [
            'success' => true,
            'response' => $aiText,
            'tokens_used' => $tokensUsed,
            'response_time' => $responseTime,
            'model' => $this->model
        ];
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo AIResponse::error('Only POST requests allowed', 405);
        exit;
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input || !isset($input['message'])) {
        echo AIResponse::error('Message is required');
        exit;
    }
    
    $userMessage = trim($input['message']);
    $sessionId = isset($input['session_id']) ? intval($input['session_id']) : null;
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    
    if (empty($userMessage)) {
        echo AIResponse::error('Message cannot be empty');
        exit;
    }
    
    if (strlen($userMessage) > 500) {
        echo AIResponse::error('Message too long (max 500 characters)');
        exit;
    }
    
    $settings = [];
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE category = 'ai_config'");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        $settings = [];
    }
    
$aiEnabled = strtolower(trim($settings['ai_enabled'] ?? '0'));

$enabledValues = ['1', 'true', 'enabled', 'on', 'yes'];

if (!in_array($aiEnabled, $enabledValues, true)) {
    echo AIResponse::error('AI chatbot is currently disabled');
    exit;
}
    
    $apiKey = $settings['ai_api_key'] ?? '';
    if (empty($apiKey)) {
        echo AIResponse::error('AI API key not configured. Please contact administrator.');
        exit;
    }
    
    $model = $settings['ai_model'] ?? 'llama-3.1-8b-instant'; 
    
    $validGroqModels = [
        'llama-3.1-8b-instant',      
        'llama-3.3-70b-versatile',   
        'llama-3.3-70b-specdec',
        'mixtral-8x7b-32768',
        'gemma2-9b-it'
    ];
    
    if (!in_array($model, $validGroqModels)) {
        $model = 'llama-3.1-8b-instant'; 
    }
    
    $ai = new GroqAI($apiKey, $model);
    
    $context = [];
    $dbSessionId = null;
    
    if ($userId && $sessionId) {
        try {
            $stmt = $conn->prepare("
                SELECT message, ai_response as response 
                FROM ai_chat_history 
                WHERE session_id = ? AND user_id = ?
                ORDER BY created_at DESC 
                LIMIT 3
            ");
            $stmt->execute([$sessionId, $userId]);
            $history = $stmt->fetchAll();
            
            $history = array_reverse($history);
            foreach ($history as $h) {
                $context[] = ['role' => 'user', 'content' => $h['message']];
                $context[] = ['role' => 'assistant', 'content' => $h['response']];
            }
            
            $dbSessionId = $sessionId;
        } catch (PDOException $e) {
            $context = [];
        }
    }
    
    $systemPrompt = $settings['ai_system_prompt'] ?? 'You are HouraBot, a helpful assistant for Houra - a time banking skill exchange platform. Help users understand how to exchange skills, earn time credits, and use the platform. Be friendly, concise, and helpful. Answer in the same language the user uses (English or Malay).';
    
    $aiResult = $ai->generateResponse($userMessage, $context, $systemPrompt);
    
    if (!$aiResult['success']) {
        error_log("Groq AI Error: " . $aiResult['error']);

        $fallbackResponses = [
            'en' => "I'm sorry, I'm having trouble connecting to the AI service right now. Please try again in a moment."
        ];
        
        $fallbackText = $fallbackResponses['en'];
        
        echo AIResponse::success([
            'response' => $fallbackText,
            'session_id' => $dbSessionId,
            'is_fallback' => true,
            'is_ai' => false,
            'error_details' => $aiResult['error']
        ]);
        exit;
    }
    
    if ($userId) {
        try {
            if (!$dbSessionId) {
                $stmt = $conn->prepare("INSERT INTO ai_chat_sessions (user_id, started_at, last_activity) VALUES (?, NOW(), NOW())");
                $stmt->execute([$userId]);
                $dbSessionId = $conn->lastInsertId();
            } else {
                $stmt = $conn->prepare("UPDATE ai_chat_sessions SET last_activity = NOW() WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$dbSessionId, $userId]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO ai_chat_history 
                (session_id, user_id, message, ai_response, model_used, tokens_used, response_time_ms, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $dbSessionId,
                $userId,
                $userMessage,
                $aiResult['response'],
                $aiResult['model'],
                $aiResult['tokens_used'],
                $aiResult['response_time']
            ]);
            
            $today = date('Y-m-d');
            $stmt = $conn->prepare("
                INSERT INTO ai_usage_stats (user_id, stat_date, total_requests, total_tokens, avg_response_time_ms)
                VALUES (?, ?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE
                total_requests = total_requests + 1,
                total_tokens = total_tokens + VALUES(total_tokens),
                avg_response_time_ms = (avg_response_time_ms * (total_requests - 1) + VALUES(avg_response_time_ms)) / total_requests
            ");
            $stmt->execute([$userId, $today, $aiResult['tokens_used'], $aiResult['response_time']]);
            
        } catch (PDOException $e) {
            error_log("Database save error: " . $e->getMessage());
        }
    }
    
    echo AIResponse::success([
        'response' => $aiResult['response'],
        'session_id' => $dbSessionId,
        'model' => $aiResult['model'],
        'tokens_used' => $aiResult['tokens_used'],
        'response_time_ms' => $aiResult['response_time'],
        'is_ai' => true,
        'is_fallback' => false
    ]);
    
} catch (Exception $e) {
    error_log("AI Chatbot Exception: " . $e->getMessage());
    echo AIResponse::error('Internal server error', 500);
}
?>