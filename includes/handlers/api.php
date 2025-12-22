<?php
/**
 * API обработчики для AJAX запросов
 */

class ApiHandler {
    
    /**
     * Обработка запроса на чтение нескольких файлов
     */
    public static function handleReadMultipleFiles() {
        // Очищаем любой вывод перед отправкой JSON
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        // Устанавливаем заголовки
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $serverName = $_GET['server'] ?? $_SESSION['current_server'] ?? '';
            
            // Читаем JSON тело запроса
            $jsonInput = file_get_contents('php://input');
            if (empty($jsonInput)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Empty request body'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $input = json_decode($jsonInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON: ' . json_last_error_msg()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $files = $input['files'] ?? [];
            
            if (empty($serverName)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Server not specified'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (empty($files) || !is_array($files)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Files list is empty or invalid'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            
            $result = $ssh->readMultipleFiles($files);
            
            // Отправляем JSON без PRETTY_PRINT для больших данных (может вызывать проблемы)
            echo json_encode([
                'success' => true,
                'files' => $result,
                'total' => count($result),
                'successful' => count(array_filter($result, function($item) {
                    return isset($item['success']) && $item['success'] === true;
                }))
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на получение дерева файлов
     */
    public static function handleGetTree($serverName, $path, $startPath) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            $listing = $ssh->listDirectory($path);
            
            // Формируем данные для дерева
            $tree = [
                'path' => $path,
                'name' => basename($path) ?: $path,
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
                
                // Фильтр: скрыть файлы/папки начинающиеся с точки
                if (strpos($name, '.') === 0) continue;
                
                // Формируем правильный путь
                $fullPath = ($path === '/') ? '/' . $name : $path . '/' . $name;
                
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
            
            // Сортировка: сначала папки, потом файлы, всё по алфавиту
            usort($tree['children'], function($a, $b) {
                if ($a['type'] !== $b['type']) {
                    return $a['type'] === 'directory' ? -1 : 1;
                }
                return strcasecmp($a['name'], $b['name']);
            });
            
            echo json_encode($tree, JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на получение списка всех файлов
     */
    public static function handleListAllFilesFiltered($serverName, $path) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            
            $files = $ssh->listAllFilesFiltered($path);
            
            echo json_encode([
                'success' => true,
                'files' => $files
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на просмотр файла
     */
    public static function handleViewFile($serverName, $filePath) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            
            $fileData = $ssh->readFile($filePath);
            $html = Renderer::renderFileContent($fileData, $serverName);
            echo $html;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo '<div class="error">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        exit;
    }
    
    /**
     * Обработка запроса на просмотр директории
     */
    public static function handleBrowse($serverName, $path) {
        if (ob_get_level()) {
            ob_clean();
        } else {
            ob_start();
        }
        
        try {
            $ssh = new SSHManager($serverName);
            $ssh->connect();
            $listing = $ssh->listDirectory($path);
            
            $html = Renderer::renderFileBrowser($listing, $serverName);
            echo $html;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo '<div class="error">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        exit;
    }
}

