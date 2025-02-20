<?php
require_once "db/Database.php";
$conn = getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id']);
$status = $data['status'];

$response = array('success' => false);

function updateAgendamentoStatus($conn, $id, $status) {
    $sql = "UPDATE agendamentos SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $id);
    return $stmt->execute();
}

function archiveAgendamento($conn, $id) {
    $sql = "UPDATE agendamentos SET arquivado = TRUE WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function unarchiveAgendamento($conn, $id) {
    $sql = "UPDATE agendamentos SET arquivado = FALSE WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

if (updateAgendamentoStatus($conn, $id, $status)) {
    $response['success'] = true;
    if ($status === 'Atendimento Finalizado') {
        if (!archiveAgendamento($conn, $id)) {
            $response['success'] = false;
            $response['message'] = 'Erro ao arquivar o agendamento.';
        } else {
            $response['archived'] = true;
        }
    } elseif ($status === 'Aguardando Atendimento' || $status === 'Atendimento Iniciado') {
        if (!unarchiveAgendamento($conn, $id)) {
            $response['success'] = false;
            $response['message'] = 'Erro ao desarquivar o agendamento.';
        } else {
            $response['unarchived'] = true;
        }
    }
} else {
    $response['message'] = 'Erro ao atualizar o status do agendamento.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>