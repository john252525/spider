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
}
