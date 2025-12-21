<?php
// config.php - Конфигурация SSH серверов

$servers = [
    'server-name' => [
        'host' => '',  // IP или домен сервера
        'port' => 22,  // Порт SSH (по умолчанию 22)
        'user' => '',  // Имя пользователя SSH
        
        // Выберите один из методов аутентификации:
        
        // 1. Аутентификация по ключу (рекомендуется):
        'key_path' => '/home/user/.ssh/id_rsa', // Путь к приватному ключу
        
        // Опционально, если ключ защищен паролем:
        'key_passphrase' => '',
        
        // ИЛИ
        
        // 2. Аутентификация по паролю:
        // 'password' => 'ваш_пароль',
    ],
    
    'backup-server' => [
        'host' => 'backup.example.com',
        'port' => 2222,
        'user' => 'backup_user',
        'password' => 'your_password_here'
    ],
    
    'prod' => [
        'host' => 'production.server.com',
        'user' => 'deploy',
        'key_path' => '/var/www/.ssh/prod_key'
    ]
];

// Дополнительные настройки
// define('DEBUG_MODE', true); // Включить отладку
