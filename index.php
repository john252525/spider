<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);




session_start();

// Функция для выполнения SSH команды
function executeSSHCommand($serverName, $path) {
    // Подключаем конфигурацию
    if (!file_exists('config.php')) {
        return "Ошибка: Создайте файл config.php с настройками SSH";
    }
    
    require 'config.php';
    
    // Проверяем существование указанного сервера в конфиге
    if (!isset($servers[$serverName])) {
        return "Ошибка: Сервер '{$serverName}' не найден в конфигурации";
    }
    
    $server = $servers[$serverName];
    
    // Проверяем обязательные параметры
    if (empty($server['host']) || empty($server['user'])) {
        return "Ошибка: Неверная конфигурация для сервера '{$serverName}'";
    }
    
    // Проверяем и чистим путь
    $path = trim($path);
    if (empty($path)) {
        $path = '.';
    }
    
    // Убираем слеш в конце, если есть
    $path = rtrim($path, '/');
    
    try {
        // Порт по умолчанию
        $port = $server['port'] ?? 22;
        
        // Создаем соединение SSH2
        $connection = ssh2_connect($server['host'], $port);
        if (!$connection) {
            return "Ошибка: Не удалось подключиться к серверу {$server['host']}:{$port}";
        }
        
        // Аутентификация
        $authenticated = false;
        
        if (isset($server['key_path']) && $server['key_path']) {
            // Аутентификация по ключу
            $authenticated = ssh2_auth_pubkey_file(
                $connection, 
                $server['user'], 
                $server['key_path'] . '.pub', 
                $server['key_path'],
                $server['key_passphrase'] ?? ''
            );
        } elseif (isset($server['password']) && $server['password']) {
            // Аутентификация по паролю
            $authenticated = ssh2_auth_password($connection, $server['user'], $server['password']);
        }
        
        if (!$authenticated) {
            return "Ошибка: Не удалось авторизоваться на сервере";
        }
        
        // Выполняем команду для получения списка файлов
        $command = "ls -la --group-directories-first " . escapeshellarg($path) . " 2>&1";
        $stream = ssh2_exec($connection, $command);
        
        if (!$stream) {
            return "Ошибка: Не удалось выполнить команду";
        }
        
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        
        // Если команда не сработала, пробуем альтернативную
        if (strpos($output, 'No such file or directory') !== false) {
            $command = "cd " . escapeshellarg($path) . " && ls -la --group-directories-first 2>&1";
            $stream = ssh2_exec($connection, $command);
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);
        }
        
        // Дополнительная информация о директории
        $command2 = "cd " . escapeshellarg($path) . " && pwd 2>&1";
        $stream2 = ssh2_exec($connection, $command2);
        stream_set_blocking($stream2, true);
        $pwd = trim(stream_get_contents($stream2));
        fclose($stream2);
        
        ssh2_disconnect($connection);
        
        $result = "Сервер: {$serverName} ({$server['host']})\n";
        $result .= "Текущая директория: {$pwd}\n";
        $result .= "Пользователь: {$server['user']}\n";
        $result .= str_repeat("-", 80) . "\n\n";
        $result .= htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        
        return $result;
        
    } catch (Exception $e) {
        return "Ошибка: " . $e->getMessage();
    }
}

// Обработка формы
$result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['path_input'])) {
    $input = trim($_POST['path_input']);
    
    if (!empty($input)) {
        // Парсим ввод: разделяем сервер и путь
        $parts = preg_split('/[\s:]+/', $input, 2);
        
        if (count($parts) >= 2) {
            $serverName = trim($parts[0]);
            $path = trim($parts[1]);
            
            // Сохраняем в сессию для удобства
            $_SESSION['last_server'] = $serverName;
            $_SESSION['last_path'] = $path;
            
            $result = executeSSHCommand($serverName, $path);
        } else {
            $result = "Ошибка: Введите данные в формате 'сервер путь'";
        }
    }
}

// Получаем список серверов из конфига для подсказок
$availableServers = [];
if (file_exists('config.php')) {
    require 'config.php';
    $availableServers = array_keys($servers);
}

// Получаем последние значения из сессии
$last_server = $_SESSION['last_server'] ?? ($availableServers[0] ?? '');
$last_path = $_SESSION['last_path'] ?? '/var/www/html';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH File Browser</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #cbd5e0;
            font-size: 1.1rem;
        }
        
        .content {
            padding: 30px;
        }
        
        .input-section {
            margin-bottom: 30px;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .input-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }
        
        .format-hint {
            font-size: 0.9rem;
            color: #718096;
            margin-top: 5px;
        }
        
        .path-input {
            flex: 1;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .path-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .result-section {
            background: #f7fafc;
            border-radius: 10px;
            padding: 20px;
            min-height: 200px;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .result-title {
            font-size: 1.2rem;
            color: #4a5568;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .output {
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .servers-list {
            margin-top: 20px;
            padding: 15px;
            background: #edf2f7;
            border-radius: 10px;
        }
        
        .servers-list h3 {
            color: #4a5568;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .servers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .server-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .server-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .server-name {
            font-weight: bold;
            color: #4a5568;
            margin-bottom: 5px;
        }
        
        .server-details {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-size: 0.9rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .error {
            color: #e53e3e;
            background: #fed7d7;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e53e3e;
        }
        
        .success {
            color: #38a169;
            background: #c6f6d5;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #38a169;
        }
        
        .info {
            color: #3182ce;
            background: #bee3f8;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #3182ce;
        }
        
        @media (max-width: 768px) {
            .input-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .servers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SSH File Browser</h1>
            <div class="subtitle">Просмотр структуры файлов через SSH</div>
        </div>
        
        <div class="content">
            <form method="POST" action="" id="sshForm">
                <div class="input-section">
                    <label class="input-label">Введите сервер и путь:</label>
                    <div class="input-group">
                        <input type="text" 
                               name="path_input" 
                               class="path-input" 
                               value="<?php echo htmlspecialchars("{$last_server} {$last_path}", ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="fvds30 /var/www/html"
                               required
                               id="pathInput">
                        <button type="submit" class="btn">Показать файлы</button>
                    </div>
                    <div class="format-hint">
                        Формат: <strong>сервер путь</strong> (разделитель: пробел или двоеточие)
                    </div>
                </div>
            </form>
            
            <?php if (!file_exists('config.php')): ?>
                <div class="error">
                    <strong>Внимание:</strong> Файл config.php не найден. Создайте его по примеру ниже:
                    <pre style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 5px; overflow-x: auto;">
&lt;?php
$servers = [
    'fvds30' => [
        'host' => '192.168.1.100',
        'port' => 22,
        'user' => 'username',
        'key_path' => '/путь/к/ssh/key' // без .pub
    ],
    'backup' => [
        'host' => 'backup.example.com',
        'port' => 2222,
        'user' => 'user',
        'password' => 'ваш_пароль'
    ]
];
                    </pre>
                </div>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <div class="result-section">
                    <div class="result-title">Содержимое директории:</div>
                    <div class="output"><?php echo $result; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($availableServers)): ?>
                <div class="servers-list">
                    <h3>📡 Доступные серверы:</h3>
                    <div class="servers-grid" id="serversGrid">
                        <?php foreach ($availableServers as $serverName): ?>
                            <?php if (file_exists('config.php')): ?>
                                <?php 
                                require 'config.php';
                                $server = $servers[$serverName] ?? [];
                                ?>
                                <div class="server-item" onclick="selectServer('<?php echo $serverName; ?>')">
                                    <div class="server-name"><?php echo htmlspecialchars($serverName); ?></div>
                                    <div class="server-details">
                                        <?php echo htmlspecialchars($server['host'] ?? 'не указан'); ?>
                                        <?php if (isset($server['port']) && $server['port'] != 22): ?>
                                            :<?php echo $server['port']; ?>
                                        <?php endif; ?>
                                        <br>
                                        Пользователь: <?php echo htmlspecialchars($server['user'] ?? 'не указан'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (file_exists('config.php')): ?>
                <div class="info">
                    Серверы не настроены в config.php. Добавьте серверы в массив $servers.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            © <?php echo date('Y'); ?> SSH File Browser | Используйте с умом
        </div>
    </div>
    
    <script>
        function selectServer(serverName) {
            const input = document.getElementById('pathInput');
            const currentValue = input.value.trim();
            
            // Если уже есть путь, оставляем его
            let newPath = '/var/www/html';
            if (currentValue) {
                const parts = currentValue.split(/[\s:]+/);
                if (parts.length >= 2) {
                    newPath = parts.slice(1).join(' ');
                }
            }
            
            input.value = serverName + ' ' + newPath;
            input.focus();
        }
        
        // Автофокус на поле ввода
        document.getElementById('pathInput')?.focus();
        
        // Обработка Enter в поле ввода
        document.getElementById('pathInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('sshForm').submit();
            }
        });
    </script>
</body>
</html>
