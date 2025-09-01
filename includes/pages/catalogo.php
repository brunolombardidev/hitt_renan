<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$current_user = wp_get_current_user();

// Verificar se o usuário está logado
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

// Verificar se a tabela existe
$table_name = $wpdb->prefix . 'inbwp_catalogo';
$table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);

if (!$table_exists) {
    $error_message = 'Tabela não existe. Por favor, desative e reative o plugin.';
} else {
    // Função para fazer upload da imagem
    function upload_produto_imagem($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Verificar se o arquivo foi enviado
        if (empty($file['name'])) {
            return array('error' => 'Nenhum arquivo foi selecionado.');
        }
        
        // Verificar tipo de arquivo
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            return array('error' => 'Tipo de arquivo não permitido. Use apenas JPG, PNG ou WebP.');
        }
        
        // Verificar tamanho do arquivo (1MB = 1048576 bytes)
        if ($file['size'] > 1048576) {
            return array('error' => 'Arquivo muito grande. O tamanho máximo é 1MB.');
        }
        
        // Configurar upload
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp'
            )
        );
        
        // Fazer upload
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            return array('success' => true, 'url' => $movefile['url'], 'file' => $movefile['file']);
        } else {
            return array('error' => $movefile['error']);
        }
    }
    
    // Função para deletar imagem antiga
    function delete_produto_imagem($image_url) {
        if (empty($image_url)) {
            return;
        }
        
        // Converter URL para caminho do arquivo
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        
        // Deletar arquivo se existir
        if (file_exists($file_path)) {
            wp_delete_file($file_path);
        }
    }

    // Processar adição de item
    if (isset($_POST['action']) && $_POST['action'] === 'add_item') {
        if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_catalogo')) {
            $nome = sanitize_text_field($_POST['nome']);
            $tipo = sanitize_text_field($_POST['tipo']);
            $categoria = sanitize_text_field($_POST['categoria']);
            $preco = floatval($_POST['preco']);
            $repasse = floatval($_POST['repasse']);
            $periodo = intval($_POST['periodo']);
            $quantidade = intval($_POST['quantidade']);
            $status = sanitize_text_field($_POST['status']);
            $descricao = sanitize_textarea_field($_POST['descricao']);
            $foto_url = '';
            
            // Processar upload da imagem
            if (!empty($_FILES['foto']['name'])) {
                $upload_result = upload_produto_imagem($_FILES['foto']);
                if (isset($upload_result['error'])) {
                    $error_message = $upload_result['error'];
                } else {
                    $foto_url = $upload_result['url'];
                }
            }
            
            if (empty($error_message)) {
                if (empty($nome) || empty($tipo) || empty($categoria) || empty($preco) || empty($repasse) || empty($periodo) || empty($quantidade) || empty($status)) {
                    $error_message = 'Todos os campos obrigatórios devem ser preenchidos.';
                } else {
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'nome' => $nome,
                            'tipo' => $tipo,
                            'categoria' => $categoria,
                            'preco' => $preco,
                            'repasse' => $repasse,
                            'periodo' => $periodo,
                            'quantidade' => $quantidade,
                            'status' => $status,
                            'foto_url' => $foto_url,
                            'descricao' => $descricao,
                            'user_id' => $current_user->ID
                        ),
                        array('%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s', '%s', '%d')
                    );
                    
                    if ($result !== false) {
                        $success_message = 'Item adicionado com sucesso!';
                    } else {
                        $error_message = 'Erro ao adicionar item: ' . $wpdb->last_error;
                        // Se houve erro ao salvar no banco, deletar a imagem que foi enviada
                        if (!empty($foto_url)) {
                            delete_produto_imagem($foto_url);
                        }
                    }
                }
            }
        } else {
            $error_message = 'Falha na verificação de segurança.';
        }
    }

    // Processar edição de item
    if (isset($_POST['action']) && $_POST['action'] === 'edit_item') {
        if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_catalogo')) {
            $id = intval($_POST['item_id']);
            $nome = sanitize_text_field($_POST['nome']);
            $tipo = sanitize_text_field($_POST['tipo']);
            $categoria = sanitize_text_field($_POST['categoria']);
            $preco = floatval($_POST['preco']);
            $repasse = floatval($_POST['repasse']);
            $periodo = intval($_POST['periodo']);
            $quantidade = intval($_POST['quantidade']);
            $status = sanitize_text_field($_POST['status']);
            $descricao = sanitize_textarea_field($_POST['descricao']);
            
            // Buscar item atual para pegar a foto antiga - CORRIGIDO: removido filtro por user_id na busca
            $where_condition = "WHERE id = %d";
            $where_params = array($id);
            
            if (!$is_owner) {
                $where_condition .= " AND user_id = %d";
                $where_params[] = $current_user->ID;
            }
            
            $item_atual = $wpdb->get_row($wpdb->prepare(
                "SELECT foto_url FROM $table_name $where_condition",
                $where_params
            ));
            
            $foto_url = $item_atual ? $item_atual->foto_url : '';
            $foto_url_antiga = $foto_url;
            
            // Processar upload da nova imagem
            if (!empty($_FILES['foto']['name'])) {
                $upload_result = upload_produto_imagem($_FILES['foto']);
                if (isset($upload_result['error'])) {
                    $error_message = $upload_result['error'];
                } else {
                    $foto_url = $upload_result['url'];
                }
            }
            
            if (empty($error_message)) {
                if (empty($nome) || empty($tipo) || empty($categoria) || empty($preco) || empty($repasse) || empty($periodo) || empty($quantidade) || empty($status)) {
                    $error_message = 'Todos os campos obrigatórios devem ser preenchidos.';
                } else {
                    $update_where = array('id' => $id);
                    $update_where_format = array('%d');
                    
                    if (!$is_owner) {
                        $update_where['user_id'] = $current_user->ID;
                        $update_where_format[] = '%d';
                    }
                    
                    $result = $wpdb->update(
                        $table_name,
                        array(
                            'nome' => $nome,
                            'tipo' => $tipo,
                            'categoria' => $categoria,
                            'preco' => $preco,
                            'repasse' => $repasse,
                            'periodo' => $periodo,
                            'quantidade' => $quantidade,
                            'status' => $status,
                            'foto_url' => $foto_url,
                            'descricao' => $descricao
                        ),
                        $update_where,
                        array('%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s', '%s'),
                        $update_where_format
                    );
                    
                    if ($result !== false) {
                        $success_message = 'Item atualizado com sucesso!';
                        // Se uma nova imagem foi enviada, deletar a antiga
                        if (!empty($_FILES['foto']['name']) && $foto_url !== $foto_url_antiga && !empty($foto_url_antiga)) {
                            delete_produto_imagem($foto_url_antiga);
                        }
                    } else {
                        $error_message = 'Erro ao atualizar item ou item não encontrado.';
                        // Se houve erro ao salvar no banco e uma nova imagem foi enviada, deletá-la
                        if (!empty($_FILES['foto']['name']) && $foto_url !== $foto_url_antiga) {
                            delete_produto_imagem($foto_url);
                        }
                    }
                }
            }
        }
    }

    // Processar exclusão de item
    if (isset($_POST['action']) && $_POST['action'] === 'delete_item') {
        if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_catalogo')) {
            $id = intval($_POST['item_id']);
            
            // Buscar item para pegar a URL da foto antes de deletar
            $where_condition = "WHERE id = %d";
            $where_params = array($id);
            
            if (!$is_owner) {
                $where_condition .= " AND user_id = %d";
                $where_params[] = $current_user->ID;
            }
            
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT foto_url FROM $table_name $where_condition",
                $where_params
            ));
            
            if ($item) {
                $delete_where = array('id' => $id);
                $delete_where_format = array('%d');
                
                if (!$is_owner) {
                    $delete_where['user_id'] = $current_user->ID;
                    $delete_where_format[] = '%d';
                }
                
                $result = $wpdb->delete(
                    $table_name,
                    $delete_where,
                    $delete_where_format
                );
                
                if ($result !== false) {
                    $success_message = 'Item excluído com sucesso!';
                    // Deletar a imagem associada
                    if (!empty($item->foto_url)) {
                        delete_produto_imagem($item->foto_url);
                    }
                } else {
                    $error_message = 'Erro ao excluir item.';
                }
            } else {
                $error_message = 'Item não encontrado.';
            }
        }
    }
}

// Buscar itens
$itens = array();
$total_itens = 0;
$total_locacoes = 0;
$total_servicos = 0;
$total_pacotes = 0;

if ($table_exists) {
    try {
        $where_sql = '';
        $where_values = array();

        if (!$is_owner) {
            $where_sql = "WHERE user_id = %d";
            $where_values[] = $current_user->ID;
        }

        // Filtros
        $tipo_filter = isset($_GET['tipo_filter']) ? sanitize_text_field($_GET['tipo_filter']) : '';
        $categoria_filter = isset($_GET['categoria_filter']) ? sanitize_text_field($_GET['categoria_filter']) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

        if ($tipo_filter) {
            $where_sql .= ($where_sql ? ' AND' : 'WHERE') . ' tipo = %s';
            $where_values[] = $tipo_filter;
        }

        if ($categoria_filter) {
            $where_sql .= ($where_sql ? ' AND' : 'WHERE') . ' categoria = %s';
            $where_values[] = $categoria_filter;
        }

        if ($status_filter) {
            $where_sql .= ($where_sql ? ' AND' : 'WHERE') . ' status = %s';
            $where_values[] = $status_filter;
        }

        $query = "SELECT * FROM $table_name $where_sql ORDER BY nome ASC";

        if (!empty($where_values)) {
            $itens = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $itens = $wpdb->get_results($query);
        }

        // Estatísticas
        $stats_where = '';
        $stats_values = array();
        
        if (!$is_owner) {
            $stats_where = "WHERE user_id = %d";
            $stats_values[] = $current_user->ID;
        }

        if (!empty($stats_values)) {
            $total_itens = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name $stats_where", $stats_values));
            $total_locacoes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name $stats_where AND tipo = 'locacoes'", $stats_values));
            $total_servicos = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name $stats_where AND tipo = 'servicos'", $stats_values));
            $total_pacotes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name $stats_where AND tipo = 'pacotes'", $stats_values));
        } else {
            $total_itens = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $total_locacoes = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE tipo = 'locacoes'");
            $total_servicos = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE tipo = 'servicos'");
            $total_pacotes = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE tipo = 'pacotes'");
        }
    } catch (Exception $e) {
        $error_message = 'Erro ao buscar dados: ' . $e->getMessage();
    }
}
?>

<div class="wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h1 class="wp-heading-inline" style="margin: 0;">Catálogo</h1>
        <?php if ($is_owner || $is_professional): ?>
            <a href="#" class="page-title-action" onclick="openAddModal(); return false;">Adicionar Item</a>
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
            <div class="inbwp-stat-box">
                <div class="inbwp-stat-number"><?php echo number_format($total_itens); ?></div>
                <div class="inbwp-stat-label">Total de Itens</div>
            </div>
            <div class="inbwp-stat-box">
                <div class="inbwp-stat-number"><?php echo number_format($total_locacoes); ?></div>
                <div class="inbwp-stat-label">Locações</div>
            </div>
            <div class="inbwp-stat-box">
                <div class="inbwp-stat-number"><?php echo number_format($total_servicos); ?></div>
                <div class="inbwp-stat-label">Serviços</div>
            </div>
            <div class="inbwp-stat-box">
                <div class="inbwp-stat-number"><?php echo number_format($total_pacotes); ?></div>
                <div class="inbwp-stat-label">Pacotes</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display: inline-flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="inbwp-catalogo">
                    
                    <select name="tipo_filter">
                        <option value="">Todos os tipos</option>
                        <option value="locacoes" <?php selected($tipo_filter ?? '', 'locacoes'); ?>>Locações</option>
                        <option value="servicos" <?php selected($tipo_filter ?? '', 'servicos'); ?>>Serviços</option>
                        <option value="pacotes" <?php selected($tipo_filter ?? '', 'pacotes'); ?>>Pacotes</option>
                    </select>
                    
                    <select name="categoria_filter">
                        <option value="">Todas as categorias</option>
                        <option value="construcao" <?php selected($categoria_filter ?? '', 'construcao'); ?>>Construção</option>
                        <option value="eletrica" <?php selected($categoria_filter ?? '', 'eletrica'); ?>>Elétrica</option>
                        <option value="jardinagem" <?php selected($categoria_filter ?? '', 'jardinagem'); ?>>Jardinagem</option>
                        <option value="pintura" <?php selected($categoria_filter ?? '', 'pintura'); ?>>Pintura</option>
                    </select>
                    
                    <select name="status_filter">
                        <option value="">Todos os status</option>
                        <option value="ativo" <?php selected($status_filter ?? '', 'ativo'); ?>>Ativo</option>
                        <option value="inativo" <?php selected($status_filter ?? '', 'inativo'); ?>>Inativo</option>
                    </select>
                    
                    <input type="submit" class="button" value="Filtrar">
                    
                    <?php if (($tipo_filter ?? '') || ($categoria_filter ?? '') || ($status_filter ?? '')): ?>
                        <a href="<?php echo admin_url('admin.php?page=inbwp-catalogo'); ?>" class="button">Limpar Filtros</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Lista de Itens -->
        <?php if (empty($itens)): ?>
            <div class="inbwp-empty-state">
                <div class="inbwp-empty-icon">📦</div>
                <h3>Nenhum item encontrado</h3>
                <?php if (($tipo_filter ?? '') || ($categoria_filter ?? '') || ($status_filter ?? '')): ?>
                    <p>Tente ajustar os filtros ou <a href="<?php echo admin_url('admin.php?page=inbwp-catalogo'); ?>">remover todos os filtros</a>.</p>
                <?php else: ?>
                    <p>Adicione seu primeiro item clicando no botão "Adicionar Item".</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 80px;">Foto</th>
                        <th>Nome</th>
                        <th style="width: 100px;">Tipo</th>
                        <th style="width: 100px;">Categoria</th>
                        <th style="width: 80px;">Preço</th>
                        <th style="width: 80px;">Repasse</th>
                        <th style="width: 60px;">Período</th>
                        <th style="width: 60px;">Qtd</th>
                        <th style="width: 80px;">Status</th>
                        <?php if ($is_owner): ?>
                            <th style="width: 120px;">Criado por</th>
                        <?php endif; ?>
                        <th style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td><strong><?php echo esc_html($item->id); ?></strong></td>
                            <td>
                                <?php if ($item->foto_url): ?>
                                    <img src="<?php echo esc_url($item->foto_url); ?>" 
                                         alt="<?php echo esc_attr($item->nome); ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #f0f0f1; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #646970;">
                                        📷
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($item->nome); ?></strong>
                                <?php if ($item->descricao): ?>
                                    <br><small style="color: #666;"><?php echo esc_html(wp_trim_words($item->descricao, 8)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="inbwp-tipo inbwp-tipo-<?php echo esc_attr($item->tipo); ?>">
                                    <?php 
                                    switch($item->tipo) {
                                        case 'locacoes': echo 'Locações'; break;
                                        case 'servicos': echo 'Serviços'; break;
                                        case 'pacotes': echo 'Pacotes'; break;
                                        default: echo esc_html($item->tipo);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="inbwp-categoria inbwp-categoria-<?php echo esc_attr($item->categoria); ?>">
                                    <?php 
                                    switch($item->categoria) {
                                        case 'construcao': echo 'Construção'; break;
                                        case 'eletrica': echo 'Elétrica'; break;
                                        case 'jardinagem': echo 'Jardinagem'; break;
                                        case 'pintura': echo 'Pintura'; break;
                                        default: echo esc_html($item->categoria);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><strong>R$ <?php echo number_format($item->preco, 2, ',', '.'); ?></strong></td>
                            <td>R$ <?php echo number_format($item->repasse, 2, ',', '.'); ?></td>
                            <td><?php echo esc_html($item->periodo); ?> dias</td>
                            <td><?php echo esc_html($item->quantidade); ?></td>
                            <td>
                                <span class="inbwp-status inbwp-status-<?php echo esc_attr($item->status); ?>">
                                    <?php echo $item->status === 'ativo' ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <?php if ($is_owner): ?>
                                <td>
                                    <?php 
                                    $user = get_user_by('ID', $item->user_id);
                                    echo $user ? esc_html($user->display_name) : 'Usuário removido';
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($is_owner || $item->user_id == $current_user->ID): ?>
                                    <button type="button" class="button button-small" onclick="editItem(<?php echo esc_attr(json_encode($item)); ?>)">
                                        Editar
                                    </button>
                                    
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este item?');">
                                        <?php wp_nonce_field('inbwp_catalogo', 'inbwp_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="item_id" value="<?php echo esc_attr($item->id); ?>">
                                        <button type="submit" class="button button-small button-link-delete">Excluir</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #646970;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Modal para Adicionar/Editar Item -->
        <div id="inbwp-modal-overlay" class="inbwp-modal-overlay">
            <div id="inbwp-modal" class="inbwp-modal">
                <div class="inbwp-modal-header">
                    <h2 id="modal-title">Adicionar Novo Item</h2>
                    <button type="button" class="inbwp-modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="inbwp-modal-content">
                    <form method="post" id="catalogo-form" class="inbwp-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('inbwp_catalogo', 'inbwp_nonce'); ?>
                        <input type="hidden" name="action" value="add_item" id="form-action">
                        <input type="hidden" name="item_id" value="" id="form-item-id">
                        
                        <!-- Primeira linha: Nome -->
                        <div class="inbwp-form-row">
                            <div class="inbwp-form-group full-width">
                                <label for="nome">Nome *</label>
                                <input type="text" id="nome" name="nome" required>
                            </div>
                        </div>
                        
                        <!-- Segunda linha: Tipos e Categorias -->
                        <div class="inbwp-form-row">
                            <div class="inbwp-form-group">
                                <label for="tipo">Tipos *</label>
                                <select id="tipo" name="tipo" required>
                                    <option value="">Selecione o tipo...</option>
                                    <option value="locacoes">Locações</option>
                                    <option value="servicos">Serviços</option>
                                    <option value="pacotes">Pacotes</option>
                                </select>
                            </div>
                            <div class="inbwp-form-group">
                                <label for="categoria">Categorias *</label>
                                <select id="categoria" name="categoria" required>
                                    <option value="">Selecione a categoria...</option>
                                    <option value="construcao">Construção</option>
                                    <option value="eletrica">Elétrica</option>
                                    <option value="jardinagem">Jardinagem</option>
                                    <option value="pintura">Pintura</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Terceira linha: Preço, Repasse e Período -->
                        <div class="inbwp-form-row">
                            <div class="inbwp-form-group">
                                <label for="preco">Preço R$ *</label>
                                <input type="number" id="preco" name="preco" step="0.01" min="0" required>
                                <small>Valor deste item por dia</small>
                            </div>
                            <div class="inbwp-form-group">
                                <label for="repasse">Repasse R$ *</label>
                                <input type="number" id="repasse" name="repasse" step="0.01" min="0" required>
                                <small>Valor pago ao profissional</small>
                            </div>
                            <div class="inbwp-form-group">
                                <label for="periodo">Período *</label>
                                <input type="number" id="periodo" name="periodo" min="1" required>
                                <small>Em dias</small>
                            </div>
                        </div>
                        
                        <!-- Quarta linha: Quantidade e Status -->
                        <div class="inbwp-form-row">
                            <div class="inbwp-form-group">
                                <label for="quantidade">Quantidade *</label>
                                <input type="number" id="quantidade" name="quantidade" min="1" required>
                            </div>
                            <div class="inbwp-form-group">
                                <label for="status">Status *</label>
                                <select id="status" name="status" required>
                                    <option value="">Selecione o status...</option>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Quinta linha: Upload de Foto -->
                        <div class="inbwp-form-row">
                            <div class="inbwp-form-group full-width">
                                <label for="foto">Foto do Produto/Serviço</label>
                                <div class="inbwp-upload-area" id="upload-area">
                                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewImage(this)">
                                    <div class="inbwp-upload-placeholder" id="upload-placeholder">
                                        <div class="inbwp-upload-icon">📷</div>
                                        <div class="inbwp-upload-text">
                                            <strong>Clique para selecionar uma imagem</strong><br>
                                            <small>JPG, PNG ou WebP • Máximo 1MB</small>
                                        </div>
                                    </div>
                                    <div class="inbwp-upload-preview" id="upload-preview" style="display: none;">
                                        <img id="preview-image" src="/placeholder.svg" alt="Preview">
                                        <button type="button" class="inbwp-remove-image" onclick="removeImage()">×</button>
                                    </div>
                                </div>
                                <div id="current-image-info" style="display: none; margin-top: 10px; padding: 10px; background: #f0f6fc; border-radius: 4px;">
                                    <small style="color: #0073aa;">
                                        <strong>Imagem atual:</strong> <span id="current-image-name"></span><br>
                                        Selecione uma nova imagem para substituir a atual.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sexta linha: Descritivo -->
                        <div class="inbwp-form-row">
                            <div class="inbwp-form-group full-width">
                                <label for="descricao">Descritivo</label>
                                <textarea id="descricao" name="descricao" rows="4"></textarea>
                            </div>
                        </div>
                        
                        <div class="inbwp-form-actions">
                            <input type="submit" class="button button-primary" value="Adicionar Item" id="submit-button">
                            <button type="button" class="button" onclick="closeModal()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="notice notice-warning">
            <p><strong>Atenção:</strong> A tabela do banco de dados não foi criada corretamente.</p>
            <p>Por favor, desative e reative o plugin para criar as tabelas necessárias.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Funções para manipular o modal
function openModal() {
    document.getElementById('inbwp-modal-overlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('inbwp-modal-overlay').style.display = 'none';
    document.body.style.overflow = 'auto';
    resetForm();
}

function openAddModal() {
    resetForm();
    document.getElementById('modal-title').textContent = 'Adicionar Novo Item';
    document.getElementById('form-action').value = 'add_item';
    document.getElementById('submit-button').value = 'Adicionar Item';
    openModal();
}

function editItem(item) {
    document.getElementById('modal-title').textContent = 'Editar Item';
    document.getElementById('form-action').value = 'edit_item';
    document.getElementById('form-item-id').value = item.id;
    document.getElementById('nome').value = item.nome;
    document.getElementById('tipo').value = item.tipo;
    document.getElementById('categoria').value = item.categoria;
    document.getElementById('preco').value = item.preco;
    document.getElementById('repasse').value = item.repasse;
    document.getElementById('periodo').value = item.periodo;
    document.getElementById('quantidade').value = item.quantidade;
    document.getElementById('status').value = item.status;
    document.getElementById('descricao').value = item.descricao || '';
    document.getElementById('submit-button').value = 'Atualizar Item';
    
    // Mostrar informação da imagem atual se existir
    if (item.foto_url) {
        document.getElementById('current-image-info').style.display = 'block';
        document.getElementById('current-image-name').textContent = item.foto_url.split('/').pop();
    } else {
        document.getElementById('current-image-info').style.display = 'none';
    }
    
    openModal();
}

function resetForm() {
    document.getElementById('catalogo-form').reset();
    document.getElementById('form-item-id').value = '';
    document.getElementById('upload-preview').style.display = 'none';
    document.getElementById('upload-placeholder').style.display = 'block';
    document.getElementById('current-image-info').style.display = 'none';
    document.getElementById('foto').value = '';
}

function previewImage(input) {
    const file = input.files[0];
    if (file) {
        // Verificar tipo de arquivo
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Tipo de arquivo não permitido. Use apenas JPG, PNG ou WebP.');
            input.value = '';
            return;
        }
        
        // Verificar tamanho do arquivo (1MB)
        if (file.size > 1048576) {
            alert('Arquivo muito grande. O tamanho máximo é 1MB.');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.getElementById('upload-preview').style.display = 'block';
            document.getElementById('upload-placeholder').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}

function removeImage() {
    document.getElementById('foto').value = '';
    document.getElementById('upload-preview').style.display = 'none';
    document.getElementById('upload-placeholder').style.display = 'block';
}

// Fechar modal ao clicar fora dele
document.getElementById('inbwp-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Fechar modal com a tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<style>
/* Estilos para o modal */
.inbwp-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.inbwp-modal {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.inbwp-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #dcdcde;
    background-color: #f6f7f7;
}

.inbwp-modal-header h2 {
    margin: 0;
    font-size: 18px;
    line-height: 1.4;
}

.inbwp-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 24px;
    color: #646970;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.inbwp-modal-close:hover {
    background-color: #dcdcde;
    color: #1d2327;
}

.inbwp-modal-content {
    padding: 20px;
    overflow-y: auto;
}

/* Estilos para o formulário */
.inbwp-form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.inbwp-form-group {
    flex: 1;
}

.inbwp-form-group.full-width {
    flex: 1 1 100%;
}

.inbwp-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.inbwp-form-group input,
.inbwp-form-group select,
.inbwp-form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.inbwp-form-group small {
    color: #646970;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.inbwp-form-actions {
    border-top: 1px solid #dcdcde;
    padding-top: 15px;
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

/* Estilos para upload de imagem */
.inbwp-upload-area {
    position: relative;
    border: 2px dashed #c3c4c7;
    border-radius: 4px;
    background: #f9f9f9;
    transition: all 0.3s ease;
}

.inbwp-upload-area:hover {
    border-color: #0073aa;
    background: #f0f6fc;
}

.inbwp-upload-area input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

.inbwp-upload-placeholder {
    padding: 40px 20px;
    text-align: center;
    color: #646970;
}

.inbwp-upload-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.inbwp-upload-text strong {
    color: #0073aa;
}

.inbwp-upload-preview {
    position: relative;
    padding: 10px;
}

.inbwp-upload-preview img {
    width: 100%;
    max-width: 200px;
    height: auto;
    border-radius: 4px;
    display: block;
    margin: 0 auto;
}

.inbwp-remove-image {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #dc3232;
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.inbwp-remove-image:hover {
    background: #a00;
}

/* Estatísticas */
.inbwp-stats-row {
    display: flex;
    gap: 20px;
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

.inbwp-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #1d2327;
    line-height: 1;
}

.inbwp-stat-label {
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
}

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

.inbwp-tipo {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.inbwp-tipo-locacoes {
    background: #e2e3ff;
    color: #3d348b;
}

.inbwp-tipo-servicos {
    background: #fff3cd;
    color: #664d03;
}

.inbwp-tipo-pacotes {
    background: #d1e7dd;
    color: #0f5132;
}

.inbwp-categoria {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.inbwp-categoria-construcao {
    background: #f8d7da;
    color: #721c24;
}

.inbwp-categoria-eletrica {
    background: #cff4fc;
    color: #055160;
}

.inbwp-categoria-jardinagem {
    background: #d1e7dd;
    color: #0f5132;
}

.inbwp-categoria-pintura {
    background: #e2e3ff;
    color: #3d348b;
}

.inbwp-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.inbwp-status-ativo {
    background: #d1e7dd;
    color: #0f5132;
}

.inbwp-status-inativo {
    background: #f8d7da;
    color: #721c24;
}

@media (max-width: 768px) {
    .inbwp-form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .inbwp-stats-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .inbwp-modal {
        width: 95%;
        max-height: 80vh;
    }
}
</style>