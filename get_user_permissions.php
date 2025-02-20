<?php
session_start();
require_once "db/Database.php";

// Verifica se o usuário está logado
if (!isset($_SESSION['login_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verifica se o ID do usuário foi fornecido
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do usuário não fornecido']);
    exit;
}

$conn = getConnection();
$user_id = intval($_GET['user_id']);

// Buscar informações do usuário
$sql = "SELECT super_admin FROM admin_log WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Se o usuário for super admin, retorna todas as permissões possíveis
if ($user && $user['super_admin']) {
    $permissions = ['inicio', 'kanban', 'agendamentos', 'cadastro', 'servicos', 'horarios', 'atendentes', 'usuarios', 'customizacao', 'configuracoes', 'relatorios'];
} else {
    // Buscar permissões específicas do usuário
    $sql = "SELECT tab_name FROM user_permissions WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['tab_name'];
    }
}

// Retornar as permissões em formato JSON
header('Content-Type: application/json');
echo json_encode($permissions);
