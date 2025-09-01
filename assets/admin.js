jQuery(document).ready(function($) {
    // Toggle form visibility
    window.toggleForm = function() {
        var form = document.getElementById('negocio-form') || 
                   document.getElementById('usuario-form') || 
                   document.getElementById('catalogo-form') ||
                   document.getElementById('financeiro-form');
        
        if (form) {
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
                // Limpar URL se estava editando
                if (window.location.href.includes('edit=')) {
                    var url = window.location.href.split('&edit=')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        }
    };
    
    // Formatação de moeda
    $('.inbwp-money').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        value = (value / 100).toFixed(2) + '';
        value = value.replace(".", ",");
        value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
        $(this).val('R$ ' + value);
    });
    
    // Formatação de telefone
    $('.inbwp-phone').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
        }
        $(this).val(value);
    });
    
    // Confirmação de exclusão
    $('.inbwp-delete-action').on('click', function(e) {
        if (!confirm('Tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Auto-hide notices
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 5000);
    
    // Smooth scroll para formulários
    if (window.location.href.includes('edit=')) {
        setTimeout(function() {
            var form = document.getElementById('negocio-form') || 
                      document.getElementById('usuario-form') || 
                      document.getElementById('catalogo-form') ||
                      document.getElementById('financeiro-form');
            if (form) {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }, 100);
    }
});