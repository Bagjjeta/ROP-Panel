<?php
/**
 * Template dla panelu wiadomości
 *
 * @package ROP-Panel
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="rop-messages-container">
    <h2>Twoje wiadomości</h2>
    
    <?php
    // Sprawdź czy Better Messages jest aktywny
    if (function_exists('better_messages_functions') || class_exists('Better_Messages')) {
        echo do_shortcode('[better_messages]');
    } else {
        ?>
        <div class="rop-messages-error">
            <p>Plugin Better Messages jest wymagany do wyświetlania wiadomości.</p>
            <p>Skontaktuj się z administratorem witryny, aby zainstalować i aktywować plugin Better Messages.</p>
        </div>
        <?php
    }
    ?>
</div>