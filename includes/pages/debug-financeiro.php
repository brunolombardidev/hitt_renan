<?php
if (!defined('ABSPATH')) {
    exit;
}

// Script de debug para verificar registros financeiros
global $wpdb;

echo "<h2>Debug - Registros Financeiros</h2>";

// Verificar se as tabelas existem
$table_financeiro = $wpdb->prefix . 'inbwp_financeiro';
$table_negocios = $wpdb->prefix . 'inbwp_negocios';

$financeiro_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_financeiro'") == $table_financeiro);
$negocios_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_negocios'") == $table_negocios);

echo "<p><strong>Tabela financeiro existe:</strong> " . ($financeiro_exists ? 'SIM' : 'NÃO') . "</p>";
echo "<p><strong>Tabela negócios existe:</strong> " . ($negocios_exists ? 'SIM' : 'NÃO') . "</p>";

if ($financeiro_exists) {
    // Mostrar estrutura da tabela
    $columns = $wpdb->get_results("DESCRIBE $table_financeiro");
    echo "<h3>Estrutura da tabela financeiro:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Contar registros
    $total_registros = $wpdb->get_var("SELECT COUNT(*) FROM $table_financeiro");
    echo "<p><strong>Total de registros financeiros:</strong> $total_registros</p>";
    
    if ($total_registros > 0) {
        // Mostrar últimos registros
        $registros = $wpdb->get_results("SELECT * FROM $table_financeiro ORDER BY created_at DESC LIMIT 10");
        echo "<h3>Últimos 10 registros:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Negócio ID</th><th>Tipo</th><th>Descrição</th><th>Valor</th><th>Usuário ID</th><th>Status</th><th>Data Criação</th></tr>";
        foreach ($registros as $registro) {
            echo "<tr>";
            echo "<td>{$registro->id}</td>";
            echo "<td>{$registro->negocio_id}</td>";
            echo "<td>{$registro->tipo}</td>";
            echo "<td>{$registro->descricao}</td>";
            echo "<td>R$ " . number_format($registro->valor, 2, ',', '.') . "</td>";
            echo "<td>{$registro->usuario_id}</td>";
            echo "<td>{$registro->status}</td>";
            echo "<td>{$registro->created_at}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

if ($negocios_exists) {
    $total_negocios = $wpdb->get_var("SELECT COUNT(*) FROM $table_negocios");
    echo "<p><strong>Total de negócios:</strong> $total_negocios</p>";
    
    if ($total_negocios > 0) {
        // Mostrar últimos negócios
        $negocios = $wpdb->get_results("SELECT * FROM $table_negocios ORDER BY created_at DESC LIMIT 5");
        echo "<h3>Últimos 5 negócios:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Cliente ID</th><th>Servidor ID</th><th>Valor Total</th><th>Status</th><th>Data Criação</th></tr>";
        foreach ($negocios as $negocio) {
            echo "<tr>";
            echo "<td>{$negocio->id}</td>";
            echo "<td>{$negocio->cliente_id}</td>";
            echo "<td>{$negocio->servidor_id}</td>";
            echo "<td>R$ " . number_format($negocio->valor_total, 2, ',', '.') . "</td>";
            echo "<td>{$negocio->status}</td>";
            echo "<td>{$negocio->created_at}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Verificar logs de erro
echo "<h3>Logs de erro recentes (relacionados ao INBWP):</h3>";
$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $lines = explode("\n", $logs);
    $inbwp_logs = array_filter($lines, function($line) {
        return strpos($line, 'INBWP') !== false;
    });
    
    if (!empty($inbwp_logs)) {
        echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 300px; overflow-y: auto;'>";
        echo implode("\n", array_slice($inbwp_logs, -20)); // Últimas 20 linhas
        echo "</pre>";
    } else {
        echo "<p>Nenhum log INBWP encontrado.</p>";
    }
} else {
    echo "<p>Arquivo de log não encontrado. Certifique-se de que WP_DEBUG_LOG está habilitado.</p>";
}
?>