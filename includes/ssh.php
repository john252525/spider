<?php
class SSHManager {
    private $connection = null;
    private $serverConfig = null;
    
    public function __construct($serverName) {
        $this->serverConfig = Config::getServer($serverName);
        
        if (!$this->serverConfig) {
            throw new Exception("Сервер '$serverName' не найден в конфигурации");
        }
    }
    
    public function connect() {
        $host = $this->serverConfig['host'];
        $port = $this->serverConfig['port'] ?? 22;
        
        $this->connection = ssh2_connect($host, $port);
        if (!$this->connection) {
            throw new Exception("Не удалось подключиться к $host:$port");
        }
        
        $this->authenticate();
        return $this;
    }
    
    private function authenticate() {
        $user = $this->serverConfig['user'];
        $authenticated = false;
        
        if (isset($this->serverConfig['key_path']) && $this->serverConfig['key_path']) {
            $pubkey = $this->serverConfig['key_path'] . '.pub';
            $privkey = $this->serverConfig['key_path'];
            $passphrase = $this->serverConfig['key_passphrase'] ?? '';
            
            $authenticated = ssh2_auth_pubkey_file(
                $this->connection, $user, $pubkey, $privkey, $passphrase
            );
        } elseif (isset($this->serverConfig['password']) && $this->serverConfig['password']) {
            $authenticated = ssh2_auth_password(
                $this->connection, $user, $this->serverConfig['password']
            );
        }
        
        if (!$authenticated) {
            throw new Exception("Ошибка аутентификации для пользователя $user");
        }
    }
    
    public function executeCommand($command) {
        if (!$this->connection) {
            throw new Exception("Соединение не установлено");
        }
        
        $stream = ssh2_exec($this->connection, $command . ' 2>&1');
        if (!$stream) {
            throw new Exception("Не удалось выполнить команду");
        }
        
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        
        return $output;
    }
    
    public function listDirectory($path) {
        $path = $this->sanitizePath($path);
        
        // Получаем информацию о текущей директории
        $pwd = trim($this->executeCommand("cd " . escapeshellarg($path) . " && pwd"));
        
        // Получаем список файлов
        $lsOutput = $this->executeCommand("cd " . escapeshellarg($path) . " && ls -la --group-directories-first");
        
        return [
            'current_path' => $pwd,
            'listing' => $lsOutput,
            'parent_dir' => dirname($pwd)
        ];
    }
    
    public function readFile($path) {
        $path = $this->sanitizePath($path);
        
        // Проверяем, является ли файлом
        $isFile = trim($this->executeCommand(
            "if [ -f " . escapeshellarg($path) . " ]; then echo '1'; else echo '0'; fi"
        ));
        
        if ($isFile !== '1') {
            throw new Exception("Файл не найден или это директория");
        }
        
        try {
            // Пробуем прочитать файл как текст
            $content = $this->executeCommand("cat " . escapeshellarg($path));
            
            // Получаем информацию о файле
            $size = trim($this->executeCommand("stat -c%s " . escapeshellarg($path)));
            $lines = trim($this->executeCommand("wc -l < " . escapeshellarg($path)));
            
            // Пытаемся определить кодировку
            $encoding = 'binary';
            try {
                $encoding = trim($this->executeCommand(
                    "file -b --mime-encoding " . escapeshellarg($path) . " 2>/dev/null || echo 'binary'"
                ));
            } catch (Exception $e) {
                $encoding = 'unknown';
            }
            
            // Пытаемся определить тип файла
            $fileType = 'text/plain';
            try {
                $fileType = trim($this->executeCommand(
                    "file -bI " . escapeshellarg($path) . " 2>/dev/null || file -b " . escapeshellarg($path) . " || echo 'text/plain'"
                ));
                $fileType = preg_replace('/; charset=.*$/', '', $fileType);
            } catch (Exception $e) {
                $fileType = 'text/plain';
            }
            
            return [
                'type' => 'text',
                'content' => $content,
                'file_type' => $fileType,
                'encoding' => $encoding,
                'size' => $size,
                'lines' => $lines,
                'path' => $path
            ];
            
        } catch (Exception $e) {
            // Если не удалось прочитать как текст, возвращаем информацию о бинарном файле
            $size = trim($this->executeCommand("stat -c%s " . escapeshellarg($path)));
            $fileType = trim($this->executeCommand(
                "file -b " . escapeshellarg($path) . " 2>/dev/null || echo 'binary'"
            ));
            
            return [
                'type' => 'binary',
                'file_type' => $fileType,
                'size' => $size,
                'path' => $path
            ];
        }
    }
    
    public function getFileInfo($path) {
        $path = $this->sanitizePath($path);
        
        $info = $this->executeCommand("stat -c '%n|%s|%U|%G|%a|%y' " . escapeshellarg($path));
        list($name, $size, $owner, $group, $perms, $mtime) = explode('|', trim($info));
        
        $type = trim($this->executeCommand(
            "if [ -d " . escapeshellarg($path) . " ]; then echo 'directory'; " .
            "elif [ -f " . escapeshellarg($path) . " ]; then echo 'file'; " .
            "else echo 'other'; fi"
        ));
        
        return [
            'name' => basename($name),
            'type' => $type,
            'size' => $size,
            'owner' => $owner,
            'group' => $group,
            'permissions' => $perms,
            'modified' => $mtime
        ];
    }
    
    private function sanitizePath($path) {
        $path = trim($path);
        if (empty($path)) {
            return '.';
        }
        return rtrim($path, '/');
    }
    
    public function disconnect() {
        if ($this->connection) {
            ssh2_disconnect($this->connection);
            $this->connection = null;
        }
    }
    
    public function __destruct() {
        $this->disconnect();
    }
}
