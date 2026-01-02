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
}
