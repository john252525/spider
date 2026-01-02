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

// API для нейросети
define('AI_API_URL', 'https://...');  // POST {"question":"TEXT"}

// Настройки DeepSeek API
// Получите API ключ на platform.deepseek.com
define('DEEPSEEK_API_KEY', 'your-deepseek-api-key-here');
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions');
define('DEEPSEEK_MODEL', 'deepseek-chat');  // Доступные модели: deepseek-chat, deepseek-coder

// Системный промпт для DeepSeek
define('DEEPSEEK_SYSTEM_PROMPT', 'Ты - помощник для обработки файлов. Ты получаешь JSON с содержимым файлов и инструкцию по их изменению. Ты всегда возвращаешь результат в виде валидного JSON, где ключи - пути к файлам, а значения - измененное содержимое этих файлов.');

// Параметры запроса
// Лимит токенов (максимально 4096 для deepseek-chat, 16384 для deepseek-coder)
define('DEEPSEEK_MAX_TOKENS', 4000);

// Температура (0-1, чем меньше, тем более детерминированный ответ)
define('DEEPSEEK_TEMPERATURE', 0.1);

// Таймаут запроса в секундах
define('DEEPSEEK_TIMEOUT', 120);  // 2 минуты для сложных обработок

// Дополнительные настройки
define('DEEPSEEK_TOP_P', 0.9);      // Параметр top_p для выборки
