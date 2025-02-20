<?php
error_reporting(0); // Disable error reporting for production
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db/Database.php';

try {
    if (!isset($_POST['id'])) {
        throw new Exception('ID não fornecido');
    }

    $conn = getConnection();
    
    if (!$conn) {
        throw new Exception("Conexão com o banco de dados falhou");
    }

    $id = $_POST['id'];
    $sql = "UPDATE agendamentos SET visualizado = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar consulta: " . $stmt->error);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
