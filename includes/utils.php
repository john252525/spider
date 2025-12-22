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
