<?php
// Конфигурация SSH серверов
$servers = [
    'fvds30' => [
        'host' => '192.168.1.100',
        'port' => 22,
        'user' => 'username',
        // Выберите один из методов:
        'password' => 'ваш_пароль',
        // ИЛИ:
        // 'key_path' => '/home/user/.ssh/id_rsa',
        // 'key_passphrase' => '' // если ключ защищен паролем
    ],
    'backup-server' => [
        'host' => 'backup.example.com',
        'port' => 2222,
        'user' => 'backup_user',
        'password' => 'ваш_пароль'
    ]
];




// Стартовая папка
define('START_PATH', '/var');
