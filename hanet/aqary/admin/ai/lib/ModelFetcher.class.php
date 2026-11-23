<?php
/**
 * ModelFetcher - Fetches available AI models from OpenRouter API
 *
 * Features:
 * - Fetches all available models with pricing information
 * - Prioritizes free models
 * - Caches model list to minimize API calls
 * - Filters and sorts models by cost
 */

class ModelFetcher {
    private $link;
    private $config;
    private $cache_duration = 86400; // 24 hours

    public function __construct($db_link, $config) {
        $this->link = $db_link;
        $this->config = $config;
    }

    /**
     * Fetch all available models from OpenRouter API
     * Returns cached data if available and not expired
     *
     * @param bool $force_refresh Force refresh from API, ignore cache
     * @return array Array of models with pricing and details
     */
    public function fetchModels($force_refresh = false) {
        // Check cache first unless force refresh
        if (!$force_refresh) {
            $cached = $this->getFromCache();
            if ($cached !== null) {
                return $cached;
            }
        }

        // Fetch from OpenRouter API
        $api_url = $this->config['base_url'] . '/models';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json',
            'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        //curl_close($ch);

        if ($curl_error) {
            throw new Exception("فشل في الاتصال بـ OpenRouter API | Failed to connect to OpenRouter API: " . $curl_error);
        }

        if ($http_code !== 200) {
            throw new Exception("خطأ في استجابة OpenRouter API (كود: $http_code) | OpenRouter API error (code: $http_code)");
        }

        $data = json_decode($response, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new Exception("بنية استجابة غير صحيحة من OpenRouter API | Invalid response structure from OpenRouter API");
        }

        // Process and normalize the models
        $models = $this->processModels($data['data']);

        // Save to cache
        $this->saveToCache($models);

        return $models;
    }

    /**
     * Process raw model data from API into normalized format
     *
     * @param array $raw_models Raw model data from API
     * @return array Processed and sorted models
     */
    private function processModels($raw_models) {
        $processed = [];

        foreach ($raw_models as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) continue;

            // Extract pricing information
            $pricing = $model['pricing'] ?? [];
            $input_cost = floatval($pricing['prompt'] ?? 0);
            $output_cost = floatval($pricing['completion'] ?? 0);

            // Determine if model is free (both input and output cost are 0)
            $is_free = ($input_cost == 0 && $output_cost == 0);

            $processed[] = [
                'id' => $id,
                'name' => $model['name'] ?? $id,
                'description' => $model['description'] ?? '',
                'context_length' => intval($model['context_length'] ?? 0),
                'input_cost_per_1k' => $input_cost,
                'output_cost_per_1k' => $output_cost,
                'is_free' => $is_free,
                'avg_cost' => ($input_cost + $output_cost) / 2, // For sorting
                'created' => $model['created'] ?? 0,
                'architecture' => $model['architecture'] ?? null,
                'top_provider' => $model['top_provider'] ?? null,
            ];
        }

        // Sort: Free models first, then by average cost (low to high)
        usort($processed, function($a, $b) {
            // Free models come first
            if ($a['is_free'] && !$b['is_free']) return -1;
            if (!$a['is_free'] && $b['is_free']) return 1;

            // Then sort by average cost
            if ($a['avg_cost'] != $b['avg_cost']) {
                return $a['avg_cost'] <=> $b['avg_cost'];
            }

            // Finally sort by name
            return strcmp($a['name'], $b['name']);
        });

        return $processed;
    }

    /**
     * Get models from cache if available and not expired
     *
     * @return array|null Cached models or null if not available
     */
    private function getFromCache() {
        $cache_key = 'openrouter_models_list';

        $sql = "SELECT response_text, created_date
                FROM ai_cache
                WHERE cache_key = ?
                AND expires_at > NOW()
                LIMIT 1";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("ModelFetcher getFromCache prepare failed: " . mysqli_error($this->link));
            return null;
        }

        mysqli_stmt_bind_param($stmt, 's', $cache_key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row) {
            $models = json_decode($row['response_text'], true);
            if (is_array($models)) {
                return $models;
            }
        }

        return null;
    }

    /**
     * Save models list to cache
     *
     * @param array $models Models to cache
     */
    private function saveToCache($models) {
        $cache_key = 'openrouter_models_list';
        $prompt_hash = md5($cache_key);
        $response_text = json_encode($models);
        $model = 'openrouter_models_api';
        $expires_at = date('Y-m-d H:i:s', time() + $this->cache_duration);
        $hit_count = 0;

        $sql = "INSERT INTO ai_cache (cache_key, prompt_hash, response_text, model, expires_at, hit_count)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                response_text = VALUES(response_text),
                expires_at = VALUES(expires_at)";

        $stmt = mysqli_prepare($this->link, $sql);
        if (!$stmt) {
            error_log("ModelFetcher saveToCache prepare failed: " . mysqli_error($this->link));
            return;
        }

        mysqli_stmt_bind_param($stmt, 'sssssi', $cache_key, $prompt_hash, $response_text, $model, $expires_at, $hit_count);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Get only free models
     *
     * @param bool $force_refresh Force refresh from API
     * @return array Array of free models only
     */
    public function getFreeModels($force_refresh = false) {
        $all_models = $this->fetchModels($force_refresh);
        return array_filter($all_models, function($model) {
            return $model['is_free'] === true;
        });
    }

    /**
     * Search models by name or ID
     *
     * @param string $query Search query
     * @param bool $force_refresh Force refresh from API
     * @return array Matching models
     */
    public function searchModels($query, $force_refresh = false) {
        $all_models = $this->fetchModels($force_refresh);
        $query = strtolower($query);

        return array_filter($all_models, function($model) use ($query) {
            return stripos($model['id'], $query) !== false ||
                   stripos($model['name'], $query) !== false ||
                   stripos($model['description'], $query) !== false;
        });
    }

    /**
     * Get model details by ID
     *
     * @param string $model_id Model ID to find
     * @param bool $force_refresh Force refresh from API
     * @return array|null Model details or null if not found
     */
    public function getModelById($model_id, $force_refresh = false) {
        foreach ($this->getDeepseekModels() as $model) {
            if ($model['id'] === $model_id) {
                return $model;
            }
        }
        if (empty($this->config['api_key'])) {
            return null; // no OpenRouter key: only DeepSeek models are selectable
        }
        $all_models = $this->fetchModels($force_refresh);

        foreach ($all_models as $model) {
            if ($model['id'] === $model_id) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Get models that support image generation
     *
     * @param bool $force_refresh Force refresh from API
     * @return array Array of image-capable models
     */
    public function getImageModels($force_refresh = false) {
        $all_models = $this->fetchModels($force_refresh);

        return array_filter($all_models, function($model) {
            $desc = strtolower($model['description']);
            $name = strtolower($model['name']);
            $id = strtolower($model['id']);

            // Check if model supports image generation
            return stripos($desc, 'image') !== false ||
                   stripos($name, 'image') !== false ||
                   stripos($id, 'dall-e') !== false ||
                   stripos($id, 'stable-diffusion') !== false ||
                   stripos($name, 'flux') !== false ||
                   stripos($desc, 'vision') !== false;
        });
    }

    /**
     * Get models that support audio/voice
     *
     * @param bool $force_refresh Force refresh from API
     * @return array Array of audio-capable models
     */
    public function getAudioModels($force_refresh = false) {
        $all_models = $this->fetchModels($force_refresh);

        return array_filter($all_models, function($model) {
            $desc = strtolower($model['description']);
            $name = strtolower($model['name']);
            $id = strtolower($model['id']);

            // Check if model supports audio
            return stripos($desc, 'audio') !== false ||
                   stripos($name, 'audio') !== false ||
                   stripos($id, 'audio') !== false ||
                   stripos($desc, 'voice') !== false ||
                   stripos($name, 'whisper') !== false;
        });
    }

    /**
     * Get models suitable for chat (excludes image/audio specialized models)
     *
     * @param bool $force_refresh Force refresh from API
     * @return array Array of chat models
     */
    public function getChatModels($force_refresh = false) {
        // DeepSeek (default provider) first, then OpenRouter's list when its key is configured.
        $deepseek = $this->getDeepseekModels();
        $all_models = [];
        if (!empty($this->config['api_key'])) {
            try {
                $all_models = $this->fetchModels($force_refresh);
            } catch (Exception $e) {
                if (empty($deepseek)) {
                    throw $e;
                }
                error_log('ModelFetcher: OpenRouter list unavailable: ' . $e->getMessage());
            }
        }

        return array_merge($deepseek, array_filter($all_models, function($model) {
            $desc = strtolower($model['description']);
            $name = strtolower($model['name']);
            $id = strtolower($model['id']);

            // Exclude specialized image/audio models
            $is_image = (stripos($id, 'dall-e') !== false ||
                        stripos($id, 'stable-diffusion') !== false ||
                        stripos($name, 'flux') !== false);

            $is_audio_only = (stripos($name, 'whisper') !== false);

            return !$is_image && !$is_audio_only;
        }));
    }

    /**
     * DeepSeek models offered for selection (the default provider's own API, bare model IDs).
     * Listed only when a DeepSeek key is available.
     *
     * @return array Models in the same shape as processModels()
     */
    public function getDeepseekModels() {
        if (empty($this->config['deepseek_api_key'])) {
            return [];
        }
        $models = [
            ['deepseek-flash', 'DeepSeek Flash (افتراضي)', 'النموذج الافتراضي: سريع واقتصادي ويدعم العربية', 0.00028, 0.00042],
            ['deepseek-v4-pro', 'DeepSeek V4 Pro', 'للتحليل والتقارير المعقدة', 0.0011, 0.0022],
        ];
        $result = [];
        foreach ($models as $m) {
            $result[] = [
                'id' => $m[0],
                'name' => $m[1],
                'description' => $m[2],
                'context_length' => 128000,
                'input_cost_per_1k' => $m[3],
                'output_cost_per_1k' => $m[4],
                'is_free' => false,
                'avg_cost' => ($m[3] + $m[4]) / 2,
                'created' => 0,
                'architecture' => null,
                'top_provider' => ['name' => 'DeepSeek'],
            ];
        }
        return $result;
    }
}
