<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);




// config.php - создайте этот файл и настройте доступы SSH
// <?php
// define('SSH_HOST', 'ваш_сервер');
// define('SSH_PORT', 22);
// define('SSH_USER', 'ваш_пользователь');
// define('SSH_KEY_PATH', '/путь/к/ssh/key'); // или использовать пароль
// define('SSH_PASSWORD', 'ваш_пароль'); // если используете пароль

session_start();

// Функция для выполнения SSH команды
function executeSSHCommand($serverName, $path) {
    // Подключаем конфигурацию
    if (!file_exists('config.php')) {
        return "Ошибка: Создайте файл config.php с настройками SSH";
    }
    
    require_once 'config.php';
    
    // Проверяем и чистим путь
    $path = trim($path);
    if (empty($path)) {
        $path = '.';
    }
    
    // Убираем слеш в конце, если есть
    $path = rtrim($path, '/');
    
    try {
        // Создаем соединение SSH2
        $connection = ssh2_connect(SSH_HOST, SSH_PORT);
        if (!$connection) {
            return "Ошибка: Не удалось подключиться к серверу";
        }
        
        // Аутентификация (используйте один из методов)
        if (defined('SSH_KEY_PATH') && SSH_KEY_PATH) {
            // Аутентификация по ключу
            if (!ssh2_auth_pubkey_file($connection, SSH_USER, SSH_KEY_PATH . '.pub', SSH_KEY_PATH)) {
                return "Ошибка: Неверный SSH ключ";
            }
        } elseif (defined('SSH_PASSWORD') && SSH_PASSWORD) {
            // Аутентификация по паролю
            if (!ssh2_auth_password($connection, SSH_USER, SSH_PASSWORD)) {
                return "Ошибка: Неверный логин или пароль";
            }
        } else {
            return "Ошибка: Настройте метод аутентификации в config.php";
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
        
        ssh2_disconnect($connection);
        
        return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        
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

// Получаем последние значения из сессии
$last_server = $_SESSION['last_server'] ?? 'fvds30';
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
        
        .examples {
            margin-top: 30px;
            background: #edf2f7;
            padding: 20px;
            border-radius: 10px;
        }
        
        .examples h3 {
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .example-item {
            background: white;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid #667eea;
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
            <form method="POST" action="">
                <div class="input-section">
                    <label class="input-label">Введите сервер и путь:</label>
                    <div class="input-group">
                        <input type="text" 
                               name="path_input" 
                               class="path-input" 
                               value="<?php echo htmlspecialchars("{$last_server} {$last_path}", ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="fvds30 /var/www/html"
                               required>
                        <button type="submit" class="btn">Показать файлы</button>
                    </div>
                    <div class="format-hint">
                        Формат: <strong>сервер путь</strong> (разделитель: пробел или двоеточие)
                    </div>
                </div>
            </form>
            
            <?php if ($result): ?>
                <div class="result-section">
                    <div class="result-title">Содержимое директории:</div>
                    <div class="output"><?php echo $result; ?></div>
                </div>
            <?php endif; ?>
            
            <div class="examples">
                <h3>Примеры использования:</h3>
                <div class="example-item"><strong>fvds30 /var/www/html</strong> - корень веб-сервера</div>
                <div class="example-item"><strong>server1:/home/user/docs/</strong> - с двоеточием и слешем</div>
                <div class="example-item"><strong>prod-server /etc/nginx</strong> - конфигурация nginx</div>
                <div class="example-item"><strong>backup ../logs</strong> - на уровень выше</div>
            </div>
        </div>
        
        <div class="footer">
            © <?php echo date('Y'); ?> SSH File Browser | Используйте с умом
        </div>
    </div>
</body>
</html>
