<div class="smlf-form-wrapper" id="smlf-form-<?php echo esc_attr( $form_id ); ?>" data-form-id="<?php echo esc_attr( $form_id ); ?>">

	<!-- Anti-bot Overlay -->
	<div class="smlf-anti-bot-overlay">
		<div class="smlf-anti-bot-modal">
			<h3><?php esc_html_e( 'Security Check', 'smart-multistep-lead-forms' ); ?></h3>
			<p><?php esc_html_e( 'Please verify you are human.', 'smart-multistep-lead-forms' ); ?></p>
			<label>
				<input type="checkbox" id="smlf-bot-check-<?php echo esc_attr( $form_id ); ?>"> <?php esc_html_e( 'I am not a robot', 'smart-multistep-lead-forms' ); ?>
			</label>
			<button type="button" class="smlf-btn-verify"><?php esc_html_e( 'Verify', 'smart-multistep-lead-forms' ); ?></button>
		</div>
	</div>

	<!-- Progress Bar -->
	<div class="smlf-progress-bar-container" style="display:none;">
		<div class="smlf-progress-bar" style="width: 0%;"></div>
	</div>

	<!-- Form Steps -->
	<form class="smlf-form-actual" style="display:none;">
		<?php if ( ! empty( $steps ) ) : ?>
			<?php foreach ( $steps as $index => $step ) : ?>
				<div class="smlf-form-step" data-step-index="<?php echo esc_attr( $index ); ?>" <?php if($index > 0) echo 'style="display:none;"'; ?>>

					<?php foreach ( $step['fields'] as $field ) : ?>
						<div class="smlf-field-row">
							<label><?php echo esc_html( $field['label'] ); ?></label>

							<?php if ( $field['type'] === 'text' ) : ?>
								<input type="text" name="smlf_field_<?php echo esc_attr( $index . '_' . sanitize_title($field['label']) ); ?>" class="smlf-input">

							<?php elseif ( $field['type'] === 'email' ) : ?>
								<input type="email" name="smlf_field_<?php echo esc_attr( $index . '_' . sanitize_title($field['label']) ); ?>" class="smlf-input smlf-critical-field" required>

							<?php elseif ( $field['type'] === 'phone' ) : ?>
								<input type="tel" name="smlf_field_<?php echo esc_attr( $index . '_' . sanitize_title($field['label']) ); ?>" class="smlf-input smlf-critical-field">

							<?php elseif ( $field['type'] === 'cards' ) : ?>
								<div class="smlf-cards-container">
									<label class="smlf-card">
										<input type="radio" name="smlf_field_<?php echo esc_attr( $index . '_' . sanitize_title($field['label']) ); ?>" value="Option 1">
										<span class="smlf-card-content">Option 1</span>
									</label>
									<label class="smlf-card">
										<input type="radio" name="smlf_field_<?php echo esc_attr( $index . '_' . sanitize_title($field['label']) ); ?>" value="Option 2">
										<span class="smlf-card-content">Option 2</span>
									</label>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<div class="smlf-step-navigation">
						<?php if ( $index > 0 ) : ?>
							<button type="button" class="smlf-btn-prev"><?php esc_html_e( 'Back', 'smart-multistep-lead-forms' ); ?></button>
						<?php endif; ?>

						<?php if ( $index < count( $steps ) - 1 ) : ?>
							<button type="button" class="smlf-btn-next"><?php esc_html_e( 'Next', 'smart-multistep-lead-forms' ); ?></button>
						<?php else : ?>
							<button type="button" class="smlf-btn-submit"><?php esc_html_e( 'Submit', 'smart-multistep-lead-forms' ); ?></button>
						<?php endif; ?>
					</div>

				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'This form has no steps yet.', 'smart-multistep-lead-forms' ); ?></p>
		<?php endif; ?>
	</form>

	<div class="smlf-success-message" style="display:none;">
		<h3><?php esc_html_e( 'Thank you!', 'smart-multistep-lead-forms' ); ?></h3>
		<p><?php esc_html_e( 'Your submission has been received.', 'smart-multistep-lead-forms' ); ?></p>
	</div>
</div>
