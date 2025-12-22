<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


// Включаем буферизацию вывода для предотвращения случайного вывода перед JSON
ob_start();

session_start();

// Подключаем модули
require_once __DIR__ . '/includes/configer.php';
require_once __DIR__ . '/includes/ssh.php';
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/includes/handlers/api.php';
require_once __DIR__ . '/includes/handlers/renderers.php';

// Инициализация
$currentView = 'browser';
$fileContent = null;
$currentServer = $_SESSION['current_server'] ?? '';
$currentPath = $_SESSION['current_path'] ?? '/var';

// Определяем стартовую папку
$startPath = '/var';

// Обработка запросов
try {
    // Обработка GET запросов
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];
        
        switch ($action) {
            case 'select_server':
                $serverName = $_GET['server'] ?? '';
                if ($serverName) {
                    $_SESSION['current_server'] = $serverName;
                    $_SESSION['current_path'] = $startPath;
                    $currentServer = $serverName;
                    $currentPath = $startPath;
                    
                    // Редирект
                    ob_end_clean();
                    header('Location: index.php');
                    exit;
                }
                break;
                
            case 'browse':
                $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
                $path = $_GET['path'] ?? $startPath;
                
                if ($serverName) {
                    $_SESSION['current_server'] = $serverName;
                    $_SESSION['current_path'] = $path;
                    $currentServer = $serverName;
                    $currentPath = $path;
                    
                    // Для AJAX запросов возвращаем только содержимое
                    if (isset($_GET['ajax'])) {
                        ApiHandler::handleBrowse($serverName, $path);
                    }
                }
                break;
                
            case 'view_file':
                $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
                $filePath = $_GET['file_path'] ?? '';
                
                if ($serverName && $filePath) {
                    // Для AJAX запросов возвращаем только содержимое файла
                    if (isset($_GET['ajax'])) {
                        ApiHandler::handleViewFile($serverName, $filePath);
                    }
                    
                    // Для обычных запросов сохраняем данные для отображения
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
                $path = $_GET['path'] ?? $startPath;
                
                if ($serverName) {
                    ApiHandler::handleGetTree($serverName, $path, $startPath);
                }
                break;
                
            case 'list_all_files_filtered':
                $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
                $path = $_GET['path'] ?? $startPath;
                
                if ($serverName && isset($_GET['ajax'])) {
                    ApiHandler::handleListAllFilesFiltered($serverName, $path);
                }
                break;
        }
    }
    
    // Обработка POST запросов
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
        $action = $_GET['action'];
        
        switch ($action) {
            case 'read_multiple_files':
                if (isset($_GET['ajax'])) {
                    ApiHandler::handleReadMultipleFiles();
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

// Функции рендеринга перенесены в includes/handlers/renderers.php
// Используем класс Renderer вместо старых функций

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
            display: block;
            padding: 8px 12px;
            background: #4a5568;
            border-radius: 5px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            color: white;
        }
        
        .server-item:hover {
            background: #667eea;
            text-decoration: none;
            color: white;
        }
        
        .server-item.active {
            background: #805ad5;
        }
        
        .tree-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
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
        
        /* Простое дерево */
        .simple-tree {
            list-style: none;
            padding-left: 0;
        }
        
        .simple-tree-item {
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
        }
        
        .simple-tree-item:hover {
            background: #edf2f7;
        }
        
        .tree-arrow {
            width: 20px;
            text-align: center;
            cursor: pointer;
            user-select: none;
        }
        
        .tree-icon {
            margin-right: 5px;
            width: 20px;
            text-align: center;
        }
        
        .tree-name {
            flex: 1;
        }
        
        .tree-children {
            margin-left: 20px;
            display: none;
        }
        
        .tree-children.expanded {
            display: block;
        }
        
        /* Все файлы */
        .all-files-container {
            padding: 20px;
        }
        
        .files-list-container {
            margin-top: 20px;
        }
        
        .files-textarea {
            width: 100%;
            padding: 15px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            background: white;
            color: #2d3748;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            resize: vertical;
            min-height: 300px;
        }
        
        .files-textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .json-textarea {
            font-family: 'Fira Code', 'Courier New', monospace;
            background: #1a202c;
            color: #cbd5e0;
            border: 2px solid #4a5568;
        }
        
        /* Кнопки */
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-primary {
            background: #805ad5;
        }
        
        .btn-primary:hover {
            background: #6b46c1;
        }
        
        .btn-large {
            padding: 12px 30px;
            font-size: 16px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .filter-info {
            background: #e6fffa;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            font-size: 13px;
            border-left: 4px solid #38a169;
        }
        
        .filter-info i {
            color: #38a169;
            margin-right: 8px;
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
        
        /* Дополнительные стили для аккордеона файлов */
        .file-item-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        
        .binary-warning {
            background: #fefcbf;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            color: #744210;
        }
        
        .file-content-display {
            background: #1a202c;
            color: #cbd5e0;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.4;
            white-space: pre-wrap;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
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
                            <?php echo escapeOutput($server); ?>
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
                    <div id="serverTree">
                        <div style="text-align: center; padding: 20px;">
                            <div class="loader"></div>
                            <p>Загрузка дерева...</p>
                        </div>
                    </div>
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
                    <?php if ($currentPath !== $startPath): ?>
                        <a href="javascript:void(0)" onclick="showFileBrowser('<?php echo escapeOutput($currentServer); ?>', '<?php echo escapeOutput(dirname($currentPath)); ?>')" class="back-btn">
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
                            <a href="javascript:void(0)" onclick="showFileBrowser('<?php echo escapeOutput($currentServer); ?>', '<?php echo escapeOutput(dirname($fileContent['path'])); ?>')" class="back-btn btn-sm">
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
                            <?php if (isset($fileContent['lines'])): ?>
                            <div class="info-item">
                                <span class="info-label">Строк:</span>
                                <span class="info-value"><?php echo $fileContent['lines']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="file-content <?php echo ($fileContent['type'] ?? '') === 'binary' ? 'binary' : ''; ?>">
                        <?php 
                        if (($fileContent['type'] ?? '') === 'binary') {
                            echo "⚠️ Это бинарный файл. Не удалось отобразить содержимое.\n";
                            echo "Размер: " . formatSize($fileContent['size'] ?? 0) . "\n";
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
                            <p><i class="fas fa-check-circle"></i> Массовое чтение файлов</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
// Полный JavaScript код
document.addEventListener('DOMContentLoaded', function() {
    const currentServer = '<?php echo escapeOutput($currentServer); ?>';
    const currentPath = '<?php echo escapeOutput($currentPath); ?>';
    
    console.log('Initializing... Server:', currentServer, 'Path:', currentPath);
    
    if (currentServer) {
        loadTree(currentServer, '<?php echo $startPath; ?>');
        
        if (currentPath) {
            loadDirectory(currentServer, currentPath);
        }
    }
});

window.createTreeElement = function(item, server) {
    const li = document.createElement('li');
    
    const div = document.createElement('div');
    div.className = 'simple-tree-item';
    div.dataset.path = item.path;
    div.dataset.type = item.type;
    
    if (item.type === 'directory') {
        const arrow = document.createElement('span');
        arrow.className = 'tree-arrow';
        arrow.innerHTML = '▶';
        arrow.onclick = function(e) {
            e.stopPropagation();
            toggleSimpleDirectory(item, arrow, li, server);
        };
        div.appendChild(arrow);
    } else {
        const spacer = document.createElement('span');
        spacer.className = 'tree-arrow';
        spacer.innerHTML = '&nbsp;';
        div.appendChild(spacer);
    }
    
    const icon = document.createElement('span');
    icon.className = 'tree-icon';
    icon.innerHTML = item.type === 'directory' ? '📁' : (item.icon || '📄');
    div.appendChild(icon);
    
    const name = document.createElement('span');
    name.className = 'tree-name';
    name.textContent = item.name;
    name.title = item.path;
    
    if (item.type === 'file') {
        name.style.cursor = 'pointer';
        name.style.color = '#3182ce';
        name.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            loadFileContent(server, item.path, item.name);
        };
    } else if (item.type === 'directory') {
        name.style.cursor = 'pointer';
        name.style.color = '#2d3748';
        name.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            loadAllFiles(server, item.path);
        };
    }
    
    div.appendChild(name);
    li.appendChild(div);
    
    if (item.type === 'directory') {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'tree-children';
        li.appendChild(childrenContainer);
    }
    
    return li;
};

async function loadAllFiles(server, path) {
    console.log('Loading all files for path:', path);
    
    const contentArea = document.getElementById('contentArea');
    const fileBrowser = document.getElementById('fileBrowser');
    
    if (fileBrowser) {
        fileBrowser.style.display = 'none';
    }
    
    let filesContainer = document.getElementById('allFilesContainer');
    if (!filesContainer) {
        filesContainer = document.createElement('div');
        filesContainer.id = 'allFilesContainer';
        contentArea.appendChild(filesContainer);
    }
    
    filesContainer.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="loader"></div> 
            <p>Сканирование файлов...</p>
        </div>
    `;
    filesContainer.style.display = 'block';
    
    try {
        const response = await fetch(`?action=list_all_files_filtered&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}&ajax=1`);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error ${response.status}: ${errorText}`);
        }
        
        const result = await response.json();
        console.log('Filtered files result:', result);
        
        if (!result.success) {
            throw new Error(result.error || 'Unknown error');
        }
        
        const files = result.files || [];
        
        let fileListText = '';
        files.forEach(file => {
            if (file && typeof file === 'string') {
                fileListText += file + '\n';
            } else if (file && file.path) {
                fileListText += file.path + '\n';
            }
        });
        
        filesContainer.innerHTML = `
            <div class="all-files-container">
                <div class="file-info-card">
                    <h3><i class="fas fa-list"></i> Все файлы в ${escapeHtml(path)}</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Файлов:</span>
                            <span class="info-value">${files.length}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Папка:</span>
                            <span class="info-value">${escapeHtml(path)}</span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; margin-bottom: 20px;">
                        <button onclick="copyAllFiles()" class="btn" style="margin-right: 10px;">
                            <i class="fas fa-copy"></i> Копировать все пути
                        </button>
                        <button onclick="downloadFileList()" class="btn" style="margin-right: 10px;">
                            <i class="fas fa-download"></i> Скачать список
                        </button>
                        <button onclick="loadFilesContent()" class="btn btn-primary">
                            <i class="fas fa-file-alt"></i> Прочитать все файлы
                        </button>
                    </div>
                    
                    <div class="filter-info">
                        <i class="fas fa-filter"></i> Применены фильтры: 
                        <ul style="margin: 5px 0 0 20px;">
                            <li>Скрыты файлы/папки, начинающиеся с точки</li>
                            <li>Сортировка: папки → файлы → по алфавиту</li>
                        </ul>
                    </div>
                </div>
                
                <div class="files-list-container">
                    <textarea id="allFilesTextarea" class="files-textarea" rows="25" placeholder="Редактируйте список файлов...">${fileListText}</textarea>
                    
                    <div style="margin-top: 15px; text-align: center;">
                        <button onclick="loadFilesContent()" class="btn btn-primary btn-large">
                            <i class="fas fa-file-alt"></i> Прочитать содержимое выбранных файлов
                        </button>
                        <p style="margin-top: 10px; color: #718096; font-size: 13px;">
                            Отредактируйте список выше, удалив ненужные файлы
                        </p>
                    </div>
                </div>
                
                <div id="filesContentResult" style="margin-top: 30px; display: none;"></div>
            </div>
        `;
        
        const pathDisplay = document.getElementById('currentPath');
        if (pathDisplay) {
            pathDisplay.innerHTML = `<i class="fas fa-folder-tree"></i> Все файлы в ${path}`;
        }
        
        const backButton = document.querySelector('.main-header .back-btn');
        if (backButton) {
            backButton.style.visibility = 'visible';
            backButton.onclick = function(e) {
                e.preventDefault();
                showFileBrowser(server, path);
            };
        }
        
    } catch (error) {
        console.error('Error loading files list:', error);
        filesContainer.innerHTML = `
            <div class="error">
                <h3>Ошибка загрузки списка файлов</h3>
                <p>${error.message}</p>
                <button onclick="showFileBrowser('${server}', '${path}')" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Назад
                </button>
            </div>
        `;
    }
}

async function loadFilesContent() {
    const textarea = document.getElementById('allFilesTextarea');
    if (!textarea) {
        showNotification('Ошибка: textarea не найден', 'error');
        return;
    }
    
    const filePaths = textarea.value.split('\n')
        .map(path => path.trim())
        .filter(path => path.length > 0);
    
    if (filePaths.length === 0) {
        showNotification('Нет файлов для чтения', 'error');
        return;
    }
    
    console.log('Loading content for', filePaths.length, 'files');
    
    const currentServer = '<?php echo escapeOutput($currentServer); ?>';
    if (!currentServer) {
        showNotification('Ошибка: сервер не выбран', 'error');
        return;
    }
    
    const resultContainer = document.getElementById('filesContentResult');
    if (resultContainer) {
        resultContainer.style.display = 'block';
        resultContainer.innerHTML = `
            <div style="text-align: center; padding: 30px;">
                <div class="loader"></div> 
                <p>Чтение ${filePaths.length} файлов...</p>
            </div>
        `;
    }
    
    try {
        const response = await fetch(`?action=read_multiple_files&server=${encodeURIComponent(currentServer)}&ajax=1`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                files: filePaths
            })
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error ${response.status}: ${errorText}`);
        }
        
        const result = await response.json();
        console.log('Files content result:', result);
        
        // Просто показываем textarea с JSON: ключи = пути, значения = контент
        if (resultContainer) {
            resultContainer.innerHTML = `
                <div style="margin-top: 20px;">
                    <textarea id="resultJsonTextarea" class="files-textarea json-textarea" rows="30" readonly>${escapeHtml(JSON.stringify(result, null, 2))}</textarea>
                </div>
            `;
        }
        
        showNotification(`Прочитано ${Object.keys(result).length} файлов`, 'success');
        
    } catch (error) {
        console.error('Error loading files content:', error);
        if (resultContainer) {
            resultContainer.innerHTML = `
                <div class="error">
                    <h3>Ошибка чтения файлов</h3>
                    <p>${error.message}</p>
                </div>
            `;
        }
        showNotification('Ошибка при чтении файлов: ' + error.message, 'error');
    }
}

function copyResultJson() {
    const textarea = document.getElementById('resultJsonTextarea');
    if (textarea) {
        textarea.select();
        document.execCommand('copy');
        showNotification('JSON скопирован в буфер обмена!', 'success');
    }
}

function downloadResultJson() {
    const textarea = document.getElementById('resultJsonTextarea');
    if (textarea) {
        const content = textarea.value;
        const blob = new Blob([content], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'files_content.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showNotification('JSON файл скачан!', 'success');
    }
}

async function loadFileContent(server, filePath, fileName) {
    console.log('Loading file content:', filePath);
    
    const contentArea = document.getElementById('contentArea');
    const fileBrowser = document.getElementById('fileBrowser');
    
    if (fileBrowser) {
        fileBrowser.style.display = 'none';
    }
    
    let fileContainer = document.getElementById('fileContentContainer');
    if (!fileContainer) {
        fileContainer = document.createElement('div');
        fileContainer.id = 'fileContentContainer';
        contentArea.appendChild(fileContainer);
    }
    
    fileContainer.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="loader"></div> 
            <p>Загрузка файла...</p>
        </div>
    `;
    fileContainer.style.display = 'block';
    
    try {
        const response = await fetch(`?action=view_file&server=${encodeURIComponent(server)}&file_path=${encodeURIComponent(filePath)}&ajax=1`);
        
        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }
        
        const html = await response.text();
        fileContainer.innerHTML = html;
        
        const pathDisplay = document.getElementById('currentPath');
        if (pathDisplay) {
            pathDisplay.innerHTML = `<i class="fas fa-file"></i> ${filePath}`;
        }
        
        const backButton = document.querySelector('.main-header .back-btn');
        if (backButton) {
            backButton.style.visibility = 'visible';
            backButton.onclick = function(e) {
                e.preventDefault();
                showFileBrowser(server, dirname(filePath));
            };
        }
        
    } catch (error) {
        console.error('Error loading file:', error);
        fileContainer.innerHTML = `
            <div class="error">
                <h3>Ошибка загрузки файла</h3>
                <p>${error.message}</p>
                <button onclick="showFileBrowser('${server}', '${dirname(filePath)}')" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Назад к списку файлов
                </button>
            </div>
        `;
    }
}

function dirname(path) {
    return path.split('/').slice(0, -1).join('/') || '/';
}

function showFileBrowser(server, path) {
    console.log('Showing file browser for path:', path);
    
    const fileContainer = document.getElementById('fileContentContainer');
    if (fileContainer) {
        fileContainer.style.display = 'none';
    }
    
    const filesContainer = document.getElementById('allFilesContainer');
    if (filesContainer) {
        filesContainer.style.display = 'none';
    }
    
    const resultContainer = document.getElementById('filesContentResult');
    if (resultContainer) {
        resultContainer.style.display = 'none';
    }
    
    const fileBrowser = document.getElementById('fileBrowser');
    if (fileBrowser) {
        fileBrowser.style.display = 'block';
        loadDirectory(server, path);
    }
    
    const pathDisplay = document.getElementById('currentPath');
    if (pathDisplay) {
        pathDisplay.innerHTML = `<i class="fas fa-folder"></i> ${path}`;
    }
    
    const backButton = document.querySelector('.main-header .back-btn');
    if (backButton) {
        if (path === '/var' || path === '/') {
            backButton.style.visibility = 'hidden';
        } else {
            backButton.style.visibility = 'visible';
            backButton.onclick = function(e) {
                e.preventDefault();
                showFileBrowser(server, dirname(path));
            };
        }
    }
}

async function loadTree(server, path) {
    const treeContainer = document.getElementById('serverTree');
    if (!treeContainer) return;
    
    console.log('Loading tree for path:', path);
    
    try {
        const response = await fetch(`?action=get_tree&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}&ajax=1`);
        
        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Tree data:', data);
        
        renderSimpleTree(treeContainer, data, server);
        
    } catch (error) {
        console.error('Error loading tree:', error);
        treeContainer.innerHTML = `<div class="error">Ошибка загрузки дерева: ${error.message}</div>`;
    }
}

function renderSimpleTree(container, node, server) {
    container.innerHTML = '';
    
    const ul = document.createElement('ul');
    ul.className = 'simple-tree';
    
    if (node.children && node.children.length > 0) {
        node.children.forEach(child => {
            ul.appendChild(window.createTreeElement(child, server));
        });
    } else {
        const li = document.createElement('li');
        li.style.padding = '10px';
        li.style.color = '#718096';
        li.textContent = 'Нет файлов или папок';
        ul.appendChild(li);
    }
    
    container.appendChild(ul);
}

async function toggleSimpleDirectory(item, arrow, li, server) {
    const childrenContainer = li.querySelector('.tree-children');
    const isExpanded = childrenContainer.classList.contains('expanded');
    
    if (isExpanded) {
        arrow.innerHTML = '▶';
        childrenContainer.classList.remove('expanded');
        childrenContainer.innerHTML = '';
        return;
    }
    
    arrow.innerHTML = '<span class="loader" style="width: 12px; height: 12px; display: inline-block;"></span>';
    
    try {
        const response = await fetch(`?action=get_tree&server=${encodeURIComponent(server)}&path=${encodeURIComponent(item.path)}&ajax=1`);
        
        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }
        
        const data = await response.json();
        
        arrow.innerHTML = '▼';
        childrenContainer.classList.add('expanded');
        
        if (data.children && data.children.length > 0) {
            const ul = document.createElement('ul');
            ul.className = 'simple-tree';
            ul.style.marginLeft = '20px';
            
            data.children.forEach(child => {
                const childElement = window.createTreeElement(child, server);
                ul.appendChild(childElement);
            });
            
            childrenContainer.appendChild(ul);
        } else {
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
        errorMsg.textContent = 'Ошибка загрузки';
        childrenContainer.appendChild(errorMsg);
    }
}

function loadDirectory(server, path) {
    const browser = document.getElementById('fileBrowser');
    if (!browser) return;
    
    console.log('Loading directory:', path);
    
    browser.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loader"></div> Загрузка файлов...</div>';
    
    fetch(`?action=browse&server=${encodeURIComponent(server)}&path=${encodeURIComponent(path)}&ajax=1`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            browser.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading directory:', error);
            browser.innerHTML = `<div class="error">Ошибка загрузки: ${error.message}</div>`;
        });
}

function copyAllFiles() {
    const textarea = document.getElementById('allFilesTextarea');
    if (textarea) {
        textarea.select();
        document.execCommand('copy');
        showNotification('Пути файлов скопированы в буфер обмена!', 'success');
    }
}

function downloadFileList() {
    const textarea = document.getElementById('allFilesTextarea');
    if (textarea) {
        const content = textarea.value;
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'files_list.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showNotification('Список файлов скачан!', 'success');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.backgroundColor = type === 'success' ? '#38a169' : '#e53e3e';
    notification.style.color = 'white';
    notification.style.padding = '15px 20px';
    notification.style.borderRadius = '5px';
    notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    notification.style.zIndex = '1000';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Вспомогательная функция для форматирования байтов
function formatBytes(bytes) {
    if (bytes === 0 || bytes === undefined || bytes === null) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
    </script>
</body>
</html>
