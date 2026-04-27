<?php

class SMLF_Admin {
	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function enqueue_styles( $hook ) {
		if ( strpos( $hook, 'smlf' ) === false ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/smlf-admin.css', array(), $this->version, 'all' );

		if ( is_rtl() ) {
			wp_enqueue_style( $this->plugin_name . '-rtl', plugin_dir_url( __FILE__ ) . 'css/smlf-admin-rtl.css', array(), $this->version, 'all' );
		}
	}

	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'smlf' ) === false ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/smlf-admin.js', array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-sortable' ), $this->version, true );

		wp_localize_script( $this->plugin_name, 'smlf_admin_obj', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'smlf_admin_nonce' ),
			'i18n'     => $this->get_builder_i18n(),
			'template' => $this->get_consultation_template(),
		) );
	}

	public function add_plugin_admin_menu() {
		global $wpdb;
		$last_viewed_lead = intval( get_option( 'smlf_last_viewed_lead_id', 0 ) );
		$table_name = $wpdb->prefix . 'smlf_leads';
		$new_leads_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE id > %d AND status='completed'", $last_viewed_lead ) );

		$menu_title = __( 'Smart Forms', 'smart-multistep-lead-forms' );
		$leads_title = __( 'Leads', 'smart-multistep-lead-forms' );

		if ( $new_leads_count > 0 ) {
			$badge = ' <span class="update-plugins count-' . esc_attr($new_leads_count) . '"><span class="plugin-count">' . esc_html($new_leads_count) . '</span></span>';
			$menu_title .= $badge;
			$leads_title .= $badge;
		}

		add_menu_page(
			__( 'Smart Forms', 'smart-multistep-lead-forms' ),
			$menu_title,
			'manage_options',
			'smlf-forms',
			array( $this, 'display_forms_page' ),
			'dashicons-feedback',
			25
		);

		add_submenu_page(
			'smlf-forms',
			__( 'Forms', 'smart-multistep-lead-forms' ),
			__( 'Forms', 'smart-multistep-lead-forms' ),
			'manage_options',
			'smlf-forms',
			array( $this, 'display_forms_page' )
		);

		add_submenu_page(
			'smlf-forms',
			__( 'Add New Form', 'smart-multistep-lead-forms' ),
			__( 'Add New Form', 'smart-multistep-lead-forms' ),
			'manage_options',
			'smlf-add-form',
			array( $this, 'display_add_form_page' )
		);

		add_submenu_page(
			'smlf-forms',
			__( 'Leads', 'smart-multistep-lead-forms' ),
			$leads_title,
			'manage_options',
			'smlf-leads',
			array( $this, 'display_leads_page' )
		);

		add_submenu_page(
			'smlf-forms',
			__( 'Email Logs', 'smart-multistep-lead-forms' ),
			__( 'Email Logs', 'smart-multistep-lead-forms' ),
			'manage_options',
			'smlf-email-logs',
			array( $this, 'display_email_logs_page' )
		);

		add_submenu_page(
			'smlf-forms',
			__( 'Settings', 'smart-multistep-lead-forms' ),
			__( 'Settings', 'smart-multistep-lead-forms' ),
			'manage_options',
			'smlf-settings',
			array( $this, 'display_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'smlf_options_group', 'smlf_admin_email', array( 'sanitize_callback' => array( $this, 'sanitize_email_option' ) ) );
		register_setting( 'smlf_options_group', 'smlf_enable_partial', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox_option' ) ) );
		register_setting( 'smlf_options_group', 'smlf_webhook_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'smlf_options_group', 'smlf_captcha_method', array( 'sanitize_callback' => array( $this, 'sanitize_captcha_method' ) ) );
		register_setting( 'smlf_options_group', 'smlf_captcha_site_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'smlf_options_group', 'smlf_captcha_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	public function sanitize_email_option( $value ) {
		$email = sanitize_email( $value );
		return is_email( $email ) ? $email : get_option( 'admin_email' );
	}

	public function sanitize_checkbox_option( $value ) {
		return ! empty( $value ) ? 1 : 0;
	}

	public function sanitize_captcha_method( $value ) {
		$value   = sanitize_key( $value );
		$allowed = array( 'none', 'custom', 'recaptcha_v2', 'recaptcha_v3', 'turnstile' );
		return in_array( $value, $allowed, true ) ? $value : 'custom';
	}

	public function get_builder_i18n() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		$strings = array(
			'blocks'                  => 'Blocks',
			'builder_title'           => 'Form Builder',
			'text_input'              => 'Text Input',
			'email_input'             => 'Email Input',
			'phone_input'             => 'Phone Input',
			'long_text'               => 'Long Text',
			'file_upload'             => 'File Upload',
			'message_text'            => 'Message Text',
			'clickable_cards'         => 'Clickable Cards',
			'radio_buttons'           => 'Radio Buttons',
			'add_step'                => '+ Add Step',
			'load_template'           => 'Load consultation template',
			'save_form'               => 'Save Form',
			'form_title'              => 'Form Title',
			'captcha_method'          => 'Captcha',
			'captcha_inherit'         => 'Use global setting',
			'captcha_none'            => 'Disabled for this form',
			'captcha_custom'          => 'Custom checkbox',
			'captcha_recaptcha_v2'    => 'Google reCAPTCHA v2',
			'captcha_recaptcha_v3'    => 'Google reCAPTCHA v3',
			'captcha_turnstile'       => 'Cloudflare Turnstile',
			'captcha_gate'            => 'Show captcha',
			'captcha_before_form'     => 'Before the form starts',
			'captcha_before_submit'   => 'Before final submit',
			'captcha_on_step'         => 'Before a specific step',
			'captcha_step'            => 'Captcha step number',
			'preview_title'           => 'Live preview',
			'preview_note'            => 'This preview updates while you edit. Save the form to publish changes.',
			'step'                    => 'Step',
			'remove'                  => 'Remove',
			'condition_prefix'        => 'Condition: Go to Step',
			'condition_middle'        => 'if answer equals',
			'condition_placeholder'   => 'Option name',
			'terminal_reset'          => 'End step with reset button',
			'label'                   => 'Label',
			'required'                => 'Required',
			'options'                 => 'Options (comma separated)',
			'option_1'                => 'Option 1',
			'option_2'                => 'Option 2',
			'drag_files'              => 'Drag files here or click to upload',
			'file_note'               => 'PDF, images, documents and ZIP files up to 10MB each.',
			'back'                    => 'Back',
			'next'                    => 'Next',
			'submit'                  => 'Submit',
			'reset'                   => 'Start again',
			'save_success'            => 'Saved successfully!',
			'save_error'              => 'Error saving.',
		);

		if ( 0 === strpos( $locale, 'de_' ) ) {
			$strings = array_merge( $strings, array(
				'blocks'                => 'Bloecke',
				'builder_title'         => 'Formular-Builder',
				'text_input'            => 'Textfeld',
				'email_input'           => 'E-Mail-Feld',
				'phone_input'           => 'Telefonfeld',
				'long_text'             => 'Langer Text',
				'file_upload'           => 'Datei-Upload',
				'message_text'          => 'Hinweistext',
				'clickable_cards'       => 'Klickbare Karten',
				'radio_buttons'         => 'Radio-Buttons',
				'add_step'              => '+ Schritt hinzufuegen',
				'load_template'         => 'Beratungsvorlage laden',
				'save_form'             => 'Formular speichern',
				'form_title'            => 'Formulartitel',
				'captcha_method'        => 'Captcha',
				'captcha_inherit'       => 'Globale Einstellung verwenden',
				'captcha_none'          => 'Fuer dieses Formular deaktiviert',
				'captcha_custom'        => 'Eigene Checkbox',
				'captcha_recaptcha_v2'  => 'Google reCAPTCHA v2',
				'captcha_recaptcha_v3'  => 'Google reCAPTCHA v3',
				'captcha_turnstile'     => 'Cloudflare Turnstile',
				'captcha_gate'          => 'Captcha anzeigen',
				'captcha_before_form'   => 'Vor Formularstart',
				'captcha_before_submit' => 'Vor dem Absenden',
				'captcha_on_step'       => 'Vor einem bestimmten Schritt',
				'captcha_step'          => 'Captcha-Schrittnummer',
				'preview_title'         => 'Live-Vorschau',
				'preview_note'          => 'Diese Vorschau aktualisiert sich beim Bearbeiten. Speichern veroeffentlicht die Aenderungen.',
				'step'                  => 'Schritt',
				'remove'                => 'Entfernen',
				'condition_prefix'      => 'Bedingung: Gehe zu Schritt',
				'condition_middle'      => 'wenn Antwort gleich',
				'condition_placeholder' => 'Optionsname',
				'terminal_reset'        => 'Endschritt mit Neustart-Button',
				'label'                 => 'Beschriftung',
				'required'              => 'Pflichtfeld',
				'options'               => 'Optionen (kommagetrennt)',
				'option_1'              => 'Option 1',
				'option_2'              => 'Option 2',
				'drag_files'            => 'Dateien hierher ziehen oder klicken',
				'file_note'             => 'PDF, Bilder, Dokumente und ZIP-Dateien bis 10MB je Datei.',
				'back'                  => 'Zurueck',
				'next'                  => 'Weiter',
				'submit'                => 'Absenden',
				'reset'                 => 'Neu starten',
				'save_success'          => 'Erfolgreich gespeichert!',
				'save_error'            => 'Fehler beim Speichern.',
			) );
		}

		if ( 0 === strpos( $locale, 'fa_' ) ) {
			$strings = array_merge( $strings, array(
				'blocks'                => 'بلوک‌ها',
				'builder_title'         => 'فرم‌ساز',
				'text_input'            => 'فیلد متن',
				'email_input'           => 'فیلد ایمیل',
				'phone_input'           => 'فیلد تلفن',
				'long_text'             => 'متن بلند',
				'file_upload'           => 'آپلود فایل',
				'message_text'          => 'متن پیام',
				'clickable_cards'       => 'کارت‌های قابل کلیک',
				'radio_buttons'         => 'دکمه‌های انتخابی',
				'add_step'              => '+ افزودن مرحله',
				'load_template'         => 'بارگذاری قالب مشاوره',
				'save_form'             => 'ذخیره فرم',
				'form_title'            => 'عنوان فرم',
				'captcha_method'        => 'کپچا',
				'captcha_inherit'       => 'استفاده از تنظیمات عمومی',
				'captcha_none'          => 'غیرفعال برای این فرم',
				'captcha_custom'        => 'چک‌باکس اختصاصی',
				'captcha_recaptcha_v2'  => 'Google reCAPTCHA v2',
				'captcha_recaptcha_v3'  => 'Google reCAPTCHA v3',
				'captcha_turnstile'     => 'Cloudflare Turnstile',
				'captcha_gate'          => 'نمایش کپچا',
				'captcha_before_form'   => 'قبل از شروع فرم',
				'captcha_before_submit' => 'قبل از ثبت نهایی',
				'captcha_on_step'       => 'قبل از یک مرحله مشخص',
				'captcha_step'          => 'شماره مرحله کپچا',
				'preview_title'         => 'پیش‌نمایش زنده',
				'preview_note'          => 'این پیش‌نمایش هنگام ویرایش به‌روز می‌شود. برای انتشار تغییرات فرم را ذخیره کنید.',
				'step'                  => 'مرحله',
				'remove'                => 'حذف',
				'condition_prefix'      => 'شرط: برو به مرحله',
				'condition_middle'      => 'اگر پاسخ برابر بود با',
				'condition_placeholder' => 'نام گزینه',
				'terminal_reset'        => 'مرحله پایانی با دکمه شروع دوباره',
				'label'                 => 'برچسب',
				'required'              => 'اجباری',
				'options'               => 'گزینه‌ها (جدا شده با ویرگول)',
				'option_1'              => 'گزینه ۱',
				'option_2'              => 'گزینه ۲',
				'drag_files'            => 'فایل‌ها را اینجا بکشید یا کلیک کنید',
				'file_note'             => 'PDF، تصویر، سند و ZIP تا ۱۰MB برای هر فایل.',
				'back'                  => 'بازگشت',
				'next'                  => 'بعدی',
				'submit'                => 'ثبت',
				'reset'                 => 'شروع دوباره',
				'save_success'          => 'با موفقیت ذخیره شد!',
				'save_error'            => 'خطا در ذخیره.',
			) );
		}

		return $strings;
	}

	public function get_consultation_template() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-smlf-activator.php';
		$template = SMLF_Activator::get_default_consultation_template();

		return array(
			'title' => $template['title'],
			'steps' => array(
				array(
					'step_id'      => 1,
					'title'        => $template['step_start'],
					'logic_target' => 4,
					'logic_value'  => $template['no'],
					'fields'       => array(
						array(
							'field_id' => 'need_consultation',
							'type'     => 'message',
							'label'    => $template['question'],
							'options'  => '',
							'required' => 0,
						),
						array(
							'field_id' => 'consultation_answer',
							'type'     => 'cards',
							'label'    => $template['choice'],
							'options'  => $template['yes'] . ', ' . $template['no'],
							'required' => 1,
						),
					),
				),
				array(
					'step_id'      => 2,
					'title'        => $template['step_basics'],
					'logic_target' => 0,
					'logic_value'  => '',
					'fields'       => array(
						array(
							'field_id' => 'business_category',
							'type'     => 'cards',
							'label'    => $template['category'],
							'options'  => implode( ', ', $template['categories'] ),
							'required' => 1,
						),
						array(
							'field_id' => 'email',
							'type'     => 'email',
							'label'    => $template['email'],
							'options'  => '',
							'required' => 1,
						),
					),
				),
				array(
					'step_id'      => 3,
					'title'        => $template['step_details'],
					'logic_target' => 0,
					'logic_value'  => '',
					'fields'       => array(
						array(
							'field_id' => 'full_name',
							'type'     => 'text',
							'label'    => $template['name'],
							'options'  => '',
							'required' => 0,
						),
						array(
							'field_id' => 'phone',
							'type'     => 'phone',
							'label'    => $template['phone'],
							'options'  => '',
							'required' => 0,
						),
						array(
							'field_id' => 'project_details',
							'type'     => 'textarea',
							'label'    => $template['details'],
							'options'  => '',
							'required' => 0,
						),
						array(
							'field_id' => 'attachments',
							'type'     => 'file',
							'label'    => $template['files'],
							'options'  => '',
							'required' => 0,
						),
					),
				),
				array(
					'step_id'      => 4,
					'title'        => $template['step_decline'],
					'terminal'     => 'reset',
					'logic_target' => 0,
					'logic_value'  => '',
					'fields'       => array(
						array(
							'field_id' => 'decline_message',
							'type'     => 'message',
							'label'    => $template['decline'],
							'options'  => '',
							'required' => 0,
						),
					),
				),
			),
		);
	}

	public function display_forms_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/smlf-forms-list.php';
		$this->render_footer();
	}

	public function display_add_form_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/smlf-form-builder.php';
		$this->render_footer();
	}

	public function display_leads_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/smlf-leads-list.php';
		$this->render_footer();
	}

	public function display_email_logs_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/smlf-email-logs.php';
		$this->render_footer();
	}

	public function display_settings_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/smlf-settings.php';
		$this->render_footer();
	}

	private function render_footer() {
		$author_name = defined('SMLF_AUTHOR_NAME') ? SMLF_AUTHOR_NAME : 'Mohammad Babaei';
		$author_url  = defined('SMLF_AUTHOR_URL') ? SMLF_AUTHOR_URL : 'https://adschi.com';
		$version     = defined('SMLF_VERSION') ? SMLF_VERSION : '1.0.0';

		echo '<div class="smlf-admin-footer" style="margin-top: 40px; text-align: center; color: #666; font-size: 13px; padding: 20px 0; border-top: 1px solid #ddd;">';
		echo sprintf(
			/* translators: 1: Author name, 2: Author URL, 3: Plugin version */
			__( 'Developed by <a href="%2$s" target="_blank">%1$s</a> | Version %3$s', 'smart-multistep-lead-forms' ),
			esc_html( $author_name ),
			esc_url( $author_url ),
			esc_html( $version )
		);
		echo '</div>';
	}
}
