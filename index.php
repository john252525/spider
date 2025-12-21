<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);




session_start();

// Подключаем модули
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ssh.php';
require_once __DIR__ . '/includes/utils.php';

// Инициализация
$result = '';
$currentView = 'browser'; // browser, file, error
$fileContent = '';
$fileInfo = [];
$browserData = [];

// Обработка запросов
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'browse':
                    $serverName = $_POST['server'] ?? '';
                    $path = $_POST['path'] ?? '/';
                    
                    $ssh = new SSHManager($serverName);
                    $ssh->connect();
                    $browserData = $ssh->listDirectory($path);
                    
                    $_SESSION['current_server'] = $serverName;
                    $_SESSION['current_path'] = $browserData['current_path'];
                    
                    $result = "Директория: " . $browserData['current_path'];
                    break;
                    
                case 'view_file':
                    $serverName = $_SESSION['current_server'] ?? '';
                    $filePath = $_POST['file_path'] ?? '';
                    
                    $ssh = new SSHManager($serverName);
                    $ssh->connect();
                    
                    $fileData = $ssh->readFile($filePath);
                    $fileInfo = $ssh->getFileInfo($filePath);
                    
                    if ($fileData['type'] === 'text') {
                        $currentView = 'file';
                        $fileContent = $fileData['content'];
                    } else {
                        $currentView = 'file_info';
                        $fileContent = null;
                    }
                    
                    $_SESSION['current_file'] = $filePath;
                    break;
                    
                case 'back':
                    $serverName = $_SESSION['current_server'] ?? '';
                    $currentPath = $_SESSION['current_path'] ?? '/';
                    $parentDir = dirname($currentPath);
                    
                    $ssh = new SSHManager($serverName);
                    $ssh->connect();
                    $browserData = $ssh->listDirectory($parentDir);
                    
                    $_SESSION['current_path'] = $browserData['current_path'];
                    $result = "Перешли в родительскую директорию";
                    break;
            }
        }
    } elseif (isset($_GET['action'])) {
        // Обработка GET запросов (для ссылок)
        $serverName = $_SESSION['current_server'] ?? $_GET['server'] ?? '';
        $path = $_GET['path'] ?? '/';
        
        if ($serverName) {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            $browserData = $ssh->listDirectory($path);
            
            $_SESSION['current_server'] = $serverName;
            $_SESSION['current_path'] = $browserData['current_path'];
        }
    }
    
} catch (Exception $e) {
    $currentView = 'error';
    $result = "Ошибка: " . $e->getMessage();
}

// Получаем список серверов
$availableServers = [];
try {
    $availableServers = Config::getServersList();
} catch (Exception $e) {
    $configError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH File Browser Pro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .header { background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .nav { background: #2d3748; padding: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
        .nav-btn { background: #4a5568; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background 0.3s; }
        .nav-btn:hover { background: #667eea; }
        .content { padding: 30px; display: grid; grid-template-columns: 300px 1fr; gap: 30px; }
        .sidebar { background: #f7fafc; border-radius: 10px; padding: 20px; }
        .main-content { background: #f7fafc; border-radius: 10px; padding: 20px; }
        
        /* Стили для файлового менеджера */
        .file-list { margin-top: 20px; }
        .file-item { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #e2e8f0; transition: background 0.3s; }
        .file-item:hover { background: #edf2f7; }
        .file-icon { font-size: 24px; margin-right: 10px; }
        .file-name { flex: 1; }
        .file-size { color: #718096; font-size: 0.9rem; margin-right: 10px; }
        .file-actions button { margin-left: 5px; padding: 3px 8px; font-size: 0.8rem; }
        
        /* Стили для просмотра файлов */
        .file-content { background: #1a202c; color: #cbd5e0; padding: 20px; border-radius: 5px; font-family: 'Courier New', monospace; white-space: pre-wrap; overflow-x: auto; max-height: 500px; }
        .file-info { background: #edf2f7; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .info-row { display: flex; margin-bottom: 5px; }
        .info-label { font-weight: bold; width: 150px; color: #4a5568; }
        .info-value { color: #2d3748; }
        
        /* Хлебные крошки */
        .breadcrumbs { background: #e2e8f0; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-family: monospace; }
        .breadcrumb-item { color: #667eea; cursor: pointer; }
        .breadcrumb-item:hover { text-decoration: underline; }
        
        /* Форма выбора сервера */
        .server-select { display: flex; gap: 10px; margin-bottom: 20px; }
        .server-select select, .server-select input { flex: 1; padding: 10px; border: 2px solid #e2e8f0; border-radius: 5px; }
        .server-select button { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; }
        
        /* Список серверов */
        .servers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 20px; }
        .server-card { background: white; padding: 15px; border-radius: 8px; border: 2px solid #e2e8f0; cursor: pointer; transition: all 0.3s; }
        .server-card:hover { border-color: #667eea; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        /* Сообщения */
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #fed7d7; color: #e53e3e; border-left: 4px solid #e53e3e; }
        .success { background: #c6f6d5; color: #38a169; border-left: 4px solid #38a169; }
        .info { background: #bee3f8; color: #3182ce; border-left: 4px solid #3182ce; }
        
        @media (max-width: 1024px) {
            .content { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SSH File Browser Pro</h1>
            <div>Просмотр и навигация по файлам через SSH</div>
        </div>
        
        <?php if (isset($configError)): ?>
            <div class="message error">
                <strong>Ошибка конфигурации:</strong> <?php echo escapeOutput($configError); ?>
                <div style="margin-top: 10px; padding: 10px; background: white; border-radius: 5px;">
                    Создайте файл <strong>config.php</strong> в корне проекта:
                    <pre style="margin-top: 5px; white-space: pre-wrap;">&lt;?php
$servers = [
    'myserver' => [
        'host' => 'ваш_сервер',
        'user' => 'ваш_пользователь',
        'password' => 'ваш_пароль' // или 'key_path' => '/путь/к/ключу'
    ]
];</pre>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="nav">
            <form method="POST" style="display: inline;">
                <button type="submit" name="action" value="browse" class="nav-btn">📁 Обзор</button>
                <?php if (isset($_SESSION['current_path'])): ?>
                    <button type="submit" name="action" value="back" class="nav-btn">⬆️ Наверх</button>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="content">
            <!-- Боковая панель - выбор сервера -->
            <div class="sidebar">
                <h3>🌐 Серверы</h3>
                <?php if (!empty($availableServers)): ?>
                    <form method="POST" class="server-select">
                        <select name="server" required>
                            <option value="">Выберите сервер</option>
                            <?php foreach ($availableServers as $server): ?>
                                <option value="<?php echo escapeOutput($server); ?>" 
                                    <?php echo ($_SESSION['current_server'] ?? '') === $server ? 'selected' : ''; ?>>
                                    <?php echo escapeOutput($server); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="path" value="<?php echo $_SESSION['current_path'] ?? '/'; ?>" placeholder="Путь">
                        <button type="submit" name="action" value="browse">Перейти</button>
                    </form>
                    
                    <div class="servers-grid">
                        <?php foreach ($availableServers as $server): ?>
                            <div class="server-card" onclick="document.querySelector('select[name=\"server\"]').value='<?php echo $server; ?>'; document.querySelector('input[name=\"path\"]').value='/'; document.forms[1].submit();">
                                <div style="font-weight: bold;"><?php echo escapeOutput($server); ?></div>
                                <div style="font-size: 0.9rem; color: #718096;">
                                    <?php 
                                    $srv = Config::getServer($server);
                                    echo escapeOutput($srv['host'] ?? '');
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="message info">Нет настроенных серверов</div>
                <?php endif; ?>
            </div>
            
            <!-- Основное содержимое -->
            <div class="main-content">
                <?php if ($currentView === 'error'): ?>
                    <div class="message error"><?php echo escapeOutput($result); ?></div>
                
                <?php elseif ($currentView === 'file'): ?>
                    <div class="file-info">
                        <div class="info-row">
                            <span class="info-label">Файл:</span>
                            <span class="info-value"><?php echo escapeOutput(basename($_SESSION['current_file'] ?? '')); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Путь:</span>
                            <span class="info-value"><?php echo escapeOutput(dirname($_SESSION['current_file'] ?? '')); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Размер:</span>
                            <span class="info-value"><?php echo formatSize($fileInfo['size'] ?? 0); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Владелец:</span>
                            <span class="info-value"><?php echo escapeOutput($fileInfo['owner'] ?? ''); ?>:<?php echo escapeOutput($fileInfo['group'] ?? ''); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Права:</span>
                            <span class="info-value"><?php echo escapeOutput($fileInfo['permissions'] ?? ''); ?></span>
                        </div>
                    </div>
                    
                    <div class="file-content"><?php echo escapeOutput($fileContent); ?></div>
                
                <?php elseif ($currentView === 'file_info'): ?>
                    <div class="message info">
                        <h3>Бинарный файл</h3>
                        <p>Тип: <?php echo escapeOutput($fileContent['file_type'] ?? ''); ?></p>
                        <p>Размер: <?php echo formatSize($fileContent['size'] ?? 0); ?></p>
                        <p>Нельзя отобразить содержимое бинарного файла.</p>
                    </div>
                
                <?php elseif (!empty($browserData)): ?>
                    <!-- Хлебные крошки -->
                    <div class="breadcrumbs">
                        <?php 
                        $pathParts = explode('/', trim($browserData['current_path'], '/'));
                        $currentPath = '';
                        foreach ($pathParts as $i => $part):
                            if ($part === '') continue;
                            $currentPath .= '/' . $part;
                        ?>
                            <span class="breadcrumb-item" onclick="navigateTo('<?php echo $currentPath; ?>')">
                                <?php echo escapeOutput($part); ?>
                            </span>
                            <?php if ($i < count($pathParts) - 1): ?>/<?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Список файлов -->
                    <div class="file-list">
                        <?php
                        $lines = explode("\n", $browserData['listing']);
                        foreach ($lines as $line):
                            if (empty($line) || strpos($line, 'total ') === 0) continue;
                            
                            $parts = preg_split('/\s+/', $line, 9);
                            if (count($parts) < 9) continue;
                            
                            $perms = $parts[0];
                            $isDir = $perms[0] === 'd';
                            $name = $parts[8];
                            $size = $parts[4];
                            
                            if ($name === '.' || $name === '..') continue;
                            
                            $fullPath = $browserData['current_path'] . '/' . $name;
                        ?>
                            <div class="file-item">
                                <div class="file-icon"><?php echo getFileIcon($name, $isDir); ?></div>
                                <div class="file-name">
                                    <?php if ($isDir): ?>
                                        <a href="?action=browse&server=<?php echo urlencode($_SESSION['current_server']); ?>&path=<?php echo urlencode($fullPath); ?>" style="color: #667eea; text-decoration: none;">
                                            <?php echo escapeOutput($name); ?>/
                                        </a>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="view_file">
                                            <input type="hidden" name="file_path" value="<?php echo escapeOutput($fullPath); ?>">
                                            <button type="submit" style="background: none; border: none; color: #667eea; cursor: pointer; text-align: left; padding: 0;">
                                                <?php echo escapeOutput($name); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="file-size"><?php echo formatSize($size); ?></div>
                                <div class="file-actions">
                                    <?php if (!$isDir): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="view_file">
                                            <input type="hidden" name="file_path" value="<?php echo escapeOutput($fullPath); ?>">
                                            <button type="submit" title="Просмотр">👁️</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                <?php else: ?>
                    <div class="message info">
                        <h3>Добро пожаловать!</h3>
                        <p>Выберите сервер и укажите путь для начала работы.</p>
                        <p>Функции:</p>
                        <ul>
                            <li>📁 Навигация по директориям</li>
                            <li>👁️ Просмотр текстовых файлов</li>
                            <li>📊 Информация о файлах</li>
                            <li>⚡ Быстрое переключение между серверами</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function navigateTo(path) {
            document.querySelector('input[name="path"]').value = path;
            document.forms[1].submit();
        }
        
        // Автофокус
        document.addEventListener('DOMContentLoaded', function() {
            const serverSelect = document.querySelector('select[name="server"]');
            if (serverSelect) serverSelect.focus();
        });
    </script>
</body>
</html>
