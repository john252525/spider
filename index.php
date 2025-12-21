<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);




session_start();

// Подключаем модули
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ssh.php';
require_once __DIR__ . '/includes/utils.php';

// Инициализация
$currentView = 'browser'; // browser, file, error
$fileContent = '';
$fileInfo = [];
$currentServer = $_SESSION['current_server'] ?? '';
$currentPath = $_SESSION['current_path'] ?? '/';
$treeData = [];
$selectedPath = '';

// Обработка запросов
try {
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
                    
                    // Загружаем корневую директорию сервера
                    $ssh = new SSHManager($serverName);
                    $ssh->connect();
                    $browserData = $ssh->listDirectory('/');
                    $_SESSION['last_listing'][$serverName]['/'] = $browserData;
                }
                break;
                
            case 'browse':
                $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
                $path = $_GET['path'] ?? '/';
                
                if ($serverName) {
                    $ssh = new SSHManager($serverName);
                    $ssh->connect();
                    $browserData = $ssh->listDirectory($path);
                    
                    $_SESSION['current_server'] = $serverName;
                    $_SESSION['current_path'] = $browserData['current_path'];
                    $_SESSION['last_listing'][$serverName][$path] = $browserData;
                    
                    $currentServer = $serverName;
                    $currentPath = $browserData['current_path'];
                }
                break;
                
            case 'view_file':
                $serverName = $_SESSION['current_server'] ?? '';
                $filePath = $_GET['file_path'] ?? '';
                
                if ($serverName && $filePath) {
                    $ssh = new SSHManager($serverName);
                    $ssh->connect();
                    
                    $fileData = $ssh->readFile($filePath);
                    $fileInfo = $ssh->getFileInfo($filePath);
                    
                    if ($fileData['type'] === 'text') {
                        $currentView = 'file';
                        $fileContent = $fileData['content'];
                    } else {
                        $currentView = 'file_info';
                    }
                    
                    $_SESSION['current_file'] = $filePath;
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
                        
                        $fullPath = rtrim($path, '/') . '/' . $name;
                        
                        if ($isDir) {
                            $tree['children'][] = [
                                'path' => $fullPath,
                                'name' => $name,
                                'type' => 'directory',
                                'children' => [] // Будет заполняться по запросу
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
                    
                    // Отправляем JSON
                    header('Content-Type: application/json');
                    echo json_encode($tree);
                    exit;
                }
                break;
        }
    }
    
} catch (Exception $e) {
    $currentView = 'error';
    $errorMessage = "Ошибка: " . $e->getMessage();
}

// Получаем список серверов
$availableServers = [];
try {
    $availableServers = Config::getServersList();
} catch (Exception $e) {
    $configError = $e->getMessage();
}

// Если это не AJAX запрос, показываем полную страницу
if (!isset($_GET['ajax'])) {
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
        
        .tree-item {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            user-select: none;
        }
        
        .tree-item:hover {
            background: #edf2f7;
        }
        
        .tree-item.active {
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
        }
        
        .file-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        
        /* Кнопки */
        .btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
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
                        <div class="server-item <?php echo $currentServer === $server ? 'active' : ''; ?>" 
                             onclick="selectServer('<?php echo escapeOutput($server); ?>')">
                            <i class="fas fa-server"></i>
                            <span><?php echo escapeOutput($server); ?></span>
                        </div>
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
                    <div id="serverTree" data-server="<?php echo escapeOutput($currentServer); ?>"></div>
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
                
                <?php elseif ($currentView === 'file'): ?>
                    <div class="file-info-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0;">
                                <i class="fas fa-file"></i> 
                                <?php echo escapeOutput(basename($_SESSION['current_file'] ?? '')); ?>
                            </h3>
                            <div>
                                <button class="btn btn-sm" onclick="history.back()">
                                    <i class="fas fa-arrow-left"></i> Назад
                                </button>
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Путь:</span>
                                <span class="info-value"><?php echo escapeOutput(dirname($_SESSION['current_file'] ?? '')); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Размер:</span>
                                <span class="info-value"><?php echo formatSize($fileInfo['size'] ?? 0); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Владелец:</span>
                                <span class="info-value"><?php echo escapeOutput($fileInfo['owner'] ?? ''); ?>:<?php echo escapeOutput($fileInfo['group'] ?? ''); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Права:</span>
                                <span class="info-value"><?php echo formatPermissions($fileInfo['permissions'] ?? ''); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Тип:</span>
                                <span class="info-value"><?php echo escapeOutput($fileInfo['file_type'] ?? ''); ?></span>
                            </div>
                            <?php if (isset($fileContent['lines'])): ?>
                            <div class="info-item">
                                <span class="info-label">Строк:</span>
                                <span class="info-value"><?php echo $fileContent['lines']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="file-content"><?php echo escapeOutput($fileContent['content'] ?? ''); ?></div>
                
                <?php elseif ($currentView === 'file_info'): ?>
                    <div class="message info">
                        <h3><i class="fas fa-file-binary"></i> Бинарный файл</h3>
                        <p>Тип: <?php echo escapeOutput($fileInfo['file_type'] ?? ''); ?></p>
                        <p>Размер: <?php echo formatSize($fileInfo['size'] ?? 0); ?></p>
                        <p>Нельзя отобразить содержимое бинарного файла.</p>
                        <button class="btn" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Назад к списку файлов
                        </button>
                    </div>
                
                <?php elseif ($currentServer && $currentPath): ?>
                    <div id="fileBrowser"></div>
                
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
        // Глобальные переменные
        let currentServer = '<?php echo escapeOutput($currentServer); ?>';
        let currentPath = '<?php echo escapeOutput($currentPath); ?>';
        let treeCache = {};
        
        // Выбор сервера
        function selectServer(serverName) {
            window.location.href = `?action=select_server&server=${encodeURIComponent(serverName)}`;
        }
        
        // Загрузка дерева
        async function loadTree(server, path = '/') {
            const treeContainer = document.getElementById('serverTree');
            if (!treeContainer) return;
            
            const cacheKey = `${server}:${path}`;
            
            // Показываем загрузку
            if (!treeCache[cacheKey]) {
                treeContainer.innerHTML = '<div style="padding: 20px; text-align: center;"><div class="loader"></div> Загрузка...</div>';
                
                try {
                    const response = await fetch(`?action=get_tree&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}&ajax=1`);
                    const data = await response.json();
                    
                    treeCache[cacheKey] = data;
                    renderTree(data, treeContainer);
                    
                    // Если это корень, загружаем содержимое
                    if (path === '/') {
                        loadDirectory(server, '/');
                    }
                } catch (error) {
                    treeContainer.innerHTML = `<div class="error">Ошибка загрузки: ${error.message}</div>`;
                }
            } else {
                renderTree(treeCache[cacheKey], treeContainer);
            }
        }
        
        // Отрисовка дерева
        function renderTree(node, container, level = 0) {
            container.innerHTML = '';
            
            const ul = document.createElement('ul');
            ul.className = 'tree';
            
            function createNode(item) {
                const li = document.createElement('li');
                li.className = 'tree-node';
                li.dataset.path = item.path;
                li.dataset.type = item.type;
                
                const div = document.createElement('div');
                div.className = 'tree-item';
                if (item.path === currentPath) {
                    div.classList.add('active');
                }
                
                // Стрелка для директорий
                if (item.type === 'directory') {
                    const arrow = document.createElement('span');
                    arrow.className = 'tree-arrow';
                    arrow.innerHTML = '▶';
                    arrow.onclick = (e) => {
                        e.stopPropagation();
                        toggleDirectory(item.path, arrow, childrenContainer);
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
                name.textContent = item.name;
                name.title = item.path;
                div.appendChild(name);
                
                // Размер для файлов
                if (item.type === 'file' && item.size) {
                    const size = document.createElement('span');
                    size.className = 'tree-size';
                    size.textContent = formatSize(parseInt(item.size));
                    div.appendChild(size);
                }
                
                // Обработчик клика
                div.onclick = () => {
                    if (item.type === 'directory') {
                        navigateToDirectory(server, item.path);
                    } else {
                        viewFile(server, item.path);
                    }
                };
                
                li.appendChild(div);
                
                // Дочерние элементы
                if (item.type === 'directory' && item.children) {
                    const childrenContainer = document.createElement('div');
                    childrenContainer.className = 'tree-children';
                    li.appendChild(childrenContainer);
                    
                    if (item.children.length > 0) {
                        item.children.forEach(child => {
                            childrenContainer.appendChild(createNode(child));
                        });
                    }
                }
                
                return li;
            }
            
            // Рендерим корневой узел
            if (node.path === '/' && node.children) {
                node.children.forEach(child => {
                    ul.appendChild(createNode(child));
                });
            } else {
                ul.appendChild(createNode(node));
            }
            
            container.appendChild(ul);
        }
        
        // Переключение директории
        function toggleDirectory(path, arrow, container) {
            const isExpanded = container.classList.contains('expanded');
            
            if (!isExpanded && (!container.hasChildNodes() || container.children.length === 0)) {
                // Загружаем содержимое директории
                arrow.innerHTML = '<div class="loader" style="display: inline-block; width: 12px; height: 12px;"></div>';
                
                fetch(`?action=get_tree&server=${encodeURIComponent(currentServer)}&path=${encodeURIComponent(path)}&ajax=1`)
                    .then(response => response.json())
                    .then(data => {
                        arrow.innerHTML = '▼';
                        container.classList.add('expanded');
                        
                        data.children.forEach(child => {
                            const childNode = createNode(child);
                            container.appendChild(childNode);
                        });
                    })
                    .catch(error => {
                        arrow.innerHTML = '▶';
                        console.error('Error loading directory:', error);
                    });
            } else {
                if (isExpanded) {
                    arrow.innerHTML = '▶';
                    container.classList.remove('expanded');
                } else {
                    arrow.innerHTML = '▼';
                    container.classList.add('expanded');
                }
            }
        }
        
        // Навигация по директории
        function navigateToDirectory(server, path) {
            window.location.href = `?action=browse&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}`;
        }
        
        // Просмотр файла
        function viewFile(server, filePath) {
            window.location.href = `?action=view_file&server=${encodeURIComponent(server)}&file_path=${encodeURIComponent(filePath)}`;
        }
        
        // Загрузка содержимого директории
        function loadDirectory(server, path) {
            const browser = document.getElementById('fileBrowser');
            if (!browser) return;
            
            browser.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loader"></div> Загрузка файлов...</div>';
            
            fetch(`?action=browse&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}&ajax=1`)
                .then(response => response.text())
                .then(html => {
                    browser.innerHTML = html;
                })
                .catch(error => {
                    browser.innerHTML = `<div class="error">Ошибка загрузки: ${error.message}</div>`;
                });
        }
        
        // Форматирование размера файла
        function formatSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            if (currentServer) {
                loadTree(currentServer, '/');
            }
        });
    </script>
</body>
</html>
<?php } ?>
