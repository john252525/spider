<?php
/**
 * Функции для рендеринга HTML
 */

class Renderer {
    
    /**
     * Рендеринг файлового браузера
     */
    public static function renderFileBrowser($listing, $serverName) {
        $html = '<div class="file-grid">';
        
        // Кнопка "Наверх" если не корень
        if ($listing['current_path'] !== '/') {
            $parentPath = dirname($listing['current_path']);
            $html .= '
            <a href="javascript:void(0)" onclick="loadAllFiles(\'' . htmlspecialchars($serverName) . '\', \'' . htmlspecialchars($parentPath) . '\')" class="file-item">
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
            
            if ($name === '.' || $name === '..') continue;
            
            // Фильтр: скрыть файлы/папки начинающиеся с точки
            if (strpos($name, '.') === 0) continue;
            
            $fullPath = ($listing['current_path'] === '/') ? '/' . $name : $listing['current_path'] . '/' . $name;
            
            if ($isDir) {
                $html .= '
                <a href="javascript:void(0)" onclick="loadAllFiles(\'' . htmlspecialchars($serverName) . '\', \'' . htmlspecialchars($fullPath) . '\')" class="file-item">
                    <div class="file-icon">📁</div>
                    <div class="file-name">' . htmlspecialchars($name) . '</div>
                </a>';
            } else {
                $html .= '
                <a href="javascript:void(0)" onclick="loadFileContent(\'' . htmlspecialchars($serverName) . '\', \'' . htmlspecialchars($fullPath) . '\', \'' . htmlspecialchars($name) . '\')" class="file-item">
                    <div class="file-icon">' . getFileIcon($name, false) . '</div>
                    <div class="file-name">' . htmlspecialchars($name) . '</div>
                </a>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Рендеринг содержимого файла
     */
    public static function renderFileContent($fileData, $serverName) {
        $html = '
        <div class="file-info-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">
                    <i class="fas fa-file"></i> 
                    ' . htmlspecialchars(basename($fileData['path'] ?? '')) . '
                </h3>
                <button onclick="showFileBrowser(\'' . htmlspecialchars($serverName) . '\', \'' . htmlspecialchars(dirname($fileData['path'])) . '\')" class="back-btn btn-sm">
                    <i class="fas fa-arrow-left"></i> Назад
                </button>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Путь:</span>
                    <span class="info-value">' . htmlspecialchars(dirname($fileData['path'] ?? '')) . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Размер:</span>
                    <span class="info-value">' . formatSize($fileData['size'] ?? 0) . '</span>
                </div>';
                
        if (isset($fileData['lines'])) {
            $html .= '
                <div class="info-item">
                    <span class="info-label">Строк:</span>
                    <span class="info-value">' . $fileData['lines'] . '</span>
                </div>';
        }
        
        $html .= '
            </div>
        </div>';
        
        $html .= '
        <div class="file-content ' . (($fileData['type'] ?? '') === 'binary' ? 'binary' : '') . '">';
        
        if (($fileData['type'] ?? '') === 'binary') {
            $html .= '⚠️ Это бинарный файл. Не удалось отобразить содержимое.<br>';
            $html .= 'Размер: ' . formatSize($fileData['size'] ?? 0) . '<br>';
        } else {
            $html .= htmlspecialchars($fileData['content'] ?? '');
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

