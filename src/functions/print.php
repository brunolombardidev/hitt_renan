<?php
require_once "../../db/Database.php";
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$conn = getConnection();

// Função para obter o agendamento pelo ID
function getAgendamentoById($conn, $id) {
    $sql = "SELECT * FROM agendamentos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$agendamento = getAgendamentoById($conn, $id);
if (!$agendamento) {
    die("Agendamento não encontrado.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Agendamento</title>
    <style>
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            background-color: #f5f5dc; /* Fundo simulando papel térmico */
            margin: 0;
            padding: 10px;
        }
        .cupom {
            width: 300px;
            margin: 0 auto;
            padding: 10px;
            border: 1px dashed #000; /* Borda para delimitar o cupom */
            background-color: #fff;
        }
        .cupom h1, .cupom p {
            text-align: center;
            margin: 0;
            padding: 5px 0;
        }
        .linha {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .barcode {
            text-align: center;
            margin-top: 15px;
        }
        .barcode img {
            max-width: 100%;
        }
        .cupom .logo {
            max-width: 120px;
            margin: 10px auto;
            display: block;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="cupom">
        <?php
        // Carregar configurações de customização
        $customizacao = json_decode(@file_get_contents('customizacao.json'), true);
        ?>
        <img src="<?php echo isset($customizacao['dashboard_logo_url']) ? $customizacao['dashboard_logo_url'] : 'https://facilitieshelping.com.br/agendamento/assets/img/logotipo-fh-azul300x105.png'; ?>" 
             alt="Logo" 
             class="logo"
             tyle="height: 120px;">
        <h1>Agendamento</h1>
        <div class="linha"></div>
        <p><strong>ID:</strong> <?php echo $agendamento['id']; ?></p>
        <p><strong>Nome:</strong> <?php echo $agendamento['nome']; ?></p>
        <p><strong>Telefone:</strong> <?php echo $agendamento['telefone']; ?></p>
        <p><strong>Data e Hora:</strong> <?php echo date('d/m/Y H:i', strtotime($agendamento['data_hora'])); ?></p>
        <p><strong>Serviço:</strong> <?php echo $agendamento['servico']; ?></p>
        <p><strong>Atendente:</strong> <?php echo $agendamento['atendente']; ?></p>
        <div class="linha"></div>
        <p>Obrigado pela sua preferência!</p>
        <div class="barcode">
            <?php 
            $barcodeValue = str_pad($agendamento['id'], 8, "0", STR_PAD_LEFT); 
            echo "<img src='https://barcode.tec-it.com/barcode.ashx?data={$barcodeValue}&code=Code128&dpi=96' alt='Código de Barras'>";
            ?>
        </div>
    </div>
</body>
</html>