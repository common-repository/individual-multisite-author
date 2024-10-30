<?php
/**
 * Plugin Name: Individual Multisite Author
 * Description: Use individual author descriptions for each site on WordPress multisites
 * Version: 1.4.0
 * Plugin URI: http://webgilde.com/
 * Author: Thomas Maier
 * Author URI: http://webgilde.com/
 * License: GPL v2 or later
 *

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
//avoid direct calls to this file
if ( ! function_exists( 'is_multisite' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

define( 'IMAVERSION', '1.4.0' );
define( 'IMADIR', basename( dirname( __FILE__ ) ) );
define( 'IMAPATH', plugin_dir_path( __FILE__ ) );

if ( ! is_multisite() ) {
	add_action( 'admin_notices', 'ima_not_a_multisite' );

	return;
}

/**
 * Show an admin notice error message, if the `vendor` folder is missing.
 */
function ima_not_a_multisite() {
	echo '<div class="error"><p>';
	esc_html_e( 'Individual Multisite Author only works with a multisite installation.', 'individual-multisite-author' );
	echo '</p></div>';
}

// load the plugin only on multisites
if ( ! class_exists( 'Ima_Class', false ) ) {

	class Ima_Class {

		/**
		 * Stores the display name field name for the current site.
		 *
		 * @var string
		 */
		public $display_name_field_name;

		/**
		 * Stores the discription field name for the current site.
		 *
		 * @var string
		 */
		public $description_field_name;

		/**
		 * initialize the plugin
		 * @since 1.0
		 */
		public function __construct() {
			add_action( 'show_user_profile', array( $this, 'add_custom_profile_fields' ) );
			add_action( 'edit_user_profile', array( $this, 'add_custom_profile_fields' ) );
			add_action( 'personal_options_update', array( $this, 'save_custom_profile_fields' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_custom_profile_fields' ) );
			add_filter( 'the_author', array( $this, 'the_author' ) ); // used in the loop - sadly the_author() does not use get_the_author_display_name filter
			add_filter( 'get_the_author_display_name', array( $this, 'get_the_author_display_name' ), 10, 2 );
			add_filter( 'get_the_author_description', array( $this, 'get_the_author_description' ), 10, 2 );
			$this->display_name_field_name = 'ima_display_name_' . get_current_blog_id();
			$this->description_field_name = 'ima_description_' . get_current_blog_id();
		}

		/**
		 * construct the form fields for the author descriptions
		 * @since 1.0
		 * @param array $user user data
		 */
		public function add_custom_profile_fields($user) {
			wp_nonce_field( 'ima_user_profile_update', 'ima_user_profile_nonce' );
			?>
			<h3><?php esc_html_e( 'Site-specific author information', 'individual-multisite-author' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><label for="ima_display_name"><?php esc_html_e( 'Site-specific display name', 'individual-multisite-author' ); ?></label></th>
					<td>
						<input type="text" name="<?php echo esc_attr( $this->display_name_field_name ); ?>" id="ima_display_name" class="regular-text" value="<?php echo esc_attr( get_the_author_meta( $this->display_name_field_name, $user->ID ) ); ?>"/>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
								// translators: %s: the home_url
									__( 'Display name for %s', 'individual-multisite-author' ),
									home_url()
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="ima_description"><?php esc_html_e( 'Site-specific biography', 'individual-multisite-author' ); ?></label></th>
					<td>
						<textarea cols="30" rows="5" name="<?php echo esc_attr( $this->description_field_name ); ?>" id="ima_description"><?php echo esc_attr( get_the_author_meta( $this->description_field_name, $user->ID ) ); ?></textarea>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
								// translators: %s: the home_url
									__( 'Biography for %s', 'individual-multisite-author' ),
									home_url()
								)
							);
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php
		}

		/**
		 * update the user descriptions
		 * @since 1.0
		 * @param int $user_id
		 */
		public function save_custom_profile_fields( $user_id ) {
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return;
			}

			if ( ! check_admin_referer( 'ima_user_profile_update', 'ima_user_profile_nonce' ) ) {
				return;
			}

			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$display_name = isset( $_POST[ $this->display_name_field_name ] ) ? wp_unslash( $_POST[ $this->display_name_field_name ] ) : '';
			$description  = isset( $_POST[ $this->description_field_name ] ) ? wp_unslash( $_POST[ $this->description_field_name ] ) : '';

			// Filter (and sanitize) fields.
			$description  = apply_filters( 'pre_user_description', $description );
			$display_name = apply_filters( 'pre_user_display_name', $display_name );
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			update_user_meta( $user_id, $this->display_name_field_name, $display_name );
			update_user_meta( $user_id, $this->description_field_name, $description );
		}

		/**
		 * get the new author display name
		 * @return string $display_name individual author display_name, if provided
		 * @return string $base_display_name normal WordPress author display_name if indiv. display_name is empty
		 * @since 1.3 ??
		 * @updated 1.3 ??
		 */
		public function the_author($base_display_name) {
			global $authordata;
			if ( is_object( $authordata ) && isset( $authordata->ID ) ) {
				$display_name = get_the_author_meta( $this->display_name_field_name, $authordata->ID );
			}
			else {
				$display_name = '';
			}
			return '' == $display_name ? $base_display_name : $display_name;
		}
		
		/**
		 * get the new author display name
		 * @return string $display_name individual author display_name, if provided
		 * @return string $val normal WordPress author display_name if indiv. display_name is empty
		 * @since 1.3 ??
		 * @updated 1.3 ??
		 */
		public function get_the_author_display_name($val = '', $user_id = 0) {
			if ( ! $user_id ) { return; }
			$display_name = get_the_author_meta( $this->display_name_field_name, $user_id );
			return '' == $display_name ? $val : $display_name;
		}

		/**
		 * get the new author description
		 * @return string $description individual author description, if provided
		 * @return string $val normal WordPress author bio if indiv. description is empty
		 * @since 1.0
		 * @updated 1.2.1
		 */
		public function get_the_author_description($val = '', $user_id = 0) {
			if ( ! $user_id ) { return; }
			$description = get_the_author_meta( $this->description_field_name, $user_id );
			return '' == $description ? $val : $description;
		}


	}

	new Ima_Class();
}
