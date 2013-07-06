<?php


class Incsub_Subscribe_By_Email_Admin_Add_Subscribers_Page extends Incsub_Subscribe_By_Email_Admin_Page {	

	private static $errors;

	public function __construct() {

		$subscribers_page = Incsub_Subscribe_By_Email::$admin_subscribers_page;
		
		$args = array(
			'slug' => 'sbe-add-subscribers',
			'page_title' => __( 'Add New', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Add Subscribers', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

		add_action( 'admin_init', array( &$this, 'validate_form' ) );

	}


	public function render_content() {

		?>
			

				<?php 
					$errors = get_settings_errors( 'subscribe' ); 
					if ( ! empty( $errors ) ) {
						?>	
							<div class="error">
								<ul>
									<?php
									foreach ( $errors as $error ) {
										?>
											<li><?php echo $error['message']; ?></li>
										<?php
									}
									?>
								</ul>
							</div>
						<?php
					}
					elseif ( isset( $_GET['user-subscribed'] ) ) {
						?>
							<div class="updated"><p><?php printf( __( 'Subscription added. He has %d days to confirm his subscription or he will be removed from the list.', INCSUB_SBE_LANG_DOMAIN ), Incsub_Subscribe_By_Email::$max_confirmation_time / ( 60 * 60 * 24 ) ); ?></p></div>
						<?php
					}
					elseif ( isset( $_GET['users-subscribed'] ) ) {
						?>
							<div class="updated"><p><?php printf( __( '%d subscriptions created out of %d e-mail addresses. They have %d days to confirm their subscriptions or they will be removed from the list.', INCSUB_SBE_LANG_DOMAIN ), $_GET['subscribed'], $_GET['total'], Incsub_Subscribe_By_Email::$max_confirmation_time / ( 60 * 60 * 24 ) ); ?></p></div>
						<?php
					}
				?>

				<form action="" id="add-single-subscriber" method="post">
					<h3><?php _e( 'Subscribe a single user', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
					<?php wp_nonce_field( 'subscribe', '_wpnonce' ); ?>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row"><?php _e( 'Email', INCSUB_SBE_LANG_DOMAIN ); ?></th>
								<td>
									<input type="text" class="regular-text" name="subscribe-email">
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Subscribe', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit-single' ); ?>
				</form>

				<form action="" id="import-subscribers" method="post" enctype="multipart/form-data">
					<h3><?php _e( 'Import subscribers', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
					<?php wp_nonce_field( 'subscribe', '_wpnonce' ); ?>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row"><?php _e( 'CSV file', INCSUB_SBE_LANG_DOMAIN ); ?></th>
								<td>
									<input type="file" class="regular-text" name="subscribe-file">
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Import', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit-bulk' ); ?>
				</form>

			

		<?php
	}

	public function validate_form() {
		
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] ) {

			$input = $_POST;

			if ( isset( $input['submit-single'] ) ) {
				
				if ( ! wp_verify_nonce( $input['_wpnonce'], 'subscribe' ) )
					return false;

				// We are submitting a single user
				$email = sanitize_email( $input['subscribe-email'] );
				if ( is_email( $email ) ) {
					$model = Incsub_Subscribe_By_Email_Model::get_instance();
					$result = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'Manual Subscription', INCSUB_SBE_LANG_DOMAIN ), __( 'Instant', INCSUB_SBE_LANG_DOMAIN ) );
				}
				else {
					// Email not valid
					add_settings_error( 'subscribe', 'email', __( 'The email is not a valid one', INCSUB_SBE_LANG_DOMAIN ) );
				}

				$errors = get_settings_errors( 'subscribe' ); 
				if ( empty( $errors ) ) {
					wp_redirect( add_query_arg( 
						array(
							'page' => $this->get_menu_slug(),
							'user-subscribed' => 'true'
						),
						admin_url( 'admin.php' ) )
					);
				}
			}

			if ( isset( $input['submit-bulk'] ) ) {

				if ( ! wp_verify_nonce( $input['_wpnonce'], 'subscribe' ) )
					return false;
				
				if ( ! isset( $_FILES['subscribe-file'] ) ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}
				if ( $_FILES['subscribe-file']['error'] ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}

				$types = array( 'application/vnd.ms-excel','text/plain','text/csv','text/tsv', 'application/octet-stream' );

				if ( ! in_array( $_FILES['subscribe-file']['type'], $types ) ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}

				if ( preg_match( '/\.csv$/', $_FILES['subscribe-file']['name'] ) == 0 ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}

				if ( ( $handle = fopen( $_FILES['subscribe-file']['tmp_name'], 'r' ) ) !== false ) {
					
					$subscribed_c = 0;
					$row_c = 0;
					$email_col = -1;

					while ( ( $row = fgetcsv( $handle, null, ',', '"' ) ) !== false ) {
						$cols = count( $row );
						$row_c++;
						if ( $email_col == -1 ) {
							for ( $c = 0; $c < $cols; $c++ ) {
								if ( is_email( sanitize_email( $row[ $c ] ) ) ) {
									$email_col = $c;
									break;
								}
							}
						}
						if ( is_email( sanitize_email( $row[$email_col] ) ) && Incsub_Subscribe_By_Email::subscribe_user( sanitize_email( $row[$email_col] ), __( 'Manual Subscription', INCSUB_SBE_LANG_DOMAIN ), __( 'Import', INCSUB_SBE_LANG_DOMAIN ) ) )
							$subscribed_c++;
					}

					fclose( $handle );

					wp_redirect( add_query_arg( 
						array(
							'page' => $this->get_menu_slug(),
							'users-subscribed' => 'true',
							'total' => $row_c,
							'subscribed' => $subscribed_c,
						),
						admin_url( 'admin.php' ) )
					);

				} 
				else {
					add_settings_error( 'subscribe', 'email', __( 'Failed to open file', INCSUB_SBE_LANG_DOMAIN ) );
				}
			}

		}
		
	}
}
