<?php
require_once 'db/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'];

    if (isset($id)) {
        $conn = getConnection();
        $sql = "UPDATE agendamentos SET arquivado = TRUE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Erro ao arquivar o agendamento."]);
        }
        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(["success" => false, "message" => "ID do agendamento é necessário."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
}
?>