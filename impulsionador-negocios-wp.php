<?php
/**
 * Plugin Name: Impulsionador de Negócios WordPress
 * Description: Sistema completo para gerenciamento de negócios, catálogo e clientes
 * Version: 1.0.0
 * Author: Seu Nome
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('INBWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INBWP_PLUGIN_PATH', plugin_dir_path(__FILE__));

class ImpulsionadorNegociosWP {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_inbwp_create_negocio', array($this, 'ajax_create_negocio'));
        add_action('wp_ajax_inbwp_get_negocio_details', array($this, 'ajax_get_negocio_details'));
        add_action('wp_ajax_inbwp_get_user_address', array($this, 'ajax_get_user_address'));
    }
    
    public function activate() {
        $this->create_tables();
    }
    
    public function deactivate() {
        // Cleanup se necessário
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela de negócios
        $table_negocios = $wpdb->prefix . 'inbwp_negocios';
        $sql_negocios = "CREATE TABLE $table_negocios (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cliente_id bigint(20) NOT NULL,
            servidor_id bigint(20) DEFAULT NULL,
            itens_negocio longtext,
            endereco_cliente longtext,
            data_inicio date NOT NULL,
            data_fim date NOT NULL,
            valor_total decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) NOT NULL DEFAULT 'ativo',
            aceita_termos tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cliente_id (cliente_id),
            KEY servidor_id (servidor_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Tabela de catálogo
        $table_catalogo = $wpdb->prefix . 'inbwp_catalogo';
        $sql_catalogo = "CREATE TABLE $table_catalogo (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            nome varchar(255) NOT NULL,
            descricao text,
            preco decimal(10,2) NOT NULL,
            categoria varchar(100),
            status varchar(20) NOT NULL DEFAULT 'ativo',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Tabela financeiro
        $table_financeiro = $wpdb->prefix . 'inbwp_financeiro';
        $sql_financeiro = "CREATE TABLE $table_financeiro (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            negocio_id mediumint(9) NOT NULL,
            tipo varchar(20) NOT NULL,
            valor decimal(10,2) NOT NULL,
            data_vencimento date,
            data_pagamento date,
            status varchar(20) NOT NULL DEFAULT 'pendente',
            observacoes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY negocio_id (negocio_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_negocios);
        dbDelta($sql_catalogo);
        dbDelta($sql_financeiro);
        
        // Inserir dados de exemplo no catálogo se não existirem
        $existing_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_catalogo");
        if ($existing_items == 0) {
            $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
            if (!empty($admin_users)) {
                $admin_id = $admin_users[0]->ID;
                
                $sample_items = array(
                    array('nome' => 'Consultoria Básica', 'descricao' => 'Consultoria básica de 1 hora', 'preco' => 100.00, 'categoria' => 'Consultoria'),
                    array('nome' => 'Desenvolvimento Web', 'descricao' => 'Desenvolvimento de site básico', 'preco' => 500.00, 'categoria' => 'Desenvolvimento'),
                    array('nome' => 'Design Gráfico', 'descricao' => 'Criação de identidade visual', 'preco' => 300.00, 'categoria' => 'Design')
                );
                
                foreach ($sample_items as $item) {
                    $wpdb->insert(
                        $table_catalogo,
                        array(
                            'user_id' => $admin_id,
                            'nome' => $item['nome'],
                            'descricao' => $item['descricao'],
                            'preco' => $item['preco'],
                            'categoria' => $item['categoria'],
                            'status' => 'ativo'
                        ),
                        array('%d', '%s', '%s', '%f', '%s', '%s')
                    );
                }
            }
        }
        
        // Configurar descontos progressivos padrão
        $existing_discounts = get_option('inbwp_descontos_progressivos');
        if (!$existing_discounts) {
            update_option('inbwp_descontos_progressivos', array(
                '1_dia' => 0,
                '1_semana' => 5,
                '1_mes' => 15
            ));
        }
    }
    
    public function add_admin_menu() {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // Determinar nível de acesso
        $is_owner = in_array('administrator', $user_roles) || in_array('editor', $user_roles) || in_array('author', $user_roles);
        $is_professional = in_array('contributor', $user_roles);
        $is_client = in_array('subscriber', $user_roles);
        
        if (!$is_owner && !$is_professional && !$is_client) {
            return;
        }
        
        // Menu principal
        add_menu_page(
            'Impulsionador de Negócios',
            'Impulsionador',
            'read',
            'inbwp-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-chart-line',
            30
        );
        
        // Submenu Dashboard
        add_submenu_page(
            'inbwp-dashboard',
            'Dashboard',
            'Dashboard',
            'read',
            'inbwp-dashboard',
            array($this, 'dashboard_page')
        );
        
        // Submenu Negócios
        add_submenu_page(
            'inbwp-dashboard',
            'Negócios',
            'Negócios',
            'read',
            'inbwp-negocios',
            array($this, 'negocios_page')
        );
        
        // Submenus condicionais baseados no role
        if ($is_professional || $is_owner) {
            add_submenu_page(
                'inbwp-dashboard',
                'Catálogo',
                'Catálogo',
                'edit_posts',
                'inbwp-catalogo',
                array($this, 'catalogo_page')
            );
        }
        
        if ($is_owner) {
            add_submenu_page(
                'inbwp-dashboard',
                'Financeiro',
                'Financeiro',
                'manage_options',
                'inbwp-financeiro',
                array($this, 'financeiro_page')
            );
            
            add_submenu_page(
                'inbwp-dashboard',
                'Usuários',
                'Usuários',
                'manage_options',
                'inbwp-usuarios',
                array($this, 'usuarios_page')
            );
            
            add_submenu_page(
                'inbwp-dashboard',
                'Configurações',
                'Configurações',
                'manage_options',
                'inbwp-config',
                array($this, 'config_page')
            );
        }
        
        if ($is_client) {
            add_submenu_page(
                'inbwp-dashboard',
                'Meus Negócios',
                'Meus Negócios',
                'read',
                'inbwp-cliente-negocios',
                array($this, 'cliente_negocios_page')
            );
            
            add_submenu_page(
                'inbwp-dashboard',
                'Catálogo de Serviços',
                'Catálogo',
                'read',
                'inbwp-cliente-catalogo',
                array($this, 'cliente_catalogo_page')
            );
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'inbwp') !== false) {
            wp_enqueue_script('jquery');
            wp_localize_script('jquery', 'inbwp_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('inbwp_nonce')
            ));
        }
    }
    
    // AJAX: Criar negócio
    public function ajax_create_negocio() {
        check_ajax_referer('inbwp_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(json_encode(array('success' => false, 'data' => 'Permissão negada')));
        }
        
        global $wpdb;
        $table_negocios = $wpdb->prefix . 'inbwp_negocios';
        
        // Coletar e validar dados
        $cliente_id = intval($_POST['cliente_id']);
        $servidor_id = intval($_POST['servidor_id']);
        $itens_negocio = sanitize_textarea_field($_POST['itens_negocio']);
        $endereco_cliente = sanitize_textarea_field($_POST['endereco_cliente']);
        $data_inicio = sanitize_text_field($_POST['data_inicio']);
        $data_fim = sanitize_text_field($_POST['data_fim']);
        $valor_total = floatval($_POST['valor_total']);
        $aceita_termos = intval($_POST['aceita_termos']);
        
        // Validações básicas
        if (!$cliente_id || !$servidor_id || !$itens_negocio || !$data_inicio || !$data_fim || !$valor_total) {
            wp_die(json_encode(array('success' => false, 'data' => 'Dados obrigatórios não preenchidos')));
        }
        
        // Inserir no banco
        $result = $wpdb->insert(
            $table_negocios,
            array(
                'cliente_id' => $cliente_id,
                'servidor_id' => $servidor_id,
                'itens_negocio' => $itens_negocio,
                'endereco_cliente' => $endereco_cliente,
                'data_inicio' => $data_inicio,
                'data_fim' => $data_fim,
                'valor_total' => $valor_total,
                'status' => 'ativo',
                'aceita_termos' => $aceita_termos
            ),
            array(
                '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d'
            )
        );
        
        if ($result === false) {
            wp_die(json_encode(array('success' => false, 'data' => 'Erro ao salvar no banco: ' . $wpdb->last_error)));
        }
        
        wp_die(json_encode(array('success' => true, 'data' => 'Negócio criado com sucesso!')));
    }
    
    // AJAX: Obter detalhes do negócio
    public function ajax_get_negocio_details() {
        check_ajax_referer('inbwp_nonce', 'nonce');
        
        if (!current_user_can('read')) {
            wp_die(json_encode(array('success' => false, 'data' => 'Permissão negada')));
        }
        
        global $wpdb;
        $table_negocios = $wpdb->prefix . 'inbwp_negocios';
        $negocio_id = intval($_POST['negocio_id']);
        
        if (!$negocio_id) {
            wp_die(json_encode(array('success' => false, 'data' => 'ID do negócio não fornecido')));
        }
        
        // Buscar negócio com informações do cliente e servidor
        $query = "
            SELECT n.*, 
                   c.display_name as cliente_nome,
                   s.display_name as servidor_nome
            FROM $table_negocios n
            LEFT JOIN {$wpdb->users} c ON n.cliente_id = c.ID 
            LEFT JOIN {$wpdb->users} s ON n.servidor_id = s.ID
            WHERE n.id = %d
        ";
        
        $negocio = $wpdb->get_row($wpdb->prepare($query, $negocio_id));
        
        if (!$negocio) {
            wp_die(json_encode(array('success' => false, 'data' => 'Negócio não encontrado')));
        }
        
        wp_die(json_encode(array('success' => true, 'data' => $negocio)));
    }
    
    // AJAX: Obter endereço do usuário
    public function ajax_get_user_address() {
        check_ajax_referer('inbwp_nonce', 'nonce');
        
        if (!current_user_can('read')) {
            wp_die(json_encode(array('success' => false, 'data' => 'Permissão negada')));
        }
        
        $current_user = wp_get_current_user();
        
        // Buscar dados de endereço do usuário (meta fields)
        $endereco = array(
            'cep' => get_user_meta($current_user->ID, 'cep', true),
            'logradouro' => get_user_meta($current_user->ID, 'logradouro', true),
            'numero' => get_user_meta($current_user->ID, 'numero', true),
            'complemento' => get_user_meta($current_user->ID, 'complemento', true),
            'bairro' => get_user_meta($current_user->ID, 'bairro', true),
            'cidade' => get_user_meta($current_user->ID, 'cidade', true),
            'estado' => get_user_meta($current_user->ID, 'estado', true)
        );
        
        wp_die(json_encode(array('success' => true, 'data' => $endereco)));
    }
    
    // Páginas do admin
    public function dashboard_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/dashboard.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/dashboard.php';
        } else {
            echo '<div class="wrap"><h1>Dashboard</h1><p>Arquivo dashboard.php não encontrado.</p></div>';
        }
    }
    
    public function negocios_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/negocios.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/negocios.php';
        } else {
            echo '<div class="wrap"><h1>Negócios</h1><p>Arquivo negocios.php não encontrado.</p></div>';
        }
    }
    
    public function catalogo_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/catalogo.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/catalogo.php';
        } else {
            echo '<div class="wrap"><h1>Catálogo</h1><p>Arquivo catalogo.php não encontrado.</p></div>';
        }
    }
    
    public function financeiro_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/financeiro.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/financeiro.php';
        } else {
            echo '<div class="wrap"><h1>Financeiro</h1><p>Arquivo financeiro.php não encontrado.</p></div>';
        }
    }
    
    public function usuarios_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/usuarios.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/usuarios.php';
        } else {
            echo '<div class="wrap"><h1>Usuários</h1><p>Arquivo usuarios.php não encontrado.</p></div>';
        }
    }
    
    public function config_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/config.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/config.php';
        } else {
            echo '<div class="wrap"><h1>Configurações</h1><p>Arquivo config.php não encontrado.</p></div>';
        }
    }
    
    public function cliente_negocios_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/cliente-negocios.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/cliente-negocios.php';
        } else {
            echo '<div class="wrap"><h1>Meus Negócios</h1><p>Arquivo cliente-negocios.php não encontrado.</p></div>';
        }
    }
    
    public function cliente_catalogo_page() {
        if (file_exists(INBWP_PLUGIN_PATH . 'includes/pages/cliente-catalogo.php')) {
            include_once INBWP_PLUGIN_PATH . 'includes/pages/cliente-catalogo.php';
        } else {
            echo '<div class="wrap"><h1>Catálogo de Serviços</h1><p>Arquivo cliente-catalogo.php não encontrado.</p></div>';
        }
    }
}

// Inicializar o plugin
new ImpulsionadorNegociosWP();
?>
