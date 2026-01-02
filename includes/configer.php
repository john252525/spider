<?php
// Единая точка загрузки конфигурации
class Config {
    private static $config = null;
    
    public static function load() {
        if (self::$config === null) {
            if (!file_exists(__DIR__ . '/../config.php')) {
                throw new Exception('Файл config.php не найден');
            }
            
            require_once __DIR__ . '/../config.php';
            
            if (!isset($servers) || !is_array($servers)) {
                throw new Exception('Неверная конфигурация: массив $servers не найден');
            }
            
            self::$config = [
                'servers' => $servers
            ];
            
            // Добавляем константы в конфиг
            if (defined('START_PATH')) {
                self::$config['start_path'] = START_PATH;
            }
            
            if (defined('AI_API_URL')) {
                self::$config['ai_api_url'] = AI_API_URL;
            }
            
            // DeepSeek настройки
            if (defined('DEEPSEEK_API_KEY')) {
                self::$config['deepseek'] = [
                    'api_key' => DEEPSEEK_API_KEY,
                    'api_url' => defined('DEEPSEEK_API_URL') ? DEEPSEEK_API_URL : 'https://api.deepseek.com/v1/chat/completions',
                    'model' => defined('DEEPSEEK_MODEL') ? DEEPSEEK_MODEL : 'deepseek-chat',
                    'system_prompt' => defined('DEEPSEEK_SYSTEM_PROMPT') ? DEEPSEEK_SYSTEM_PROMPT : 'Ты - помощник для обработки файлов. Ты получаешь JSON с содержимым файлов и инструкцию по их изменению. Ты всегда возвращаешь результат в виде валидного JSON, где ключи - пути к файлам, а значения - измененное содержимое этих файлов.',
                    'max_tokens' => defined('DEEPSEEK_MAX_TOKENS') ? DEEPSEEK_MAX_TOKENS : 4000,
                    'temperature' => defined('DEEPSEEK_TEMPERATURE') ? DEEPSEEK_TEMPERATURE : 0.1,
                    'timeout' => defined('DEEPSEEK_TIMEOUT') ? DEEPSEEK_TIMEOUT : 120,
                    'top_p' => defined('DEEPSEEK_TOP_P') ? DEEPSEEK_TOP_P : 0.9
                ];
            }
        }
        
        return self::$config;
    }
    
    public static function getServer($name) {
        $config = self::load();
        return $config['servers'][$name] ?? null;
    }
    
    public static function getServersList() {
        $config = self::load();
        return array_keys($config['servers']);
    }
    
    public static function getAiApiUrl() {
        $config = self::load();
        return $config['ai_api_url'] ?? 'https://api22222.apitter.com/v1/ai/api.php';
    }
    
    public static function getDeepSeekConfig() {
        $config = self::load();
        return $config['deepseek'] ?? null;
    }
    
    public static function getDeepSeekApiKey() {
        $config = self::getDeepSeekConfig();
        return $config['api_key'] ?? '';
    }
    
    public static function getDeepSeekApiUrl() {
        $config = self::getDeepSeekConfig();
        return $config['api_url'] ?? 'https://api.deepseek.com/v1/chat/completions';
    }
    
    public static function getDeepSeekModel() {
        $config = self::getDeepSeekConfig();
        return $config['model'] ?? 'deepseek-chat';
    }
    
    public static function getDeepSeekSystemPrompt() {
        $config = self::getDeepSeekConfig();
        return $config['system_prompt'] ?? 'Ты - помощник для обработки файлов. Ты получаешь JSON с содержимым файлов и инструкцию по их изменению. Ты всегда возвращаешь результат в виде валидного JSON, где ключи - пути к файлам, а значения - измененное содержимое этих файлов.';
    }
    
    public static function getDeepSeekMaxTokens() {
        $config = self::getDeepSeekConfig();
        return $config['max_tokens'] ?? 4000;
    }
    
    public static function getDeepSeekTemperature() {
        $config = self::getDeepSeekConfig();
        return $config['temperature'] ?? 0.1;
    }
    
    public static function getDeepSeekTimeout() {
        $config = self::getDeepSeekConfig();
        return $config['timeout'] ?? 120;
    }
    
    public static function getDeepSeekTopP() {
        $config = self::getDeepSeekConfig();
        return $config['top_p'] ?? 0.9;
    }
}
