<?php
/**
 * API обработчики для AJAX запросов
 */

class ApiHandler {
    
    /**
     * Обработка запроса на чтение нескольких файлов
     */
    public static function handleReadMultipleFiles() {
        // Очищаем любой вывод перед отправкой JSON
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        // Устанавливаем заголовки
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
            
            // Читаем JSON тело запроса
            $jsonInput = file_get_contents('php://input');
            if (empty($jsonInput)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Empty request body'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $input = json_decode($jsonInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON: ' . json_last_error_msg()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $files = $input['files'] ?? [];
            
            if (empty($serverName)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Server not specified'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (empty($files) || !is_array($files)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Files list is empty or invalid'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            
            $result = $ssh->readMultipleFiles($files);
            
            // Возвращаем простой объект: ключ = путь, значение = контент
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на запись нескольких файлов
     */
    public static function handleWriteMultipleFiles() {
        // Очищаем любой вывод перед отправкой JSON
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        // Устанавливаем заголовки
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
            
            // Читаем JSON тело запроса
            $jsonInput = file_get_contents('php://input');
            if (empty($jsonInput)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Empty request body'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $input = json_decode($jsonInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON: ' . json_last_error_msg()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $filesData = $input['files'] ?? [];
            
            if (empty($serverName)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Server not specified'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (empty($filesData) || !is_array($filesData)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Files data is empty or invalid'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            
            $result = $ssh->writeMultipleFiles($filesData);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на обработку через нейросеть
     */
    public static function handleAiProcess() {
        // Очищаем любой вывод перед отправкой JSON
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        // Устанавливаем заголовки
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // Читаем JSON тело запроса
            $jsonInput = file_get_contents('php://input');
            if (empty($jsonInput)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Empty request body'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $input = json_decode($jsonInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON: ' . json_last_error_msg()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $jsonData = $input['json_data'] ?? '';
            $prompt = $input['prompt'] ?? '';
            $aiModel = $input['ai_model'] ?? 'default';
            
            if (empty($jsonData)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'JSON data is empty'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (empty($prompt)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Prompt is empty'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Обработка в зависимости от выбранной модели
            if ($aiModel === 'deepseek') {
                $result = self::processWithDeepSeek($jsonData, $prompt);
            } else {
                $result = self::processWithDefaultAI($jsonData, $prompt);
            }
            
            // Проверяем, является ли результат валидным JSON (чтобы можно было использовать для записи файлов)
            $jsonResult = json_decode($result, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Если ответ - валидный JSON, возвращаем его
                $output = $result;
                $isJson = true;
            } else {
                // Иначе возвращаем как есть
                $output = $result;
                $isJson = false;
            }
            
            echo json_encode([
                'success' => true,
                'result' => $output,
                'is_json' => $isJson,
                'model' => $aiModel
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка через стандартную нейросеть
     */
    private static function processWithDefaultAI($jsonData, $prompt) {
        // Подготавливаем запрос для нейросети
        $question = $jsonData . "\n\n" . $prompt;
        
        // Получаем URL API из конфигурации
        $apiUrl = Config::getAiApiUrl();
        
        // Отправляем запрос к нейросети
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['question' => $question]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600);  // 30
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('AI API returned HTTP code: ' . $httpCode . '. Response: ' . $response);
        }
        
        // Парсим ответ (может быть JSON или простой текст)
        $parsedResponse = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($parsedResponse['answer'])) {
            return $parsedResponse['answer'];
        } else if (json_last_error() === JSON_ERROR_NONE && isset($parsedResponse['response'])) {
            return $parsedResponse['response'];
        } else {
            return $response;
        }
    }
    
    /**
     * Обработка через DeepSeek API
     */
    private static function processWithDeepSeek($jsonData, $prompt) {
        $config = Config::getDeepSeekConfig();
        if (!$config) {
            throw new Exception('DeepSeek не настроен в конфигурации');
        }
        
        $apiKey = $config['api_key'];
        $apiUrl = $config['api_url'];
        $model = $config['model'];
        $systemPrompt = $config['system_prompt'];
        $maxTokens = $config['max_tokens'];
        $temperature = $config['temperature'];
        $timeout = $config['timeout'];
        $topP = $config['top_p'];
        
        if (empty($apiKey)) {
            throw new Exception('DeepSeek API ключ не настроен в конфигурации');
        }
        
        // Формируем промпт для DeepSeek
        $fullPrompt = "У меня есть JSON с содержимым файлов:\n\n" . $jsonData . "\n\n" . $prompt . "\n\nВерни результат в виде валидного JSON, где ключи - пути к файлам, а значения - измененное содержимое этих файлов.";
        
        // Подготавливаем данные для запроса
        $requestData = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $fullPrompt
                ]
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'top_p' => $topP
        ];
        
        // Отправляем запрос к DeepSeek API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . 'application/json',
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('DeepSeek CURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('DeepSeek API returned HTTP code: ' . $httpCode . '. Response: ' . $response);
        }
        
        $parsedResponse = json_decode($response, true);
        
        if (!isset($parsedResponse['choices'][0]['message']['content'])) {
            if (isset($parsedResponse['error']['message'])) {
                throw new Exception('DeepSeek API error: ' . $parsedResponse['error']['message']);
            }
            throw new Exception('Invalid DeepSeek response format');
        }
        
        return $parsedResponse['choices'][0]['message']['content'];
    }
    
    /**
     * Обработка запроса на получение дерева файлов
     */
    public static function handleGetTree($serverName, $path, $startPath) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            $listing = $ssh->listDirectory($path);
            
            // Формируем данные для дерева
            $tree = [
                'path' => $path,
                'name' => basename($path) ?: $path,
                'type' => 'directory',
                'children' => []
            ];
            
            $lines = explode("\n", $listing['listing']);
            foreach ($lines as $line) {
                if (empty($line) || strpos($line, 'total ') === 0) continue;
                
                $parts = preg_split('/\s+/', $line, 9);
                if (count($parts) < 9) continue;
                
                $perms = $parts[0];
                $isDir = $perms[0] === 'd';
                $name = $parts[8];
                
                if ($name === '.' || $name === '..') continue;
                
                // Фильтр: скрыть файлы/папки начинающиеся с точки
                if (strpos($name, '.') === 0) continue;
                
                // Формируем правильный путь
                $fullPath = ($path === '/') ? '/' . $name : $path . '/' . $name;
                
                if ($isDir) {
                    $tree['children'][] = [
                        'path' => $fullPath,
                        'name' => $name,
                        'type' => 'directory',
                        'children' => []
                    ];
                } else {
                    $tree['children'][] = [
                        'path' => $fullPath,
                        'name' => $name,
                        'type' => 'file',
                        'size' => $parts[4],
                        'icon' => getFileIcon($name, false)
                    ];
                }
            }
            
            // Сортировка: сначала папки, потом файлы, всё по алфавиту
            usort($tree['children'], function($a, $b) {
                if ($a['type'] !== $b['type']) {
                    return $a['type'] === 'directory' ? -1 : 1;
                }
                return strcasecmp($a['name'], $b['name']);
            });
            
            echo json_encode($tree, JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на получение списка всех файлов
     */
    public static function handleListAllFilesFiltered($serverName, $path) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            
            $files = $ssh->listAllFilesFiltered($path);
            
            echo json_encode([
                'success' => true,
                'files' => $files
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на просмотр файла
     */
    public static function handleViewFile($serverName, $filePath) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            
            $fileData = $ssh->readFile($filePath);
            $html = Renderer::renderFileContent($fileData, $serverName);
            echo $html;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo '<div class="error">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на просмотр директории
     */
    public static function handleBrowse($serverName, $path) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            $listing = $ssh->listDirectory($path);
            
            $html = Renderer::renderFileBrowser($listing, $serverName);
            echo $html;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo '<div class="error">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        exit;
    }
}
