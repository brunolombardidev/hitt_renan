<?php
require_once "db/Database.php";
require_once "src/functions/relatorios.php";

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $conn = getConnection();
    if (!$conn) {
        throw new Exception("Erro ao conectar ao banco de dados");
    }

    $periodo = isset($_GET['periodo']) ? intval($_GET['periodo']) : 30;
    
    error_log("Buscando dados para período: " . $periodo);

    $data = [
        'success' => true,
        'profissionais' => [],
        'servicos' => [],
        'faturamento' => [],
        'servicosProfissional' => []
    ];

    // Buscar dados dos profissionais mais requisitados
    $result = getProfissionaisMaisRequisitados($conn, $periodo);
    if (!$result) {
        throw new Exception("Erro ao buscar profissionais: " . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        $data['profissionais'][] = $row;
    }

    // Buscar dados dos serviços mais requisitados
    $result = getServicosMaisRequisitados($conn, $periodo);
    while ($row = $result->fetch_assoc()) {
        $data['servicos'][] = $row;
    }

    // Buscar dados de faturamento
    $result = getFaturamentoProjetadoRealizado($conn, $periodo);
    while ($row = $result->fetch_assoc()) {
        $data['faturamento'][] = $row;
    }

    // Buscar dados de serviços por profissional
    $result = getServicosPorProfissional($conn, $periodo);
    while ($row = $result->fetch_assoc()) {
        $data['servicosProfissional'][] = $row;
    }

    echo json_encode($data);

} catch (Exception $e) {
    error_log("Erro em get_relatorios.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 