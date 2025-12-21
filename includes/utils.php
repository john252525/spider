<?php
// Вспомогательные функции
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function formatPermissions($perms) {
    if (!is_numeric($perms)) return $perms;
    $symbolic = '';
    $symbolic .= ($perms & 0x0400) ? 'r' : '-';
    $symbolic .= ($perms & 0x0200) ? 'w' : '-';
    $symbolic .= ($perms & 0x0100) ? 'x' : '-';
    $symbolic .= ($perms & 0x0040) ? 'r' : '-';
    $symbolic .= ($perms & 0x0020) ? 'w' : '-';
    $symbolic .= ($perms & 0x0010) ? 'x' : '-';
    $symbolic .= ($perms & 0x0004) ? 'r' : '-';
    $symbolic .= ($perms & 0x0002) ? 'w' : '-';
    $symbolic .= ($perms & 0x0001) ? 'x' : '-';
    return $symbolic;
}

function isTextFile($filename) {
    $textExtensions = [
        'txt', 'php', 'js', 'css', 'html', 'htm', 'json', 'xml', 
        'yml', 'yaml', 'md', 'log', 'conf', 'ini', 'env', 'sh', 
        'bash', 'zsh', 'py', 'rb', 'java', 'c', 'cpp', 'h', 'hpp',
        'sql', 'csv', 'tsv', 'xml', 'xsl', 'xslt', 'xsd',
        'toml', 'cfg', 'properties', 'gitignore', 'dockerfile'
    ];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $textExtensions);
}

function isBinaryFile($fileType) {
    $binaryIndicators = [
        'executable',
        'binary',
        'compressed',
        'archive',
        'image',
        'pdf',
        'microsoft',
        'msword',
        'excel',
        'powerpoint',
        'audio',
        'video',
        'octet-stream'
    ];
    
    $fileType = strtolower($fileType);
    foreach ($binaryIndicators as $indicator) {
        if (strpos($fileType, $indicator) !== false) {
            return true;
        }
    }
    return false;
}

function getFileIcon($filename, $isDir = false) {
    if ($isDir) return '📁';
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'php' => '🐘', 'js' => '📜', 'html' => '🌐', 'css' => '🎨',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'svg' => '🖼️', 'bmp' => '🖼️', 'webp' => '🖼️',
        'pdf' => '📕', 'doc' => '📄', 'docx' => '📄', 'odt' => '📄',
        'xls' => '📊', 'xlsx' => '📊', 'ods' => '📊',
        'zip' => '📦', 'tar' => '📦', 'gz' => '📦', '7z' => '📦', 'rar' => '📦',
        'txt' => '📝', 'md' => '📝', 'rtf' => '📝',
        'json' => '{}', 'xml' => '</>', 'yaml' => '⚙️', 'yml' => '⚙️',
        'sql' => '🗃️', 'log' => '📋', 'ini' => '⚙️', 'conf' => '⚙️',
        'sh' => '🐚', 'bash' => '🐚', 'zsh' => '🐚',
        'py' => '🐍', 'rb' => '💎', 'java' => '☕', 'c' => '📟', 'cpp' => '📟',
        'mp3' => '🎵', 'wav' => '🎵', 'mp4' => '🎬', 'avi' => '🎬'
    ];
    
    return $icons[$ext] ?? '📄';
}

function escapeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}
