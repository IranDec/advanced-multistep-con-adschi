<div class="wrap smlf-builder-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Form Builder', 'smart-multistep-lead-forms' ); ?></h1>
	<hr class="wp-header-end">

	<div id="smlf-builder-app">
		<div class="smlf-builder-sidebar">
			<h3><?php esc_html_e( 'Blocks', 'smart-multistep-lead-forms' ); ?></h3>
			<ul class="smlf-draggable-blocks">
				<li data-type="text"><?php esc_html_e( 'Text Input', 'smart-multistep-lead-forms' ); ?></li>
				<li data-type="email"><?php esc_html_e( 'Email Input', 'smart-multistep-lead-forms' ); ?></li>
				<li data-type="phone"><?php esc_html_e( 'Phone Input', 'smart-multistep-lead-forms' ); ?></li>
				<li data-type="cards"><?php esc_html_e( 'Clickable Cards', 'smart-multistep-lead-forms' ); ?></li>
				<li data-type="radio"><?php esc_html_e( 'Radio Buttons', 'smart-multistep-lead-forms' ); ?></li>
			</ul>
			<button class="button button-primary" id="smlf-add-step"><?php esc_html_e( '+ Add Step', 'smart-multistep-lead-forms' ); ?></button>
		</div>

		<div class="smlf-builder-canvas">
			<div class="smlf-form-settings">
				<input type="text" id="smlf-form-title" placeholder="<?php esc_attr_e( 'Form Title', 'smart-multistep-lead-forms' ); ?>" value="New Form" />
				<button class="button button-primary" id="smlf-save-form"><?php esc_html_e( 'Save Form', 'smart-multistep-lead-forms' ); ?></button>
			</div>

			<div id="smlf-steps-container">
				<!-- Steps will be rendered here via JS -->
			</div>
		</div>
	</div>
</div>
