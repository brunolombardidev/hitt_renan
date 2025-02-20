<?php
header('Content-Type: text/css');
require_once('../db/Database.php');

// Carregar configurações de customização
$customizacao = json_decode(file_get_contents(__DIR__ . '/../customizacao.json'), true);
?>

:root {
    --font-family: <?php echo $customizacao['font_family'] ?? 'Poppins'; ?>, sans-serif;
    --font-size: <?php echo $customizacao['font_size'] ?? '16px'; ?>;
    --font-weight: <?php echo $customizacao['font_weight'] ?? '400'; ?>;
    --background-color: <?php echo $customizacao['background_color'] ?? '#f8f9fa'; ?>;
    --primary-color: <?php echo $customizacao['primary_color'] ?? '#4789eb'; ?>;
    --navbar-color: <?php echo $customizacao['navbar_color'] ?? '#0d6efd'; ?>;
}

body {
    font-family: var(--font-family);
    font-size: var(--font-size);
    font-weight: var(--font-weight);
    background-color: var(--background-color);
}

/* Estilos de Tabela */
<?php if ($customizacao['table_striped'] ?? false): ?>
.table tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.05);
}
<?php endif; ?>

<?php if ($customizacao['table_hover'] ?? false): ?>
.table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.075);
}
<?php endif; ?>

<?php if ($customizacao['table_responsive'] ?? false): ?>
@media screen and (max-width: 768px) {
    .table-responsive-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
<?php endif; ?>

/* Estilos de Botão */
.btn {
    <?php if ($customizacao['button_style'] == 'modern'): ?>
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    <?php elseif ($customizacao['button_style'] == 'classic'): ?>
    border-width: 2px;
    font-weight: normal;
    text-transform: none;
    <?php elseif ($customizacao['button_style'] == 'minimal'): ?>
    background: none;
    border: 1px solid;
    box-shadow: none;
    font-weight: normal;
    <?php endif; ?>

    <?php if ($customizacao['button_radius'] == 'square'): ?>
    border-radius: 0;
    <?php elseif ($customizacao['button_radius'] == 'rounded'): ?>
    border-radius: 6px;
    <?php elseif ($customizacao['button_radius'] == 'pill'): ?>
    border-radius: 50px;
    <?php endif; ?>
}

/* Navbar */
.navbar {
    background: var(--navbar-color);
}

/* Cores primárias */
.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: <?php echo $customizacao['primary_hover_color'] ?? '#3672c9'; ?>;
    border-color: <?php echo $customizacao['primary_hover_color'] ?? '#3672c9'; ?>;
    transform: translateY(-1px);
}

.btn-primary:active {
    background-color: <?php echo $customizacao['primary_hover_color'] ?? '#3672c9'; ?> !important;
    border-color: <?php echo $customizacao['primary_hover_color'] ?? '#3672c9'; ?> !important;
    transform: translateY(0);
}

/* Cards do Dashboard */
.dashboard-stats .agendamentos-card {
    background: linear-gradient(135deg, 
        <?php echo $customizacao['card_agendamentos_gradient_start'] ?? '#4e54c8'; ?> 0%, 
        <?php echo $customizacao['card_agendamentos_gradient_end'] ?? '#8f94fb'; ?> 100%
    );
}

.dashboard-stats .finalizados-card {
    background: linear-gradient(135deg, 
        <?php echo $customizacao['card_finalizados_gradient_start'] ?? '#11998e'; ?> 0%, 
        <?php echo $customizacao['card_finalizados_gradient_end'] ?? '#38ef7d'; ?> 100%
    );
}

.dashboard-stats .atendentes-card {
    background: linear-gradient(135deg, 
        <?php echo $customizacao['card_atendentes_gradient_start'] ?? '#ff758c'; ?> 0%, 
        <?php echo $customizacao['card_atendentes_gradient_end'] ?? '#ff7eb3'; ?> 100%
    );
}

.dashboard-stats .servicos-card {
    background: linear-gradient(135deg, 
        <?php echo $customizacao['card_servicos_gradient_start'] ?? '#fc466b'; ?> 0%, 
        <?php echo $customizacao['card_servicos_gradient_end'] ?? '#3f5efb'; ?> 100%
    );
}

.dashboard-stats .atendimentos-hoje-card {
    background: linear-gradient(135deg, 
        <?php echo $customizacao['card_atendimentos_hoje_gradient_start'] ?? '#00c6fb'; ?> 0%, 
        <?php echo $customizacao['card_atendimentos_hoje_gradient_end'] ?? '#005bea'; ?> 100%
    );
    color: white;
    transition: transform 0.3s ease;
}

.dashboard-stats .atendimentos-periodo-card {
    background: linear-gradient(135deg, 
        <?php echo $customizacao['card_atendimentos_periodo_gradient_start'] ?? '#f5576c'; ?> 0%, 
        <?php echo $customizacao['card_atendimentos_periodo_gradient_end'] ?? '#f093fb'; ?> 100%
    );
    color: white;
    transition: transform 0.3s ease;
} 