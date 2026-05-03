<?php
/**
 * OpenRouter API Wrapper Class
 * Compatible with OpenAI API
 *
 * @package AI Integration
 * @version 1.0
 */

class OpenRouterAPI {
    private $link; // Database connection
    private $config;
    private $models;
    private $errors;
    private $last_response;
    private $last_usage;
    private $last_model;

    /**
     * Constructor
     */
    public function __construct($db_connection = null) {
        global $link;
        $this->link = $db_connection ?? $link;

        // Check if cURL is available
        if (!function_exists('curl_init')) {
            throw new Exception('خطأ: امتداد cURL غير مفعل في PHP. يرجى تفعيل cURL لاستخدام ميزات الذكاء الاصطناعي. | Error: cURL extension is not enabled in PHP. Please enable cURL to use AI features.');
        }

        // Load configuration
        $config_data = require(dirname(__FILE__) . '/../config/api_config.hnt');
        $this->config = $config_data['config'];
        $this->models = $config_data['models'];
        $this->errors = $config_data['errors'];

        // Validate API key
        if (empty($this->config['api_key'])) {
            throw new Exception($this->errors['api_key_missing']);
        }
    }

    /**
     * Get user's preferred AI model from database
     *
     * @return string Model ID (user's preference or system default)
     */
    private function getUserPreferredModel() {
        $userid = $_SESSION['useridv'] ?? 0;

        if ($userid <= 0) {
            return $this->config['default_model'];
        }

        // Query user preferences table
        $sql = "SELECT preferred_model FROM ai_user_preferences
                WHERE userid = ?
                LIMIT 1";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter getUserPreferredModel prepare failed: " . mysqli_error($this->link));
            return $this->config['default_model'];
        }

        mysqli_stmt_bind_param($stmt, 'i', $userid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && !empty($row['preferred_model'])) {
            return $row['preferred_model'];
        }

        return $this->config['default_model'];
    }

    /**
     * Build model candidates using configured preferences and configured fallbacks.
     *
     * @param string|null $preferredModel
     * @return array
     */
    private function getModelCandidates($preferredModel = null) {
        $candidates = [];

        $addCandidate = function($model) use (&$candidates) {
            $model = trim((string)$model);
            if ($model !== '' && !in_array($model, $candidates, true)) {
                $candidates[] = $model;
            }
        };

        $addCandidate($preferredModel ?: $this->getUserPreferredModel());
        $addCandidate($this->config['default_model'] ?? '');
        $addCandidate($this->config['premium_model'] ?? '');

        foreach ($this->models as $modelConfig) {
            if (isset($modelConfig['id'])) {
                $addCandidate($modelConfig['id']);
            }
        }

        return $candidates;
    }

    /**
     * Determine whether a failed request should retry with a fallback model.
     *
     * @param Exception $exception
     * @return bool
     */
    private function shouldRetryWithFallback($exception) {
        $message = $exception->getMessage();

        return strpos($message, 'HTTP 429') !== false
            || stripos($message, 'rate-limited upstream') !== false
            || stripos($message, 'temporarily rate-limited') !== false;
    }

    /**
     * Send a chat completion request for a specific model.
     *
     * @param array $messages
     * @param array $options
     * @return string
     */
    private function sendChatCompletionRequest($messages, $options) {
        $data = [
            'model' => $options['model'],
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature']
        ];

        $response = $this->makeRequest('/chat/completions', $data);

        $text = $response['choices'][0]['message']['content'] ?? '';
        $this->last_usage = $response['usage'] ?? [];
        $this->last_model = $options['model'];

        return $text;
    }

    /**
     * Complete text using AI
     *
     * @param string $prompt The prompt text
     * @param array $options Additional options
     * @return string|false Generated text or false on error
     */
    public function complete($prompt, $options = []) {
        // Merge with defaults (use user's preferred model if not explicitly specified)
        $default_model = isset($options['model']) ? $options['model'] : $this->getUserPreferredModel();

        $options = array_merge([
            'model' => $default_model,
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => $this->config['temperature'],
            'cache' => $this->config['cache_enabled']
        ], $options);

        // Check cache first
        if ($options['cache']) {
            $cached = $this->getFromCache($prompt, $options['model']);
            if ($cached) {
                return $cached;
            }
        }

        // Check rate limits
        if (!$this->checkRateLimit()) {
            throw new Exception($this->errors['rate_limit_exceeded']);
        }

        // Check budget
        if (!$this->checkBudget()) {
            throw new Exception($this->errors['budget_exceeded']);
        }

        $messages = [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        $candidateModels = $this->getModelCandidates($options['model']);
        $lastException = null;
        $text = '';

        foreach ($candidateModels as $index => $candidateModel) {
            $options['model'] = $candidateModel;

            if ($options['cache']) {
                $cached = $this->getFromCache($prompt, $options['model']);
                if ($cached) {
                    $this->last_model = $options['model'];
                    $this->last_usage = [];
                    return $cached;
                }
            }

            try {
                $text = $this->sendChatCompletionRequest($messages, $options);
                break;
            } catch (Exception $e) {
                $lastException = $e;

                if ($index < count($candidateModels) - 1 && $this->shouldRetryWithFallback($e)) {
                    continue;
                }

                throw $e;
            }
        }

        if ($text === '' && $lastException) {
            throw $lastException;
        }

        // Log request
        $this->logRequest($prompt, $text, $options['model'], $this->last_usage);

        // Cache result
        if ($options['cache'] && !empty($text)) {
            $this->saveToCache($prompt, $text, $options['model']);
        }

        return $text;
    }

    /**
     * Chat completion (multiple messages)
     *
     * @param array $messages Array of messages
     * @param array $options Additional options
     * @return string|false Response text
     */
    public function chat($messages, $options = []) {
        // Use user's preferred model if not explicitly specified
        $default_model = isset($options['model']) ? $options['model'] : $this->getUserPreferredModel();

        $options = array_merge([
            'model' => $default_model,
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => $this->config['temperature']
        ], $options);

        $candidateModels = $this->getModelCandidates($options['model']);
        $lastException = null;
        $text = '';

        foreach ($candidateModels as $index => $candidateModel) {
            $options['model'] = $candidateModel;

            try {
                $text = $this->sendChatCompletionRequest($messages, $options);
                break;
            } catch (Exception $e) {
                $lastException = $e;

                if ($index < count($candidateModels) - 1 && $this->shouldRetryWithFallback($e)) {
                    continue;
                }

                throw $e;
            }
        }

        if ($text === '' && $lastException) {
            throw $lastException;
        }

        return $text;
    }

    /**
     * Make HTTP request to OpenRouter API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false Response data or false on error
     */
    private function makeRequest($endpoint, $data) {
        $url = $this->config['base_url'] . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key'],
            'HTTP-Referer: ' . $this->config['site_url'],
            'X-Title: ' . $this->config['site_name']
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);

        // SSL configuration for Windows environments
        // In production, use proper CA bundle: curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        //curl_close($ch);

        if ($error) {
            $error_msg = "خطأ في الاتصال بـ OpenRouter API: " . $error . " | API Connection Error: " . $error;
            error_log("OpenRouter API Error: " . $error);
            throw new Exception($error_msg);
        }

        if ($http_code !== 200) {
            $error_msg = "خطأ من OpenRouter API (HTTP $http_code): $response | API HTTP Error ($http_code): $response";
            error_log("OpenRouter API HTTP Error: " . $http_code . " - " . $response);
            throw new Exception($error_msg);
        }

        $this->last_response = json_decode($response, true);

        if (!$this->last_response) {
            $error_msg = "خطأ: استجابة غير صالحة من API | Invalid JSON Response from API";
            error_log("OpenRouter API Invalid JSON Response: " . $response);
            throw new Exception($error_msg);
        }

        return $this->last_response;
    }

    /**
     * Check rate limits
     *
     * @return bool True if within limits
     */
    private function checkRateLimit() {
        if (!$this->config['rate_limit_enabled']) {
            return true;
        }

        $userid = $_SESSION['useridv'] ?? 0;

        // Check per minute using prepared statement
        $sql = "SELECT COUNT(*) as count FROM ai_usage_log
                WHERE userid = ?
                AND created_date > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter checkRateLimit prepare failed: " . mysqli_error($this->link));
            return true; // Allow on error
        }

        mysqli_stmt_bind_param($stmt, 'i', $userid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['count'] >= $this->config['max_requests_per_minute']) {
            return false;
        }

        return true;
    }

    /**
     * Check budget limits
     *
     * @return bool True if within budget
     */
    private function checkBudget() {
        // Check daily budget using prepared statement
        $sql = "SELECT SUM(cost) as total FROM ai_usage_log
                WHERE DATE(created_date) = CURDATE()";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter checkBudget (daily) prepare failed: " . mysqli_error($this->link));
            return true; // Allow on error
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $daily_cost = floatval($row['total'] ?? 0);

        if ($daily_cost >= $this->config['daily_budget']) {
            return false;
        }

        // Check monthly budget using prepared statement
        $sql = "SELECT SUM(cost) as total FROM ai_usage_log
                WHERE MONTH(created_date) = MONTH(CURDATE())
                AND YEAR(created_date) = YEAR(CURDATE())";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter checkBudget (monthly) prepare failed: " . mysqli_error($this->link));
            return true; // Allow on error
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $monthly_cost = floatval($row['total'] ?? 0);

        if ($monthly_cost >= $this->config['monthly_budget']) {
            return false;
        }

        return true;
    }

    /**
     * Get from cache
     *
     * @param string $prompt Prompt text
     * @param string $model Model ID
     * @return string|false Cached response or false
     */
    private function getFromCache($prompt, $model) {
        $cache_key = md5($prompt . $model);

        // SELECT using prepared statement
        $sql = "SELECT response_text, hit_count FROM ai_cache
                WHERE cache_key = ?
                AND expires_at > NOW()
                LIMIT 1";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter getFromCache (SELECT) prepare failed: " . mysqli_error($this->link));
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $cache_key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            // Increment hit count using prepared statement
            $update_sql = "UPDATE ai_cache
                          SET hit_count = hit_count + 1
                          WHERE cache_key = ?";

            $update_stmt = mysqli_prepare($this->link, $update_sql);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, 's', $cache_key);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }

            return $row['response_text'];
        }

        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Save to cache
     *
     * @param string $prompt Prompt text
     * @param string $response Response text
     * @param string $model Model ID
     */
    private function saveToCache($prompt, $response, $model) {
        $cache_key = md5($prompt . $model);
        $prompt_hash = md5($prompt);
        $expires_at = date('Y-m-d H:i:s', time() + $this->config['cache_duration']);
        $hit_count = 0;

        // INSERT using prepared statement
        $sql = "INSERT INTO ai_cache (cache_key, prompt_hash, response_text, model, expires_at, hit_count)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                response_text = VALUES(response_text),
                expires_at = VALUES(expires_at)";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter saveToCache prepare failed: " . mysqli_error($this->link));
            return;
        }

        mysqli_stmt_bind_param($stmt, 'sssssi', $cache_key, $prompt_hash, $response, $model, $expires_at, $hit_count);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Log API request
     *
     * @param string $prompt Prompt text
     * @param string $response Response text
     * @param string $model Model ID
     * @param array $usage Usage statistics
     */
    private function logRequest($prompt, $response, $model, $usage) {
        if (!$this->config['log_enabled']) {
            return;
        }

        $userid = $_SESSION['useridv'] ?? 0;
        $feature = $_REQUEST['feature'] ?? 'unknown';

        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;
        $total_tokens = $usage['total_tokens'] ?? ($prompt_tokens + $completion_tokens);

        // Calculate cost based on model
        $cost = $this->calculateCost($model, $prompt_tokens, $completion_tokens);
        $response_time = 0;

        // INSERT using prepared statement
        $sql = "INSERT INTO ai_usage_log (userid, feature, model, prompt_tokens, completion_tokens, total_tokens, cost, response_time, created_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter logRequest prepare failed: " . mysqli_error($this->link));
            return;
        }

        mysqli_stmt_bind_param($stmt, 'issiiidd', $userid, $feature, $model, $prompt_tokens, $completion_tokens, $total_tokens, $cost, $response_time);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Calculate cost based on model and tokens
     *
     * @param string $model Model ID
     * @param int $input_tokens Input tokens
     * @param int $output_tokens Output tokens
     * @return float Cost in USD
     */
    private function calculateCost($model, $input_tokens, $output_tokens) {
        // Find model config
        $model_config = null;
        foreach ($this->models as $config) {
            if ($config['id'] === $model) {
                $model_config = $config;
                break;
            }
        }

        if (!$model_config) {
            // Default to GPT-3.5 pricing
            $model_config = $this->models['gpt-3.5-turbo'];
        }

        $input_cost = ($input_tokens / 1000) * $model_config['cost_per_1k_input'];
        $output_cost = ($output_tokens / 1000) * $model_config['cost_per_1k_output'];

        return $input_cost + $output_cost;
    }

    /**
     * Get usage statistics
     *
     * @return array Usage stats
     */
    public function getUsageStats() {
        return $this->last_usage;
    }

    /**
     * Get last response
     *
     * @return array Last API response
     */
    public function getLastResponse() {
        return $this->last_response;
    }


    /**
     * Get last used model
     *
     * @return string Last model ID
     */
    public function getLastModel() {
        return $this->last_model ?? 'unknown';
    }

    /**
     * Get available models
     *
     * @return array Models configuration
     */
    public function getAvailableModels() {
        return $this->models;
    }

    /**
     * Clean expired cache entries
     */
    public function cleanCache() {
        $sql = "DELETE FROM ai_cache WHERE expires_at < NOW()";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter cleanCache prepare failed: " . mysqli_error($this->link));
            return 0;
        }

        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        return $affected;
    }

    /**
     * Get usage summary for period
     *
     * @param string $period 'today', 'week', 'month'
     * @return array Usage summary
     */
    public function getUsageSummary($period = 'today') {
        $where = "DATE(created_date) = CURDATE()";

        if ($period === 'week') {
            $where = "created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where = "MONTH(created_date) = MONTH(CURDATE())
                     AND YEAR(created_date) = YEAR(CURDATE())";
        }

        $sql = "SELECT
                COUNT(*) as total_requests,
                SUM(total_tokens) as total_tokens,
                SUM(cost) as total_cost,
                AVG(response_time) as avg_response_time
                FROM ai_usage_log
                WHERE $where";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("OpenRouter getUsageSummary prepare failed: " . mysqli_error($this->link));
            return [
                'total_requests' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'avg_response_time' => 0
            ];
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $summary = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return $summary;
    }
}
?>
