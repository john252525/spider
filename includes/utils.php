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

function isImageFile($filename) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $imageExtensions);
}

function isTextFile($filename) {
    $textExtensions = ['txt', 'php', 'js', 'css', 'html', 'htm', 'json', 'xml', 'yml', 'yaml', 'md', 'log', 'conf', 'ini'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $textExtensions);
}

function getFileIcon($filename, $isDir = false) {
    if ($isDir) return '📁';
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'php' => '🐘', 'js' => '📜', 'html' => '🌐', 'css' => '🎨',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'pdf' => '📕', 'doc' => '📄', 'docx' => '📄',
        'zip' => '📦', 'tar' => '📦', 'gz' => '📦',
        'txt' => '📝', 'md' => '📝',
        'json' => '{}', 'xml' => '</>',
        'sql' => '🗃️', 'log' => '📋'
    ];
    
    return $icons[$ext] ?? '📄';
}

function escapeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
