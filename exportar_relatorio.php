<?php
require_once "db/Database.php";
require_once "src/functions/relatorios.php";

try {
    $conn = getConnection();
    if (!$conn) {
        throw new Exception("Erro ao conectar ao banco de dados");
    }

    $periodo = isset($_GET['periodo']) ? intval($_GET['periodo']) : 30;

    // Buscar todos os dados
    $profissionais = getProfissionaisMaisRequisitados($conn, $periodo);
    $servicos = getServicosMaisRequisitados($conn, $periodo);
    $faturamento = getFaturamentoProjetadoRealizado($conn, $periodo);
    $servicosProfissional = getServicosPorProfissional($conn, $periodo);

    // Preparar dados para CSV
    $dados = [];
    $dados[] = ['Relatório de ' . $periodo . ' dias - Gerado em ' . date('d/m/Y H:i:s')];
    $dados[] = [''];

    // Profissionais mais requisitados
    $dados[] = ['PROFISSIONAIS MAIS REQUISITADOS'];
    $dados[] = ['Profissional', 'Total Agendamentos', 'Valor Total'];
    while ($row = $profissionais->fetch_assoc()) {
        $dados[] = [
            $row['atendente'],
            $row['total_agendamentos'],
            formatarMoeda($row['valor_total'])
        ];
    }
    $dados[] = [''];

    // Serviços mais requisitados
    $dados[] = ['SERVIÇOS MAIS REQUISITADOS'];
    $dados[] = ['Serviço', 'Total Agendamentos', 'Valor Total'];
    while ($row = $servicos->fetch_assoc()) {
        $dados[] = [
            $row['servico'],
            $row['total_agendamentos'],
            formatarMoeda($row['valor_total'])
        ];
    }
    $dados[] = [''];

    // Faturamento
    $dados[] = ['FATURAMENTO PROJETADO X REALIZADO'];
    $dados[] = ['Data', 'Realizado', 'Projetado'];
    while ($row = $faturamento->fetch_assoc()) {
        $dados[] = [
            $row['data'],
            formatarMoeda($row['realizado']),
            formatarMoeda($row['projetado'])
        ];
    }
    $dados[] = [''];

    // Serviços por profissional
    $dados[] = ['SERVIÇOS POR PROFISSIONAL'];
    $dados[] = ['Profissional', 'Serviço', 'Total Serviços', 'Valor Total'];
    while ($row = $servicosProfissional->fetch_assoc()) {
        $dados[] = [
            $row['atendente'],
            $row['servico'],
            $row['total_servicos'],
            formatarMoeda($row['valor_total'])
        ];
    }

    // Exportar para CSV
    exportarParaCSV(
        $dados,
        ['Relatório Completo - Período: ' . $periodo . ' dias'],
        'relatorio_' . $periodo . 'dias.csv'
    );

} catch (Exception $e) {
    error_log("Erro em exportar_relatorio.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>