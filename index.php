<?php

session_start();

// Подключаем модули
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ssh.php';
require_once __DIR__ . '/includes/utils.php';

// Включаем отладку
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Инициализация
$currentView = 'browser';
$fileContent = null;
$currentServer = $_SESSION['current_server'] ?? '';
$currentPath = $_SESSION['current_path'] ?? '/';

// Обработка запросов
try {
    // Обработка GET запросов
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            
            switch ($action) {
                case 'select_server':
                    $serverName = $_GET['server'] ?? '';
                    if ($serverName) {
                        $_SESSION['current_server'] = $serverName;
                        $_SESSION['current_path'] = '/';
                        $currentServer = $serverName;
                        $currentPath = '/';
                    }
                    break;
                    
                case 'browse':
                    $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
                    $path = $_GET['path'] ?? '/';
                    
                    if ($serverName) {
                        $_SESSION['current_server'] = $serverName;
                        $_SESSION['current_path'] = $path;
                        $currentServer = $serverName;
                        $currentPath = $path;
                        
                        // Для AJAX запросов возвращаем только содержимое
                        if (isset($_GET['ajax'])) {
                            $ssh = new SSHManager($serverName);
                            $ssh->connect();
                            $listing = $ssh->listDirectory($path);
                            
                            // Формируем HTML для файлового браузера
                            $html = renderFileBrowser($listing, $serverName);
                            echo $html;
                            exit;
                        }
                    }
                    break;
                    
                case 'view_file':
                    $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
                    $filePath = $_GET['file_path'] ?? '';
                    
                    if ($serverName && $filePath) {
                        $ssh = new SSHManager($serverName);
                        $ssh->connect();
                        
                        $fileData = $ssh->readFile($filePath);
                        $currentView = 'file';
                        $fileContent = $fileData;
                        
                        $_SESSION['current_file'] = $filePath;
                        $_SESSION['current_server'] = $serverName;
                        $_SESSION['current_path'] = dirname($filePath);
                        
                        $currentServer = $serverName;
                        $currentPath = dirname($filePath);
                    }
                    break;
                    
                case 'get_tree':
                    $serverName = $_GET['server'] ?? '';
                    $path = $_GET['path'] ?? '/';
                    
                    if ($serverName) {
                        $ssh = new SSHManager($serverName);
                        $ssh->connect();
                        $listing = $ssh->listDirectory($path);
                        
                        // Формируем данные для дерева
                        $tree = [
                            'path' => $path,
                            'name' => basename($path) ?: '/',
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
                            
                            // Формируем правильный путь
                            $fullPath = ($path === '/') ? '/' . $name : $path . '/' . $name;
                            error_log("Tree item: name='{$name}', path='{$fullPath}', isDir=" . ($isDir ? 'true' : 'false'));
                            
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
                        
                        header('Content-Type: application/json');
                        echo json_encode($tree);
                        exit;
                    }
                    break;
            }
        }
    }
    
} catch (Exception $e) {
    $currentView = 'error';
    $errorMessage = "Ошибка: " . $e->getMessage();
    error_log("SSH Browser Error: " . $e->getMessage());
}

// Получаем список серверов
$availableServers = [];
try {
    $availableServers = Config::getServersList();
} catch (Exception $e) {
    $configError = $e->getMessage();
}

// Функция для рендеринга файлового браузера
function renderFileBrowser($listing, $serverName) {
    $html = '<div class="file-grid">';
    
    // Кнопка "Наверх" если не корень
    if ($listing['current_path'] !== '/') {
        $parentPath = dirname($listing['current_path']);
        $html .= '
        <a href="?action=browse&server=' . urlencode($serverName) . '&path=' . urlencode($parentPath) . '" class="file-item">
            <div class="file-icon">⬆️</div>
            <div class="file-name">..</div>
        </a>';
    }
    
    $lines = explode("\n", $listing['listing']);
    foreach ($lines as $line) {
        if (empty($line) || strpos($line, 'total ') === 0) continue;
        
        $parts = preg_split('/\s+/', $line, 9);
        if (count($parts) < 9) continue;
        
        $perms = $parts[0];
        $isDir = $perms[0] === 'd';
        $name = $parts[8];
        $size = $parts[4];
        
        if ($name === '.' || $name === '..') continue;
        
        // Формируем правильный путь
        $fullPath = ($listing['current_path'] === '/') ? '/' . $name : $listing['current_path'] . '/' . $name;
        error_log("File browser item: name='{$name}', path='{$fullPath}', current='{$listing['current_path']}'");
        
        if ($isDir) {
            $html .= '
            <a href="?action=browse&server=' . urlencode($serverName) . '&path=' . urlencode($fullPath) . '" class="file-item">
                <div class="file-icon">📁</div>
                <div class="file-name">' . htmlspecialchars($name) . '</div>
            </a>';
        } else {
            $html .= '
            <a href="?action=view_file&server=' . urlencode($serverName) . '&file_path=' . urlencode($fullPath) . '" class="file-item">
                <div class="file-icon">' . getFileIcon($name, false) . '</div>
                <div class="file-name">' . htmlspecialchars($name) . '</div>
                <div style="font-size: 11px; color: #718096; margin-top: 5px;">' . formatSize($size) . '</div>
            </a>';
        }
    }
    
    $html .= '</div>';
    return $html;
}

// Если это AJAX запрос, выходим
if (isset($_GET['ajax'])) {
    exit;
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
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            padding: 20px;
            color: #333;
        }
        
        .container {
            display: flex;
            height: calc(100vh - 40px);
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        /* Левая панель - дерево */
        .tree-panel {
            width: 350px;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .tree-header {
            padding: 20px;
            background: #2d3748;
            color: white;
        }
        
        .tree-header h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .servers-list {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        
        .server-item {
            padding: 8px 12px;
            background: #4a5568;
            border-radius: 5px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .server-item:hover {
            background: #667eea;
        }
        
        .server-item.active {
            background: #805ad5;
        }
        
        .tree-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        /* Стили для дерева */
        .tree {
            list-style: none;
            padding-left: 0;
        }
        
        .tree-node {
            margin-bottom: 2px;
        }
        
        .tree-link {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            user-select: none;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }
        
        .tree-link:hover {
            background: #edf2f7;
            text-decoration: none;
        }
        
        .tree-link.active {
            background: #e2e8f0;
            font-weight: bold;
        }
        
        .tree-icon {
            width: 20px;
            text-align: center;
            margin-right: 6px;
            font-size: 14px;
        }
        
        .tree-arrow {
            width: 16px;
            text-align: center;
            margin-right: 4px;
            transition: transform 0.2s;
            font-size: 10px;
            cursor: pointer;
            user-select: none;
        }
        
        .tree-arrow.expanded {
            transform: rotate(90deg);
        }
        
        .tree-name {
            flex: 1;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .tree-size {
            font-size: 12px;
            color: #718096;
            margin-left: 8px;
        }
        
        .tree-children {
            margin-left: 20px;
            display: none;
        }
        
        .tree-children.expanded {
            display: block;
        }
        
        /* Основная панель */
        .main-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .main-header {
            padding: 20px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .path-display {
            font-family: monospace;
            font-size: 16px;
            color: #4a5568;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
            word-break: break-all;
            flex: 1;
        }
        
        .back-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-btn:hover {
            background: #5a67d8;
            text-decoration: none;
            color: white;
        }
        
        .content-area {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: white;
        }
        
        /* Стили для файлового браузера */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .file-item {
            text-align: center;
            padding: 15px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
            text-decoration: none;
            color: inherit;
        }
        
        .file-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: white;
            text-decoration: none;
            color: inherit;
        }
        
        .file-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        .file-name {
            font-size: 12px;
            word-break: break-word;
            line-height: 1.3;
        }
        
        /* Стили для просмотра файлов */
        .file-content {
            background: #1a202c;
            color: #cbd5e0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            overflow-x: auto;
            max-height: 70vh;
        }
        
        .file-content.binary {
            background: #fed7d7;
            color: #9b2c2c;
            font-family: 'Segoe UI', sans-serif;
            padding: 30px;
            text-align: center;
        }
        
        .file-info-card {
            background: #f7fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 14px;
            color: #2d3748;
            font-weight: 500;
        }
        
        /* Сообщения */
        .message {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .error {
            background: #fed7d7;
            color: #e53e3e;
            border-left: 4px solid #e53e3e;
        }
        
        .info {
            background: #bee3f8;
            color: #3182ce;
            border-left: 4px solid #3182ce;
        }
        
        .welcome {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }
        
        .welcome h2 {
            margin-bottom: 20px;
            color: #4a5568;
        }
        
        /* Загрузчик */
        .loader {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Адаптивность */
        @media (max-width: 1024px) {
            .container {
                flex-direction: column;
            }
            
            .tree-panel {
                width: 100%;
                height: 300px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Левая панель - дерево -->
        <div class="tree-panel">
            <div class="tree-header">
                <h2><i class="fas fa-server"></i> SSH Browser</h2>
                
                <div class="servers-list">
                    <?php foreach ($availableServers as $server): ?>
                        <a href="?action=select_server&server=<?php echo urlencode($server); ?>" 
                           class="server-item <?php echo $currentServer === $server ? 'active' : ''; ?>">
                            <i class="fas fa-server"></i>
                            <span><?php echo escapeOutput($server); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($currentServer): ?>
                    <div style="color: #cbd5e0; font-size: 14px;">
                        <i class="fas fa-plug"></i> Подключено: <?php echo escapeOutput($currentServer); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tree-container" id="treeContainer">
                <?php if ($currentServer): ?>
                    <div id="serverTree"></div>
                <?php else: ?>
                    <div class="welcome">
                        <i class="fas fa-mouse-pointer" style="font-size: 48px; color: #a0aec0; margin-bottom: 20px;"></i>
                        <p>Выберите сервер для начала работы</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Основная панель -->
        <div class="main-panel">
            <div class="main-header">
                <?php if ($currentPath && $currentServer): ?>
                    <?php if ($currentPath !== '/'): ?>
                        <a href="?action=browse&server=<?php echo urlencode($currentServer); ?>&path=<?php echo urlencode(dirname($currentPath)); ?>" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    <?php else: ?>
                        <span class="back-btn" style="visibility: hidden;">
                            <i class="fas fa-arrow-left"></i> Назад
                        </span>
                    <?php endif; ?>
                    <div class="path-display" id="currentPath">
                        <i class="fas fa-folder"></i> 
                        <?php echo escapeOutput($currentPath); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="content-area" id="contentArea">
                <?php if (isset($configError)): ?>
                    <div class="message error">
                        <strong>Ошибка конфигурации:</strong> <?php echo escapeOutput($configError); ?>
                    </div>
                
                <?php elseif ($currentView === 'error'): ?>
                    <div class="message error"><?php echo escapeOutput($errorMessage); ?></div>
                
                <?php elseif ($currentView === 'file' && isset($fileContent)): ?>
                    <div class="file-info-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0;">
                                <i class="fas fa-file"></i> 
                                <?php echo escapeOutput(basename($fileContent['path'] ?? '')); ?>
                            </h3>
                            <a href="?action=browse&server=<?php echo urlencode($currentServer); ?>&path=<?php echo urlencode(dirname($fileContent['path'])); ?>" class="back-btn btn-sm">
                                <i class="fas fa-arrow-left"></i> Назад
                            </a>
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Путь:</span>
                                <span class="info-value"><?php echo escapeOutput(dirname($fileContent['path'] ?? '')); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Размер:</span>
                                <span class="info-value"><?php echo formatSize($fileContent['size'] ?? 0); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Тип:</span>
                                <span class="info-value"><?php echo escapeOutput($fileContent['file_type'] ?? ''); ?></span>
                            </div>
                            <?php if (isset($fileContent['lines'])): ?>
                            <div class="info-item">
                                <span class="info-label">Строк:</span>
                                <span class="info-value"><?php echo $fileContent['lines']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($fileContent['encoding']) && $fileContent['encoding'] !== 'binary'): ?>
                            <div class="info-item">
                                <span class="info-label">Кодировка:</span>
                                <span class="info-value"><?php echo escapeOutput($fileContent['encoding']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="file-content <?php echo ($fileContent['type'] ?? '') === 'binary' ? 'binary' : ''; ?>">
                        <?php 
                        if (($fileContent['type'] ?? '') === 'binary') {
                            echo "⚠️ Это бинарный файл. Не удалось отобразить содержимое.\n";
                            echo "Размер: " . formatSize($fileContent['size'] ?? 0) . "\n";
                            echo "Тип: " . escapeOutput($fileContent['file_type'] ?? '');
                        } else {
                            echo escapeOutput($fileContent['content'] ?? '');
                        }
                        ?>
                    </div>
                
                <?php elseif ($currentServer && $currentPath): ?>
                    <div id="fileBrowser">
                        <div style="text-align: center; padding: 40px;">
                            <div class="loader"></div> 
                            <p>Загрузка файлов...</p>
                        </div>
                    </div>
                
                <?php else: ?>
                    <div class="welcome">
                        <h2><i class="fas fa-cloud"></i> SSH File Browser</h2>
                        <p>Выберите сервер слева для просмотра файлов</p>
                        <div style="margin-top: 30px; color: #a0aec0;">
                            <p><i class="fas fa-check-circle"></i> Навигация по дереву файлов</p>
                            <p><i class="fas fa-check-circle"></i> Просмотр текстовых файлов</p>
                            <p><i class="fas fa-check-circle"></i> Информация о файлах и папках</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            const currentServer = '<?php echo escapeOutput($currentServer); ?>';
            const currentPath = '<?php echo escapeOutput($currentPath); ?>';
            
            console.log('Current server:', currentServer);
            console.log('Current path:', currentPath);
            
            if (currentServer) {
                // Загружаем дерево для текущего сервера
                loadTree(currentServer, '/');
                
                // Загружаем содержимое текущей директории
                if (currentPath) {
                    loadDirectory(currentServer, currentPath);
                }
            }
        });
        
        // Загрузка дерева
        async function loadTree(server, path = '/') {
            const treeContainer = document.getElementById('serverTree');
            if (!treeContainer) {
                console.error('Tree container not found');
                return;
            }
            
            treeContainer.innerHTML = '<div style="padding: 20px; text-align: center;"><div class="loader"></div> Загрузка дерева...</div>';
            
            try {
                console.log('Loading tree for server:', server, 'path:', path);
                const response = await fetch(`?action=get_tree&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}&ajax=1`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Tree data received:', data);
                
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid tree data received');
                }
                
                renderTree(treeContainer, data, server);
            } catch (error) {
                console.error('Error loading tree:', error);
                treeContainer.innerHTML = `<div class="error">Ошибка загрузки дерева: ${error.message}</div>`;
            }
        }
        
        // Отрисовка дерева
        function renderTree(container, node, server) {
            console.log('Rendering tree, node:', node);
            
            container.innerHTML = '';
            
            const ul = document.createElement('ul');
            ul.className = 'tree';
            
            // Создаем корневой элемент
            const rootLi = document.createElement('li');
            rootLi.className = 'tree-node';
            
            const rootDiv = document.createElement('div');
            rootDiv.className = 'tree-link';
            rootDiv.onclick = () => {
                console.log('Root clicked, navigating to /');
                window.location.href = `?action=browse&server=${encodeURIComponent(server)}&path=/`;
            };
            
            const rootIcon = document.createElement('span');
            rootIcon.className = 'tree-icon';
            rootIcon.innerHTML = '📁';
            
            const rootName = document.createElement('span');
            rootName.className = 'tree-name';
            rootName.textContent = '/';
            
            rootDiv.appendChild(rootIcon);
            rootDiv.appendChild(rootName);
            rootLi.appendChild(rootDiv);
            ul.appendChild(rootLi);
            
            // Рендерим дочерние элементы, если они есть
            if (node.children && Array.isArray(node.children) && node.children.length > 0) {
                console.log('Rendering', node.children.length, 'children');
                
                const childrenContainer = document.createElement('div');
                childrenContainer.className = 'tree-children expanded';
                
                node.children.forEach(child => {
                    console.log('Creating child:', child);
                    try {
                        const childElement = createTreeItem(child, server);
                        childrenContainer.appendChild(childElement);
                    } catch (error) {
                        console.error('Error creating tree item:', error, 'child:', child);
                    }
                });
                
                rootLi.appendChild(childrenContainer);
            } else {
                console.log('No children to render');
            }
            
            container.appendChild(ul);
        }
        
        // Создание элемента дерева
        function createTreeItem(item, server) {
            console.log('Creating tree item:', item);
            
            if (!item || typeof item !== 'object') {
                throw new Error('Invalid item data');
            }
            
            const li = document.createElement('li');
            li.className = 'tree-node';
            
            const div = document.createElement('div');
            div.className = 'tree-link';
            div.dataset.path = item.path || '';
            div.dataset.type = item.type || '';
            
            // Стрелка для директорий
            if (item.type === 'directory') {
                const arrow = document.createElement('span');
                arrow.className = 'tree-arrow';
                arrow.innerHTML = '▶';
                arrow.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Arrow clicked for directory:', item.path);
                    toggleDirectory(item, arrow, li, server);
                };
                div.appendChild(arrow);
            } else {
                const spacer = document.createElement('span');
                spacer.className = 'tree-arrow';
                spacer.style.visibility = 'hidden';
                div.appendChild(spacer);
            }
            
            // Иконка
            const icon = document.createElement('span');
            icon.className = 'tree-icon';
            if (item.type === 'directory') {
                icon.innerHTML = '📁';
            } else {
                icon.innerHTML = item.icon || '📄';
            }
            div.appendChild(icon);
            
            // Имя
            const name = document.createElement('span');
            name.className = 'tree-name';
            name.textContent = item.name || 'unnamed';
            name.title = item.path || '';
            div.appendChild(name);
            
            // Размер для файлов
            if (item.type === 'file' && item.size) {
                const size = document.createElement('span');
                size.className = 'tree-size';
                const bytes = parseInt(item.size) || 0;
                size.textContent = formatSize(bytes);
                div.appendChild(size);
            }
            
            // Обработчик клика
            div.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Item clicked:', item);
                
                if (item.type === 'file') {
                    // Файл - открываем для просмотра
                    console.log('Opening file:', item.path);
                    window.location.href = `?action=view_file&server=${encodeURIComponent(server)}&file_path=${encodeURIComponent(item.path)}`;
                } else if (item.type === 'directory') {
                    // Папка - раскрываем в дереве
                    console.log('Toggling directory:', item.path);
                    const arrow = div.querySelector('.tree-arrow');
                    if (arrow) {
                        toggleDirectory(item, arrow, li, server);
                    }
                }
            };
            
            li.appendChild(div);
            
            // Контейнер для дочерних элементов (только для директорий)
            if (item.type === 'directory') {
                const childrenContainer = document.createElement('div');
                childrenContainer.className = 'tree-children';
                li.appendChild(childrenContainer);
            }
            
            return li;
        }
        
        // Переключение директории
        async function toggleDirectory(item, arrow, li, server) {
            console.log('toggleDirectory called for:', item.path);
            
            const childrenContainer = li.querySelector('.tree-children');
            if (!childrenContainer) {
                console.error('Children container not found');
                return;
            }
            
            const isExpanded = childrenContainer.classList.contains('expanded');
            console.log('Is expanded:', isExpanded);
            
            if (isExpanded) {
                // Скрываем
                arrow.innerHTML = '▶';
                childrenContainer.classList.remove('expanded');
                childrenContainer.innerHTML = '';
                return;
            }
            
            // Показываем загрузку
            arrow.innerHTML = '<div class="loader" style="display: inline-block; width: 12px; height: 12px;"></div>';
            
            try {
                console.log('Loading directory contents for:', item.path);
                const response = await fetch(`?action=get_tree&server=${encodeURIComponent(server)}&path=${encodeURIComponent(item.path)}&ajax=1`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Directory data received:', data);
                
                arrow.innerHTML = '▼';
                childrenContainer.classList.add('expanded');
                
                // Очищаем контейнер
                childrenContainer.innerHTML = '';
                
                if (data.children && Array.isArray(data.children) && data.children.length > 0) {
                    console.log('Rendering', data.children.length, 'child items');
                    
                    // Создаем контейнер для дочерних элементов
                    const childUl = document.createElement('ul');
                    childUl.className = 'tree';
                    childUl.style.marginLeft = '10px';
                    
                    data.children.forEach(child => {
                        try {
                            const childElement = createTreeItem(child, server);
                            childUl.appendChild(childElement);
                        } catch (error) {
                            console.error('Error creating child item:', error, 'child:', child);
                        }
                    });
                    
                    childrenContainer.appendChild(childUl);
                } else {
                    console.log('Directory is empty');
                    const emptyMsg = document.createElement('div');
                    emptyMsg.style.padding = '5px 10px';
                    emptyMsg.style.color = '#718096';
                    emptyMsg.style.fontSize = '12px';
                    emptyMsg.textContent = 'Папка пуста';
                    childrenContainer.appendChild(emptyMsg);
                }
            } catch (error) {
                console.error('Error loading directory:', error);
                arrow.innerHTML = '▶';
                
                const errorMsg = document.createElement('div');
                errorMsg.style.padding = '5px 10px';
                errorMsg.style.color = '#e53e3e';
                errorMsg.style.fontSize = '12px';
                errorMsg.textContent = 'Ошибка загрузки: ' + error.message;
                childrenContainer.appendChild(errorMsg);
            }
        }
        
        // Загрузка содержимого директории
        function loadDirectory(server, path) {
            const browser = document.getElementById('fileBrowser');
            if (!browser) {
                console.error('File browser container not found');
                return;
            }
            
            console.log('Loading directory for main view:', server, path);
            browser.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loader"></div> Загрузка файлов...</div>';
            
            fetch(`?action=browse&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}&ajax=1`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    console.log('Directory HTML received, length:', html.length);
                    browser.innerHTML = html;
                    
                    // Обновляем путь в заголовке
                    updatePathDisplay(path);
                })
                .catch(error => {
                    console.error('Error loading directory:', error);
                    browser.innerHTML = `<div class="error">Ошибка загрузки: ${error.message}</div>`;
                });
        }
        
        // Обновление отображения пути
        function updatePathDisplay(path) {
            const pathDisplay = document.getElementById('currentPath');
            if (pathDisplay) {
                pathDisplay.innerHTML = `<i class="fas fa-folder"></i> ${path}`;
            }
        }
        
        // Форматирование размера файла
        function formatSize(bytes) {
            if (!bytes || bytes === 0 || isNaN(bytes)) return '0 B';
            
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            // Проверяем индекс на валидность
            if (i < 0 || i >= sizes.length) {
                return bytes + ' B';
            }
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Добавим обработку ошибок формата размера
        window.formatSize = formatSize;
    </script>
</body>
</html>
