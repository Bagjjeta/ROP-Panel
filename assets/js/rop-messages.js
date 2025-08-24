/**
 * Obsługa panelu wiadomości
 * 
 * @package ROP-Panel
 */

jQuery(document).ready(function($) {
    console.log('ROP Messages: Script loaded');
    
    // Obsługa kliknięcia na przycisk wiadomości
    $('#rop-messages').on('click', function(e) {
        e.preventDefault();
        console.log('ROP Messages: Button clicked');
        
        // Pokaż loader
        $('#panel-container').html('<div class="rop-loading">Ładowanie wiadomości...</div>');
        
        // Pobierz zawartość panelu wiadomości
        $.ajax({
            url: rop_panel_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'rop_load_messages_panel',
                security: rop_panel_vars.nonce
            },
            success: function(response) {
                console.log('ROP Messages: Content loaded successfully');
                // Zastąp zawartość kontenera
                $('#panel-container').html(response);
                
                // Oznacz aktywny element menu
                $('.rop-menu-item').removeClass('active');
                $('#rop-messages').addClass('active');
            },
            error: function(xhr, status, error) {
                console.error('ROP Messages: Error loading content', error);
                $('#panel-container').html('<div class="rop-error">Wystąpił błąd podczas ładowania wiadomości.</div>');
            }
        });
    });
});