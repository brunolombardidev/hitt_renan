<?php

class Database {
    private static $instance = null;
    private $conn;
    
    // Configurações do banco de dados
    private $config = [
        'host' => 'localhost',
        'username' => 'u983967743_adm_prime',
        'password' => '@2025f01h23R',
        'database' => 'u983967743_bd_facelities',
        'charset' => 'utf8mb4',
        'debug' => true,
        'middlewares' => 'apiKeyAuth',
        'apiKeyAuth.keys' => 'c0978f39bfd63ce3da906f6f297c5408'
    ];

    private function __construct() {
        try {
            $this->conn = mysqli_connect(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database']
            );

            if (!$this->conn) {
                throw new Exception("Não é possível se conectar ao banco de dados: " . mysqli_connect_error());
            }

            mysqli_set_charset($this->conn, $this->config['charset']);

        } catch (Exception $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }

    // Previne clonagem do objeto
    private function __clone() {}

    // Método para obter instância única
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Obtém a conexão
    public function getConnection() {
        return $this->conn;
    }

    // Obtém configurações
    public function getConfig($key = null) {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? null;
    }

    // Fecha a conexão
    public function closeConnection() {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }

    // Destrutor
    public function __destruct() {
        $this->closeConnection();
    }
}

// Função helper para obter conexão rapidamente
if (!function_exists('getConnection')) {
    function getConnection() {
        return Database::getInstance()->getConnection();
    }
} 