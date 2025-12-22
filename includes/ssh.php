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
        
        // Отладочная информация
        error_log("SSHManager::listDirectory called with path: '{$path}'");
        
        // Получаем информацию о текущей директории
        $pwd = trim($this->executeCommand("cd " . escapeshellarg($path) . " && pwd 2>&1"));
        error_log("Current directory after cd: '{$pwd}'");
        
        // Получаем список файлов
        $lsOutput = $this->executeCommand("cd " . escapeshellarg($path) . " && ls -la --group-directories-first 2>&1");
        error_log("ls output length: " . strlen($lsOutput));
        
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




    public function listDirectoryRecursive($path, $maxDepth = 10) {
        $path = $this->sanitizePath($path);
        
        // Команда для рекурсивного поиска всех файлов
        $command = "find " . escapeshellarg($path) . " -type f 2>/dev/null | head -1000";
        $output = $this->executeCommand($command);
        
        $files = explode("\n", trim($output));
        $result = [];
        
        foreach ($files as $file) {
            $file = trim($file);
            if (!empty($file)) {
                $result[] = $file;
            }
        }
        
        // Если слишком много файлов, ограничиваем
        if (count($result) > 500) {
            $result = array_slice($result, 0, 500);
            $result[] = "... и ещё " . (count($files) - 500) . " файлов";
        }
        
        return $result;
    }
    
    public function listDirectoryTree($path, $maxDepth = 10) {
        $path = $this->sanitizePath($path);
        
        // Команда для получения дерева файлов
        $command = "find " . escapeshellarg($path) . " -type f 2>/dev/null | head -1000";
        $output = $this->executeCommand($command);
        
        $files = explode("\n", trim($output));
        $result = [];
        
        foreach ($files as $file) {
            $file = trim($file);
            if (!empty($file)) {
                // Получаем информацию о файле
                $info = $this->getFileInfo($file);
                $result[] = [
                    'path' => $file,
                    'size' => $info['size'] ?? 0,
                    'type' => $info['type'] ?? 'unknown'
                ];
            }
        }
        
        // Если слишком много файлов, ограничиваем
        if (count($result) > 500) {
            $result = array_slice($result, 0, 500);
            $result[] = [
                'path' => "... и ещё " . (count($files) - 500) . " файлов",
                'size' => 0,
                'type' => 'info'
            ];
        }
        
        return $result;
    }





    
    public function listAllFilesFiltered($path) {
        $path = $this->sanitizePath($path);
        
        // Получаем правила .gitignore если есть
        $gitignoreRules = [];
        try {
            $gitignorePath = $path . '/.gitignore';
            if ($this->fileExists($gitignorePath)) {
                $gitignoreContent = $this->executeCommand("cat " . escapeshellarg($gitignorePath) . " 2>/dev/null || echo ''");
                $gitignoreRules = $this->parseGitignore($gitignoreContent, $path);
            }
        } catch (Exception $e) {
            // .gitignore не найден или ошибка чтения - игнорируем
        }
        
        // Сначала получаем все файлы и папки отдельно для сортировки
        $commandFiles = "find " . escapeshellarg($path) . " -type f 2>/dev/null";
        $commandDirs = "find " . escapeshellarg($path) . " -type d 2>/dev/null";
        
        $filesOutput = $this->executeCommand($commandFiles);
        $dirsOutput = $this->executeCommand($commandDirs);
        
        $allItems = [];
        
        // Обрабатываем папки
        $dirs = explode("\n", trim($dirsOutput));
        foreach ($dirs as $dir) {
            $dir = trim($dir);
            if (empty($dir) || $dir === $path) continue;
            
            // Фильтр: скрыть папки начинающиеся с точки
            if (basename($dir)[0] === '.') {
                continue;
            }
            
            // Фильтр: .gitignore правила для папок
            $shouldExclude = false;
            foreach ($gitignoreRules as $pattern) {
                if (fnmatch($pattern, $dir) || fnmatch($pattern . '/*', $dir)) {
                    $shouldExclude = true;
                    break;
                }
            }
            if ($shouldExclude) {
                continue;
            }
            
            $allItems[] = [
                'path' => $dir,
                'type' => 'directory',
                'name' => basename($dir)
            ];
        }
        
        // Обрабатываем файлы
        $files = explode("\n", trim($filesOutput));
        foreach ($files as $file) {
            $file = trim($file);
            if (empty($file)) continue;
            
            // Фильтр: скрыть файлы начинающиеся с точки
            if (basename($file)[0] === '.') {
                continue;
            }
            
            // Фильтр: .gitignore правила
            $shouldExclude = false;
            foreach ($gitignoreRules as $pattern) {
                if (fnmatch($pattern, $file)) {
                    $shouldExclude = true;
                    break;
                }
            }
            if ($shouldExclude) {
                continue;
            }
            
            $allItems[] = [
                'path' => $file,
                'type' => 'file',
                'name' => basename($file)
            ];
        }
        
        // Сортировка: сначала папки, потом файлы, всё по алфавиту
        usort($allItems, function($a, $b) {
            // Сначала сортируем по типу (папки перед файлами)
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            // Затем по имени (регистронезависимо)
            return strcasecmp($a['name'], $b['name']);
        });
        
        // Преобразуем в простой массив путей (только для файлов, как в textarea)
        $filePaths = [];
        foreach ($allItems as $item) {
            if ($item['type'] === 'file') {
                $filePaths[] = ['path' => $item['path']];
            }
        }
        
        // Ограничиваем количество
        if (count($filePaths) > 1000) {
            $filePaths = array_slice($filePaths, 0, 1000);
        }
        
        return $filePaths;
    }






    
    private function fileExists($path) {
        $result = trim($this->executeCommand(
            "if [ -f " . escapeshellarg($path) . " ]; then echo '1'; else echo '0'; fi"
        ));
        return $result === '1';
    }
    
    private function parseGitignore($content, $basePath) {
        $rules = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Пропускаем пустые строки и комментарии
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Удаляем trailing spaces
            $line = rtrim($line);
            
            // Обработка правил
            if ($line[0] === '/') {
                // Абсолютный путь от корня репозитория
                $rules[] = $basePath . $line;
            } else if (strpos($line, '/') !== false) {
                // Относительный путь с поддиректориями
                $rules[] = $basePath . '/' . $line;
            } else {
                // Просто шаблон файла
                $rules[] = $basePath . '/*/' . $line;
                $rules[] = $basePath . '/' . $line;
            }
        }
        
        return $rules;
    }






}
