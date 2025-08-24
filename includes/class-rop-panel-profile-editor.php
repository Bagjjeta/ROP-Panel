<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Panel_Profile_Editor {
    
    public function __construct() {
        add_action('wp_ajax_rop_get_company_profile_form', array($this, 'get_company_profile_form'));
        add_action('wp_ajax_rop_save_company_profile', array($this, 'save_company_profile'));
        add_action('wp_ajax_rop_upload_company_logo', array($this, 'upload_company_logo'));
        add_action('wp_ajax_rop_delete_company_logo', array($this, 'delete_company_logo')); // Nowa akcja
        
        // Auto-load profilu przy ładowaniu strony panelu
        add_action('wp_ajax_rop_auto_load_profile', array($this, 'auto_load_profile'));
        add_action('wp_ajax_nopriv_rop_auto_load_profile', array($this, 'auto_load_profile'));
    }
    
    // Nowa metoda do auto-ładowania
    public function auto_load_profile() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }
        
        $user_id = get_current_user_id();
        $user_data = $this->get_user_company_data($user_id);
        
        $form_html = $this->render_company_profile_form($user_data);
        
        wp_send_json_success(array(
            'content' => $form_html
        ));
    }
    
    public function get_company_profile_form() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }
        
        $user_id = get_current_user_id();
        $user_data = $this->get_user_company_data($user_id);
        
        $form_html = $this->render_company_profile_form($user_data);
        
        wp_send_json_success(array(
            'title' => __('Profil firmy', 'rop_panel'),
            'content' => $form_html
        ));
    }
    
    // Nowa metoda do usuwania logo
    public function delete_company_logo() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }
        
        $user_id = get_current_user_id();
        $old_logo = get_user_meta($user_id, 'rop_company_logo', true);
        
        if ($old_logo) {
            // Usuń plik z serwera
            $upload_dir = wp_upload_dir();
            $rop_upload_dir = $upload_dir['basedir'] . '/rop_panel/company_logos';
            $rop_upload_url = $upload_dir['baseurl'] . '/rop_panel/company_logos';
            
            $file_path = str_replace($rop_upload_url, $rop_upload_dir, $old_logo);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Usuń z bazy danych
            delete_user_meta($user_id, 'rop_company_logo');
            
            wp_send_json_success(__('Logo zostało usunięte.', 'rop_panel'));
        } else {
            wp_send_json_error(__('Brak logo do usunięcia.', 'rop_panel'));
        }
    }
    
    public function save_company_profile() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }
        
        $user_id = get_current_user_id();
        
        // Sanityzacja danych
        $company_data = array(
            'company_name' => sanitize_text_field($_POST['company_name']),
            'nip' => sanitize_text_field($_POST['nip']),
            'address' => sanitize_text_field($_POST['address']),
            'city' => sanitize_text_field($_POST['city']),
            'postal_code' => sanitize_text_field($_POST['postal_code']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'website' => esc_url_raw($_POST['website']),
            'industry' => sanitize_text_field($_POST['industry']),
            'description' => sanitize_textarea_field($_POST['description'])
        );
        
        // Walidacja
        $errors = array();
        
        if (empty($company_data['company_name'])) {
            $errors[] = __('Nazwa firmy jest wymagana.', 'rop_panel');
        }
        
        if (!empty($company_data['email']) && !is_email($company_data['email'])) {
            $errors[] = __('Nieprawidłowy adres email.', 'rop_panel');
        }
        
        if (!empty($company_data['website']) && !filter_var($company_data['website'], FILTER_VALIDATE_URL)) {
            $errors[] = __('Nieprawidłowy adres strony internetowej.', 'rop_panel');
        }
        
        if (!empty($errors)) {
            wp_send_json_error(implode('<br>', $errors));
        }
        
        // Zapisz dane
        foreach ($company_data as $key => $value) {
            update_user_meta($user_id, 'rop_' . $key, $value);
        }
        
        // Aktualizuj też pola Ultimate Members jeśli jest aktywny
        if (function_exists('um_user')) {
            update_user_meta($user_id, 'company', $company_data['company_name']);
            update_user_meta($user_id, 'organization', $company_data['company_name']);
        }
        
        wp_send_json_success(__('Profil firmy został zaktualizowany.', 'rop_panel'));
    }
    
    public function upload_company_logo() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }
        
        if (empty($_FILES['logo'])) {
            wp_send_json_error(__('Nie wybrano pliku.', 'rop_panel'));
        }
        
        $file = $_FILES['logo'];
        
        // Walidacja pliku
        $allowed_types = array('image/jpeg', 'image/png', 'image/jpg');
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(__('Nieprawidłowy typ pliku. Dozwolone są tylko PNG i JPG.', 'rop_panel'));
        }
        
        if ($file['size'] > $max_size) {
            wp_send_json_error(__('Plik jest zbyt duży. Maksymalny rozmiar to 2MB.', 'rop_panel'));
        }
        
        // Upload pliku
        $upload_dir = wp_upload_dir();
        $rop_upload_dir = $upload_dir['basedir'] . '/rop_panel/company_logos';
        $rop_upload_url = $upload_dir['baseurl'] . '/rop_panel/company_logos';
        
        if (!file_exists($rop_upload_dir)) {
            wp_mkdir_p($rop_upload_dir);
        }
        
        $user_id = get_current_user_id();
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'company_logo_' . $user_id . '_' . time() . '.' . $file_extension;
        $file_path = $rop_upload_dir . '/' . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $file_url = $rop_upload_url . '/' . $new_filename;
            
            // Usuń stare logo jeśli istnieje
            $old_logo = get_user_meta($user_id, 'rop_company_logo', true);
            if ($old_logo && file_exists(str_replace($rop_upload_url, $rop_upload_dir, $old_logo))) {
                unlink(str_replace($rop_upload_url, $rop_upload_dir, $old_logo));
            }
            
            // Zapisz nowe logo
            update_user_meta($user_id, 'rop_company_logo', $file_url);
            
            wp_send_json_success(array(
                'message' => __('Logo zostało przesłane pomyślnie.', 'rop_panel'),
                'logo_url' => $file_url
            ));
        } else {
            wp_send_json_error(__('Błąd podczas przesyłania pliku.', 'rop_panel'));
        }
    }
    
    private function get_user_company_data($user_id) {
        return array(
            'company_name' => get_user_meta($user_id, 'rop_company_name', true),
            'nip' => get_user_meta($user_id, 'rop_nip', true),
            'address' => get_user_meta($user_id, 'rop_address', true),
            'city' => get_user_meta($user_id, 'rop_city', true),
            'postal_code' => get_user_meta($user_id, 'rop_postal_code', true),
            'phone' => get_user_meta($user_id, 'rop_phone', true),
            'email' => get_user_meta($user_id, 'rop_email', true),
            'website' => get_user_meta($user_id, 'rop_website', true),
            'industry' => get_user_meta($user_id, 'rop_industry', true),
            'description' => get_user_meta($user_id, 'rop_description', true),
            'logo' => get_user_meta($user_id, 'rop_company_logo', true)
        );
    }
    
		private function render_company_profile_form($data) {
		ob_start();
		?>
		<div class="rop-company-profile-form">
			<form id="rop-company-profile-form">

				<div class="rop-form-section">
					<p class="rop-form-description"><?php _e('Zarządzaj informacjami o swojej firmie i logo', 'rop_panel'); ?></p>

					<!-- Logo firmy -->
					<div class="rop-form-group">
						<label class="rop-form-label"><?php _e('Logo firmy', 'rop_panel'); ?></label>
						<p class="rop-form-hint"><?php _e('Dodaj lub zmień logo swojej firmy', 'rop_panel'); ?></p>

						<div class="rop-logo-upload">
							<div class="rop-logo-preview">
								<?php if (!empty($data['logo'])): ?>
									<img src="<?php echo esc_url($data['logo']); ?>" alt="Logo firmy" id="rop-logo-image">
									<div class="rop-logo-overlay">
										<button type="button" class="rop-logo-delete" id="rop-logo-delete-btn" title="Usuń logo">
											<span>&times;</span>
										</button>
									</div>
								<?php else: ?>
									<div class="rop-logo-placeholder" id="rop-logo-placeholder">
										<i class="rop-icon-image"></i>
									</div>
								<?php endif; ?>
							</div>
							<div class="rop-logo-actions">
								<input type="file" id="rop-logo-input" accept=".png,.jpg,.jpeg" style="display: none;">
								<button type="button" class="rop-btn rop-btn-primary" id="rop-logo-upload-btn">
									<i class="rop-icon-upload"></i> <?php _e('Prześlij logo', 'rop_panel'); ?>
								</button>
								<p class="rop-file-info"><?php _e('PNG, JPG do 2MB', 'rop_panel'); ?></p>
							</div>
						</div>
					</div>
				</div>

				<div class="rop-form-section">
					<h3><?php _e('Informacje o firmie', 'rop_panel'); ?></h3>
					<p class="rop-form-description"><?php _e('Podstawowe dane kontaktowe i informacje o działalności', 'rop_panel'); ?></p>

					<div class="rop-form-row">
						<div class="rop-form-group rop-form-group-required">
							<label for="company_name" class="rop-form-label"><?php _e('Nazwa firmy', 'rop_panel'); ?></label>
							<input type="text" id="company_name" name="company_name" class="rop-form-control" 
								   value="<?php echo esc_attr($data['company_name']); ?>" required>
						</div>

						<div class="rop-form-group">
							<label for="nip" class="rop-form-label"><?php _e('NIP', 'rop_panel'); ?></label>
							<input type="text" id="nip" name="nip" class="rop-form-control" 
								   value="<?php echo esc_attr($data['nip']); ?>" placeholder="123-456-78-90">
						</div>
					</div>

					<div class="rop-form-row">
						<div class="rop-form-group rop-form-group-required">
							<label for="address" class="rop-form-label"><?php _e('Adres', 'rop_panel'); ?></label>
							<input type="text" id="address" name="address" class="rop-form-control" 
								   value="<?php echo esc_attr($data['address']); ?>" placeholder="ul. Przykładowa 123">
						</div>

						<div class="rop-form-group rop-form-group-required">
							<label for="city" class="rop-form-label"><?php _e('Miasto', 'rop_panel'); ?></label>
							<input type="text" id="city" name="city" class="rop-form-control" 
								   value="<?php echo esc_attr($data['city']); ?>" placeholder="Częstochowa">
						</div>
					</div>

					<div class="rop-form-row">
						<div class="rop-form-group">
							<label for="postal_code" class="rop-form-label"><?php _e('Kod pocztowy', 'rop_panel'); ?></label>
							<input type="text" id="postal_code" name="postal_code" class="rop-form-control" 
								   value="<?php echo esc_attr($data['postal_code']); ?>" placeholder="42-200">
						</div>

						<div class="rop-form-group rop-form-group-required">
							<label for="phone" class="rop-form-label"><?php _e('Telefon', 'rop_panel'); ?></label>
							<input type="tel" id="phone" name="phone" class="rop-form-control" 
								   value="<?php echo esc_attr($data['phone']); ?>" placeholder="+48 34 123 45 67">
						</div>
					</div>

					<div class="rop-form-row">
						<div class="rop-form-group rop-form-group-required">
							<label for="email" class="rop-form-label"><?php _e('Email', 'rop_panel'); ?></label>
							<input type="email" id="email" name="email" class="rop-form-control" 
								   value="<?php echo esc_attr($data['email']); ?>" placeholder="kontakt@abcconsulting.pl">
						</div>

						<div class="rop-form-group">
							<label for="website" class="rop-form-label"><?php _e('Strona internetowa', 'rop_panel'); ?></label>
							<input type="url" id="website" name="website" class="rop-form-control" 
								   value="<?php echo esc_attr($data['website']); ?>" placeholder="https://www.abcconsulting.pl">
						</div>
					</div>

					<div class="rop-form-group">
						<label for="industry" class="rop-form-label"><?php _e('Branża', 'rop_panel'); ?></label>
						<select id="industry" name="industry" class="rop-form-control">
							<option value=""><?php _e('Wybierz branżę', 'rop_panel'); ?></option>
							<option value="consulting" <?php selected($data['industry'], 'consulting'); ?>><?php _e('Doradztwo biznesowe', 'rop_panel'); ?></option>
							<option value="it" <?php selected($data['industry'], 'it'); ?>><?php _e('IT i technologie', 'rop_panel'); ?></option>
							<option value="finance" <?php selected($data['industry'], 'finance'); ?>><?php _e('Finanse i bankowość', 'rop_panel'); ?></option>
							<option value="manufacturing" <?php selected($data['industry'], 'manufacturing'); ?>><?php _e('Produkcja', 'rop_panel'); ?></option>
							<option value="services" <?php selected($data['industry'], 'services'); ?>><?php _e('Usługi', 'rop_panel'); ?></option>
							<option value="trade" <?php selected($data['industry'], 'trade'); ?>><?php _e('Handel', 'rop_panel'); ?></option>
							<option value="other" <?php selected($data['industry'], 'other'); ?>><?php _e('Inne', 'rop_panel'); ?></option>
						</select>
					</div>

					<div class="rop-form-group">
						<label for="description" class="rop-form-label"><?php _e('Opis działalności', 'rop_panel'); ?></label>
						<textarea id="description" name="description" class="rop-form-control" rows="4" 
								  placeholder="<?php _e('Krótki opis działalności firmy...', 'rop_panel'); ?>"><?php echo esc_textarea($data['description']); ?></textarea>
					</div>
				</div>

				<div class="rop-form-footer">
					<button type="button" class="rop-btn rop-btn-secondary" id="rop-cancel-profile">
						<?php _e('Anuluj', 'rop_panel'); ?>
					</button>
					<button type="submit" class="rop-btn rop-btn-primary" id="rop-save-profile">
						<i class="rop-icon-save"></i> <?php _e('Zapisz zmiany', 'rop_panel'); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}