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
        $pwd = trim($this->executeCommand("cd " . escapeshellarg($path) . " && pwd 2>&1"));
        
        // Получаем список файлов
        $lsOutput = $this->executeCommand("cd " . escapeshellarg($path) . " && ls -la --group-directories-first 2>&1");
        
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
            throw new Exception("Файл не найден или это директория: " . $path);
        }
        
        // Читаем файл как текст (все файлы читаем как текстовые)
        $content = $this->executeCommand("cat " . escapeshellarg($path) . " 2>&1");
        
        // Получаем информацию о файле
        $size = trim($this->executeCommand("stat -c%s " . escapeshellarg($path) . " 2>/dev/null || echo '0'"));
        $lines = trim($this->executeCommand("wc -l < " . escapeshellarg($path) . " 2>/dev/null || echo '0'"));
        
        return [
            'type' => 'text',
            'content' => $content,
            'size' => (int)$size,
            'lines' => (int)$lines,
            'path' => $path
        ];
    }
    
    public function listAllFilesFiltered($path) {
        $path = $this->sanitizePath($path);
        
        // Команда для рекурсивного поиска всех файлов, исключая скрытые
        $command = "find " . escapeshellarg($path) . " -type f ! -name '.*' ! -path '*/.*' 2>/dev/null | sort";
        $output = $this->executeCommand($command);
        
        $files = explode("\n", trim($output));
        $filteredFiles = [];
        
        foreach ($files as $file) {
            $file = trim($file);
            if (empty($file)) continue;
            
            $filteredFiles[] = $file;
        }
        
        // Ограничиваем количество
        if (count($filteredFiles) > 1000) {
            $filteredFiles = array_slice($filteredFiles, 0, 1000);
        }
        
        return $filteredFiles;
    }
    
    public function readMultipleFiles($filePaths) {
        // Возвращаем простой объект: ключ = путь к файлу, значение = контент
        $results = [];
        
        foreach ($filePaths as $filePath) {
            $filePath = trim($filePath);
            if (empty($filePath)) continue;
            
            try {
                $fileData = $this->readFile($filePath);
                $results[$filePath] = $fileData['content'] ?? '';
            } catch (Exception $e) {
                // При ошибке сохраняем сообщение об ошибке
                $results[$filePath] = 'ERROR: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    public function getFileInfo($path) {
        $path = $this->sanitizePath($path);
        
        $info = $this->executeCommand("stat -c '%n|%s|%U|%G|%a|%y' " . escapeshellarg($path) . " 2>&1");
        $parts = explode('|', trim($info));
        
        if (count($parts) < 6) {
            throw new Exception("Не удалось получить информацию о файле");
        }
        
        list($name, $size, $owner, $group, $perms, $mtime) = $parts;
        
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
