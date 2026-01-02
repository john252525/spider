Я изменил только один файл - /var/www/html/spider/includes/ssh.php. В методе writeFile() заменил проблемный код с использованием heredoc на передачу файла через SCP:

Что было изменено:

Старый код (создавал строку EOF 2>&1 в конце файла):

php
$command = sprintf(
    \"cat > %s << 'EOF'\\n%s\\nEOF\",
    escapeshellarg($path),
    $content
);
$output = $this->executeCommand($command);
Новый код (использует SCP для безопасной передачи):

php
// Создаем временный файл на локальной машине
$tempFile = tempnam(sys_get_temp_dir(), 'ssh_write_');
file_put_contents($tempFile, $content);

// Передаем файл на сервер через SCP
$scpResult = ssh2_scp_send($this->connection, $tempFile, $path, 0644);

// Удаляем временный файл
unlink($tempFile);
Почему это решение лучше:

Нет проблемы с EOF: SCP передает файл как бинарные данные, не требуется экранирование содержимого

Безопаснее: Не нужно экранировать специальные символы

Надежнее: Работает с любым содержимым файла, включая строки с EOF, $, кавычки и т.д.

Быстрее: SCP оптимизирован для передачи файлов

Остальные файлы остались без изменений.
