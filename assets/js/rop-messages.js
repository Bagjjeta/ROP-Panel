jQuery(document).ready(function($) {
    
    // Handler dla kliknięcia w kontener wiadomości
    $(document).on('click', '#rop-messages', function(e) {
        e.preventDefault();
        
        console.log('ROP Messages: Loading messages panel...');
        
        // Pokaż loading w panel-container
        $('#panel-container').html('<div class="rop-loading">Ładowanie wiadomości...</div>');
        
        // AJAX request do załadowania shortcode Better Messages
        $.ajax({
            url: rop_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rop_load_messages',
                nonce: rop_ajax.nonce
            },
            success: function(response) {
                console.log('ROP Messages: Response received', response);
                
                if (response.success) {
                    // Wstaw zawartość Better Messages do panel-container
                    $('#panel-container').html(response.data.content);
                    
                    // Zaktualizuj aktywną zakładkę
                    $('.rop-tab').removeClass('active');
                    $('#rop-messages').addClass('active');
                    
                    console.log('ROP Messages: Panel loaded successfully');
                } else {
                    $('#panel-container').html('<div class="rop-error">Błąd: ' + (response.data || 'Nie udało się załadować wiadomości') + '</div>');
                    console.error('ROP Messages: Error loading messages', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('ROP Messages: AJAX error', status, error);
                $('#panel-container').html('<div class="rop-error">Wystąpił błąd podczas ładowania wiadomości. Spróbuj ponownie.</div>');
            }
        });
    });
    
    console.log('ROP Messages: JavaScript initialized');
});