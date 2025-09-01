<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$current_user = wp_get_current_user();

if (!is_user_logged_in()) {
    wp_die('Você precisa estar logado para acessar esta página.');
}

// Determinar nível de acesso baseado no role do usuário
$user_roles = $current_user->roles;
$is_owner = false;
$is_professional = false;
$is_client = false;

if (in_array('administrator', $user_roles) || in_array('editor', $user_roles) || in_array('author', $user_roles)) {
    $is_owner = true;
} elseif (in_array('contributor', $user_roles)) {
    $is_professional = true;
} elseif (in_array('subscriber', $user_roles)) {
    $is_client = true;
}

if (!$is_owner && !$is_professional && !$is_client) {
    wp_die('Acesso negado. Você não tem um role válido para acessar esta página.');
}

$success_message = '';
$error_message = '';

// Verificar se as tabelas existem
$table_financeiro = $wpdb->prefix . 'inbwp_financeiro';
$table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_financeiro'") == $table_financeiro);

if (!$table_exists) {
    $error_message = 'Tabela financeiro não existe. Por favor, desative e reative o plugin.';
} else {
    // Processar efetivar/cancelar registro financeiro
    if (isset($_POST['action']) && ($_POST['action'] === 'efetivar_registro' || $_POST['action'] === 'cancelar_registro')) {
        if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_financeiro')) {
            $registro_id = intval($_POST['registro_id']);
            $novo_status = $_POST['action'] === 'efetivar_registro' ? 'efetivado' : 'pendente';
            
            $where_conditions = array('id' => $registro_id);
            if (!$is_owner) {
                $where_conditions['usuario_id'] = $current_user->ID;
            }
            
            $result = $wpdb->update(
                $table_financeiro,
                array('status' => $novo_status),
                $where_conditions,
                array('%s'),
                array('%d', '%d')
            );
            
            if ($result !== false) {
                $action_text = $novo_status === 'efetivado' ? 'efetivado' : 'cancelado';
                $success_message = "Registro $action_text com sucesso!";
            } else {
                $error_message = 'Erro ao atualizar registro ou registro não encontrado.';
            }
        }
    }
}

// Buscar registros financeiros
$registros = array();
$total_receitas = 0;
$total_despesas = 0;
$total_pendentes = 0;
$total_efetivados = 0;

if ($table_exists) {
    try {
        $where_sql = '';
        $where_values = array();

        if ($is_client) {
            $where_sql = "WHERE f.usuario_id = %d AND f.tipo = 'receita'";
            $where_values[] = $current_user->ID;
        } elseif ($is_professional) {
            $where_sql = "WHERE f.usuario_id = %d";
            $where_values[] = $current_user->ID;
        }

        // Filtros
        $tipo_filter = isset($_GET['tipo_filter']) ? sanitize_text_field($_GET['tipo_filter']) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
        $negocio_filter = isset($_GET['negocio']) ? intval($_GET['negocio']) : 0;

        if ($tipo_filter) {
            $where_sql .= ($where_sql ? ' AND' : 'WHERE') . ' f.tipo = %s';
            $where_values[] = $tipo_filter;
        }

        if ($status_filter) {
            $where_sql .= ($where_sql ? ' AND' : 'WHERE') . ' f.status = %s';
            $where_values[] = $status_filter;
        }

        if ($negocio_filter) {
            $where_sql .= ($where_sql ? ' AND' : 'WHERE') . ' f.negocio_id = %d';
            $where_values[] = $negocio_filter;
        }

        $query = "
            SELECT f.*, 
                   u.display_name as usuario_nome,
                   n.id as negocio_numero
            FROM $table_financeiro f
            LEFT JOIN {$wpdb->users} u ON f.usuario_id = u.ID 
            LEFT JOIN {$wpdb->prefix}inbwp_negocios n ON f.negocio_id = n.id
            $where_sql 
            ORDER BY f.data_vencimento DESC, f.created_at DESC
        ";

        if (!empty($where_values)) {
            $registros = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $registros = $wpdb->get_results($query);
        }

        // Estatísticas
        $stats_where = '';
        $stats_values = array();
        
        if ($is_client) {
            $stats_where = "WHERE usuario_id = %d AND tipo = 'receita'";
            $stats_values[] = $current_user->ID;
        } elseif ($is_professional) {
            $stats_where = "WHERE usuario_id = %d";
            $stats_values[] = $current_user->ID;
        }

        if (!empty($stats_values)) {
            $total_receitas = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM $table_financeiro $stats_where AND tipo = 'receita'", $stats_values));
            $total_despesas = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM $table_financeiro $stats_where AND tipo = 'despesa'", $stats_values));
            $total_pendentes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_financeiro $stats_where AND status = 'pendente'", $stats_values));
            $total_efetivados = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_financeiro $stats_where AND status = 'efetivado'", $stats_values));
        } else {
            $total_receitas = $wpdb->get_var("SELECT COALESCE(SUM(valor), 0) FROM $table_financeiro WHERE tipo = 'receita'");
            $total_despesas = $wpdb->get_var("SELECT COALESCE(SUM(valor), 0) FROM $table_financeiro WHERE tipo = 'despesa'");
            $total_pendentes = $wpdb->get_var("SELECT COUNT(*) FROM $table_financeiro WHERE status = 'pendente'");
            $total_efetivados = $wpdb->get_var("SELECT COUNT(*) FROM $table_financeiro WHERE status = 'efetivado'");
        }
    } catch (Exception $e) {
        $error_message = 'Erro ao buscar dados: ' . $e->getMessage();
    }
}
?>

<div class="wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h1 class="wp-heading-inline" style="margin: 0;">Financeiro</h1>
        <?php if (isset($_GET['negocio'])): ?>
            <a href="<?php echo admin_url('admin.php?page=inbwp-negocios'); ?>" class="page-title-action">← Voltar aos Negócios</a>
        <?php endif; ?>
    </div>
    <hr class="wp-header-end">
    
    <?php if ($success_message): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
        <!-- Estatísticas -->
        <div class="inbwp-stats-row">
            <div class="inbwp-stat-box receitas">
                <div class="inbwp-stat-number">R$ <?php echo number_format($total_receitas, 2, ',', '.'); ?></div>
                <div class="inbwp-stat-label">Total Receitas</div>
            </div>
            <div class="inbwp-stat-box despesas">
                <div class="inbwp-stat-number">R$ <?php echo number_format($total_despesas, 2, ',', '.'); ?></div>
                <div class="inbwp-stat-label">Total Despesas</div>
            </div>
            <div class="inbwp-stat-box saldo">
                <div class="inbwp-stat-number">R$ <?php echo number_format($total_receitas - $total_despesas, 2, ',', '.'); ?></div>
                <div class="inbwp-stat-label">Saldo</div>
            </div>
            <div class="inbwp-stat-box pendentes">
                <div class="inbwp-stat-number"><?php echo number_format($total_pendentes); ?></div>
                <div class="inbwp-stat-label">Pendentes</div>
            </div>
            <div class="inbwp-stat-box efetivados">
                <div class="inbwp-stat-number"><?php echo number_format($total_efetivados); ?></div>
                <div class="inbwp-stat-label">Efetivados</div>
            </div>
        </div>
        
        <?php if (isset($_GET['negocio'])): ?>
            <div class="notice notice-info">
                <p><strong>💰 Registros do Negócio #<?php echo intval($_GET['negocio']); ?></strong></p>
                <p>Exibindo apenas os registros financeiros relacionados a este negócio.</p>
            </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display: inline-flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="inbwp-financeiro">
                    <?php if (isset($_GET['negocio'])): ?>
                        <input type="hidden" name="negocio" value="<?php echo intval($_GET['negocio']); ?>">
                    <?php endif; ?>
                    
                    <select name="tipo_filter">
                        <option value="">Todos os tipos</option>
                        <option value="receita" <?php selected($tipo_filter ?? '', 'receita'); ?>>Receitas</option>
                        <option value="despesa" <?php selected($tipo_filter ?? '', 'despesa'); ?>>Despesas</option>
                    </select>
                    
                    <select name="status_filter">
                        <option value="">Todos os status</option>
                        <option value="pendente" <?php selected($status_filter ?? '', 'pendente'); ?>>Pendente</option>
                        <option value="efetivado" <?php selected($status_filter ?? '', 'efetivado'); ?>>Efetivado</option>
                    </select>
                    
                    <input type="submit" class="button" value="Filtrar">
                    
                    <?php if (($tipo_filter ?? '') || ($status_filter ?? '')): ?>
                        <a href="<?php echo admin_url('admin.php?page=inbwp-financeiro' . (isset($_GET['negocio']) ? '&negocio=' . intval($_GET['negocio']) : '')); ?>" class="button">Limpar Filtros</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Lista de Registros -->
        <?php if (empty($registros)): ?>
            <div class="inbwp-empty-state">
                <div class="inbwp-empty-icon">💰</div>
                <h3>Nenhum registro financeiro encontrado</h3>
                <?php if (($tipo_filter ?? '') || ($status_filter ?? '')): ?>
                    <p>Tente ajustar os filtros ou <a href="<?php echo admin_url('admin.php?page=inbwp-financeiro'); ?>">remover todos os filtros</a>.</p>
                <?php else: ?>
                    <p>Os registros financeiros são criados automaticamente quando um negócio é adicionado.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 80px;">Tipo</th>
                        <th>Descrição</th>
                        <th>Usuário</th>
                        <th style="width: 100px;">Valor</th>
                        <th style="width: 100px;">Vencimento</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 100px;">Negócio</th>
                        <th style="width: 120px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $registro): ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($registro->id); ?></strong></td>
                            <td>
                                <span class="inbwp-tipo-financeiro inbwp-tipo-<?php echo esc_attr($registro->tipo); ?>">
                                    <?php echo $registro->tipo === 'receita' ? '📈 Receita' : '📉 Despesa'; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($registro->descricao); ?></strong>
                            </td>
                            <td><?php echo esc_html($registro->usuario_nome); ?></td>
                            <td>
                                <strong class="<?php echo $registro->tipo === 'receita' ? 'receita-valor' : 'despesa-valor'; ?>">
                                    <?php echo $registro->tipo === 'receita' ? '+' : '-'; ?>R$ <?php echo number_format($registro->valor, 2, ',', '.'); ?>
                                </strong>
                            </td>
                            <td><?php echo $registro->data_vencimento ? date('d/m/Y', strtotime($registro->data_vencimento)) : '—'; ?></td>
                            <td>
                                <span class="inbwp-status-financeiro inbwp-status-<?php echo esc_attr($registro->status); ?>">
                                    <?php echo $registro->status === 'efetivado' ? '✅ Efetivado' : '⏳ Pendente'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($registro->negocio_numero): ?>
                                    <a href="<?php echo admin_url('admin.php?page=inbwp-negocios'); ?>" title="Ver negócio">
                                        #<?php echo esc_html($registro->negocio_numero); ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_owner || ($registro->usuario_id == $current_user->ID)): ?>
                                    <?php if ($registro->status === 'pendente'): ?>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('inbwp_financeiro', 'inbwp_nonce'); ?>
                                            <input type="hidden" name="action" value="efetivar_registro">
                                            <input type="hidden" name="registro_id" value="<?php echo esc_attr($registro->id); ?>">
                                            <button type="submit" class="button button-small button-primary" title="Marcar como efetivado">
                                                ✅ Efetivar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('inbwp_financeiro', 'inbwp_nonce'); ?>
                                            <input type="hidden" name="action" value="cancelar_registro">
                                            <input type="hidden" name="registro_id" value="<?php echo esc_attr($registro->id); ?>">
                                            <button type="submit" class="button button-small" title="Marcar como pendente">
                                                ⏳ Cancelar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <div class="notice notice-warning">
            <p><strong>Atenção:</strong> A tabela financeiro não foi criada corretamente.</p>
            <p>Por favor, desative e reative o plugin para criar as tabelas necessárias.</p>
        </div>
    <?php endif; ?>
</div>

<style>
/* Estatísticas */
.inbwp-stats-row {
    display: flex;
    gap: 15px;
    margin: 20px 0;
}

.inbwp-stat-box {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    flex: 1;
}

.inbwp-stat-box.receitas {
    border-color: #00a32a;
    background: #f0f9f0;
}

.inbwp-stat-box.despesas {
    border-color: #d63638;
    background: #fdf0f0;
}

.inbwp-stat-box.saldo {
    border-color: #0073aa;
    background: #f0f6fc;
}

.inbwp-stat-box.pendentes {
    border-color: #dba617;
    background: #fcf9e8;
}

.inbwp-stat-box.efetivados {
    border-color: #00a32a;
    background: #f0f9f0;
}

.inbwp-stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #1d2327;
    line-height: 1;
}

.inbwp-stat-label {
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
}

/* Estado vazio */
.inbwp-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.inbwp-empty-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

/* Tipos financeiros */
.inbwp-tipo-financeiro {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.inbwp-tipo-receita {
    background: #d1e7dd;
    color: #0f5132;
}

.inbwp-tipo-despesa {
    background: #f8d7da;
    color: #721c24;
}

/* Status financeiros */
.inbwp-status-financeiro {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.inbwp-status-pendente {
    background: #fff3cd;
    color: #664d03;
}

.inbwp-status-efetivado {
    background: #d1e7dd;
    color: #0f5132;
}

/* Valores */
.receita-valor {
    color: #00a32a;
}

.despesa-valor {
    color: #d63638;
}

@media (max-width: 768px) {
    .inbwp-stats-row {
        flex-direction: column;
        gap: 10px;
    }
}
</style>