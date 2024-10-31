<?php
/*
Plugin Name: Safelayout Brilliant Buttons
Plugin URI: https://safelayout.com
Description: Beautiful and high quality buttons.
Requires at least: 6.2
Requires PHP: 7.0
Version: 1.0.2
Author: Safelayout
Text Domain: safelayout-brilliant-buttons
Domain Path: /languages
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( 'Safelayout_brilliant_buttons' ) && ! class_exists( 'Safelayout_brilliant_buttons_pro' ) ) {
	
	// Define the constant used in this plugin
	define( 'SAFELAYOUT_BUTTONS_VERSION', '1.0.2');
	define( 'SAFELAYOUT_BUTTONS_URL', plugin_dir_url( __FILE__ ) );
	define( 'SAFELAYOUT_BUTTONS_NAME', plugin_basename( __FILE__ ) );

	class Safelayout_brilliant_buttons {
		protected $options_page_hook = null;

		public function __construct() {
			load_plugin_textdomain( 'safelayout-brilliant-buttons', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			add_action( 'activated_plugin', array( $this, 'activated_plugin' ) );
			add_filter( 'plugin_action_links_' . SAFELAYOUT_BUTTONS_NAME, array( $this, 'plugin_action_links' ) );
			add_filter( 'wp_kses_allowed_html', array( $this, 'allowed_html' ), 10, 2 );
			add_filter( 'safecss_filter_attr_allow_css', array( $this, 'attr_allow_css' ), 10, 2 );
			add_filter( 'safe_style_css', array( $this, 'allowed_css' ), 10, 1 );

			add_action( 'init', array( $this, 'register_block' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'load_packs' ), 1 );
			add_action( 'enqueue_block_editor_assets', array( $this, 'set_translations' ), 999999 );
			add_filter( 'block_categories_all', array( $this, 'safelayout_blocks_categories_add' ), 10, 2 );

			if ( is_admin() ){
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
				add_action( 'admin_init', array( $this, 'add_settings_fields' ) );
				add_action( 'admin_init', array( $this, 'add_rate_reminder' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_for_feedback' ) );
				add_action( 'admin_footer-plugins.php', array( $this, 'add_code_for_feedback' ) );
				add_action( 'wp_ajax_slbb_buttons_feedback', array( $this, 'buttons_feedback_ajax_handler' ) );
				add_filter( 'http_request_host_is_external', array( $this, 'allow_buttons_feedback_host' ), 10, 3 );
				add_filter( 'register_block_type_args', array( $this, 'add_button_block_to_core_blocks' ), 10, 2 );
			}
		}

		// activated plugin
		public function activated_plugin( $plugin ) {
			if( $plugin == plugin_basename( __FILE__ ) ) {
				$rate = $this->get_rate_data();
			}
		}

		// Add settings link on plugin page
		public function plugin_action_links( $links ) {
			$settings_link = array(
				'<a href="' . admin_url( 'options-general.php?page=safelayout-brilliant-buttons' ) . '">' . esc_html__( 'Settings', 'safelayout-brilliant-buttons' ) . '</a>',
			);
			$links = array_merge( $links, $settings_link );
			return $links;
		}

		// Register button block
		public function register_block() {
			if ( ! function_exists( 'register_block_type' ) ) {
				// Gutenberg is not active.
				return;
			}
			register_block_type( __DIR__ . '/build/button' );
		}

		// Return rate reminder data
		public function get_rate_data() {
			$rate = get_option( 'safelayout_buttons_options_rate' );
			if ( ! $rate ) {
				$rate = array(
					'time'	=> time(),
					'later'	=> time(),
				);
				update_option( 'safelayout_buttons_options_rate', $rate );
			}
			return $rate;
		}

		// Add rate reminder
		public function add_rate_reminder() {
			if ( is_super_admin() ) {
				$rate = $this->get_rate_data();
				if ( $rate['later'] != 0 && $rate['later'] < strtotime( '-3 day' ) ) {
					add_action( 'admin_notices', array( $this, 'show_rate_reminder' ), 0 );
					add_action( 'wp_ajax_slbb_buttons_rate_reminder', array( $this, 'buttons_rate_reminder_ajax_handler' ) );
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_for_rate_reminder' ) );
				}
			}
		}

		// ajax handlers for rate reminder
		public function buttons_rate_reminder_ajax_handler() {
			check_ajax_referer( 'slbb_buttons_ajax' );
			$type = sanitize_text_field( $_POST['type'] );
			$rate = $this->get_rate_data();
			if ( $type === 'sl-bb-rate-later' ) {
				$rate['later'] = time();
			} else {
				$rate['later'] = 0;
			}
			update_option( 'safelayout_buttons_options_rate', $rate );

			wp_die();
		}

		// Show rate reminder
		public function show_rate_reminder() {
			global $current_user;
			?>
			<div id="sl-bb-rate-reminder" class="notice notice-success is-dismissible">
				<img alt="safelayout brilliant buttons" src="https://ps.w.org/safelayout-brilliant-buttons/assets/icon-128x128.gif">
				<div class="sl-bb-msg-container">
					<p>
						<?php
						printf(
							esc_html__(
								'Howdy, %1$s! Thank you for using %2$s! Could you please do us a BIG favor and %3$s? Just to help us spread the word and boost our motivation.%4$s',
								'safelayout-brilliant-buttons'
							),
							'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
							'<strong>' . esc_html__( 'Safelayout Brilliant Buttons', 'safelayout-brilliant-buttons' ) . '</strong>',
							'<strong>' . esc_html__( 'give it a 5-star rating on WordPress.org', 'safelayout-brilliant-buttons' ) . '</strong>',
							'<br>' . esc_html__( 'We really appreciate your support!', 'safelayout-brilliant-buttons' ) . '<strong> -Safelayout-</strong>'
						);
						?>
					</p>
					<div class="sl-bb-rate-reminder-footer">
						<a id="sl-bb-rate-ok" class="button" href="https://wordpress.org/support/plugin/safelayout-brilliant-buttons/reviews/?filter=5" target="_blank">
							<?php esc_html_e( 'Yes, I will help ★★★★★', 'safelayout-brilliant-buttons' ); ?>
						</a>
						<a id="sl-bb-rate-later" class="button"><span class="dashicons dashicons-calendar"></span><?php esc_html_e( 'Remind me later', 'safelayout-brilliant-buttons' ); ?></a>
						<a id="sl-bb-rate-already" class="button"><span class="dashicons dashicons-smiley"></span><?php esc_html_e( 'I already did', 'safelayout-brilliant-buttons' ); ?></a>
					</div>
				</div>
			</div>
			<?php
		}

		// allow feedback host
		public function allow_buttons_feedback_host( $allow, $host, $url ) {
			return ( false !== strpos( $host, 'safelayout' ) ) ? true : $allow;
		}

		// Add css and js file for rate reminder
		public function enqueue_scripts_for_rate_reminder( $hook ) {
			$this->enqueue_scripts_for_feedback_and_rate();
		}

		// Add css and js file for feedback
		public function enqueue_scripts_for_feedback( $hook ) {
			if ( $hook != 'plugins.php' ) {
				return;
			}
			$this->enqueue_scripts_for_feedback_and_rate();
		}

		// Add css and js file for feedback & rate reminder
		public function enqueue_scripts_for_feedback_and_rate() {
			wp_enqueue_script(
				'safelayout-brilliant-buttons-script-admin-feedback',
				SAFELAYOUT_BUTTONS_URL . 'assets/js/safelayout-brilliant-buttons-admin-feedback.min.js',
				array( 'jquery' ),
				SAFELAYOUT_BUTTONS_VERSION,
				true
			);
			$temp_obj = array(
				'ajax_url'	=> admin_url( 'admin-ajax.php' ),
				'nonce'		=> wp_create_nonce( 'slbb_buttons_ajax' ),
			);
			wp_localize_script( 'safelayout-brilliant-buttons-script-admin-feedback', 'slbbButtonsAjax', $temp_obj );
			wp_enqueue_style(
				'safelayout-brilliant-buttons-style-admin-feedback',
				SAFELAYOUT_BUTTONS_URL . 'assets/css/safelayout-brilliant-buttons-admin-feedback.min.css',
				array(),
				SAFELAYOUT_BUTTONS_VERSION
			);
		}

		// ajax handlers for feedback
		public function buttons_feedback_ajax_handler() {
			check_ajax_referer( 'slbb_buttons_ajax' );
			$type = sanitize_text_field( $_POST['type'] );
			$text = sanitize_text_field( $_POST['text'] );
			$apiUrl = 'https://safelayout.com/feedback/feedback.php';
			$rate = $this->get_rate_data();

			$data = array (
				'php'		=> phpversion(),
				'wordpress'	=> get_bloginfo( 'version' ),
				'version'	=> SAFELAYOUT_BUTTONS_VERSION,
				'time'		=> $rate['time'],
				'type'		=> $type,
				'text'		=> $text,
				'plugin'	=> 'buttons',
			);
			$arg = array (
				'body'			=> $data,
				'timeout'		=> 30,
				'sslverify'		=> false,
				'httpversion'	=> 1.1,
			);

			$ret = wp_safe_remote_post( $apiUrl, $arg );
			if ( is_wp_error( $ret ) ) {
				$apiUrl = 'http://' . substr( $apiUrl, 8 );
				$ret = wp_remote_post( $apiUrl, $arg );
			}
			var_dump( $ret );

			wp_die();
		}

		// Add html code for feedback
		public function add_code_for_feedback( $hook ) {
			?>
			<div id="sl-bb-feedback-modal">
				<div class="sl-bb-feedback-window">
					<div class="sl-bb-feedback-header"><?php esc_html_e( 'Quick Feedback', 'safelayout-brilliant-buttons' ); ?></div>
					<div class="sl-bb-feedback-body">
						<div class="sl-bb-feedback-title">
							<?php esc_html_e( 'If you have a moment, please share why you are deactivating', 'safelayout-brilliant-buttons' ); ?>
							<span class="dashicons dashicons-smiley"></span>
						</div>
						<div class="sl-bb-feedback-item">
							<input type="radio" name="sl-bb-feedback-radio" value="temporary deactivation" id="sl-bb-feedback-item1">
							<label for="sl-bb-feedback-item1"><?php esc_html_e( "It's a temporary deactivation", 'safelayout-brilliant-buttons' ); ?></label>
						</div>
						<div class="sl-bb-feedback-item">
							<input type="radio" name="sl-bb-feedback-radio" value="site broken" id="sl-bb-feedback-item2">
							<label for="sl-bb-feedback-item2"><?php esc_html_e( 'The plugin broke my site', 'safelayout-brilliant-buttons' ); ?></label><br>
							<textarea rows="2" id="sl-bb-feedback-item2-text" placeholder="<?php esc_html_e( 'Please explain the problem.', 'safelayout-brilliant-buttons' ); ?>"></textarea>
						</div>
						<div class="sl-bb-feedback-item">
							<input type="radio" name="sl-bb-feedback-radio" value="better plugin" id="sl-bb-feedback-item5">
							<label for="sl-bb-feedback-item5"><?php esc_html_e( 'I found a better plugin', 'safelayout-brilliant-buttons' ); ?></label><br>
							<input type="text" id="sl-bb-feedback-item5-text" placeholder="<?php esc_html_e( "What's the plugin name?", 'safelayout-brilliant-buttons' ); ?>">
						</div>
						<div class="sl-bb-feedback-item">
							<input type="radio" name="sl-bb-feedback-radio" value="Other" id="sl-bb-feedback-item6">
							<label for="sl-bb-feedback-item6"><?php esc_html_e( 'Other', 'safelayout-brilliant-buttons' ); ?></label><br>
							<textarea rows="2" id="sl-bb-feedback-item6-text" placeholder="<?php esc_html_e( 'Please share the reason.', 'safelayout-brilliant-buttons' ); ?>"></textarea>
						</div>
						<p>
							<?php esc_html_e( 'No email address, domain name or IP addresses are transmitted after you submit the survey.', 'safelayout-brilliant-buttons' ); ?><br>
							<?php esc_html_e( 'You can see the source code here: ', 'safelayout-brilliant-buttons' ); ?> /wp-content/plugins/safelayout-brilliant-buttons/safelayout-brilliant-buttons.php ( line: 187 ).
						</p>
					</div>
					<div class="sl-bb-feedback-footer">
						<a id="sl-bb-feedback-submit" class="button"><?php esc_html_e( 'Submit & Deactivate', 'safelayout-brilliant-buttons' ); ?></a>
						<a id="sl-bb-feedback-skip" class="button"><?php esc_html_e( 'Skip & Deactivate', 'safelayout-brilliant-buttons' ); ?></a> 
					</div>
					<div id="sl-bb-feedback-loader"><div id="sl-bb-dots-rate" class="sl-bb-spin-rate"><div><span></span><span></span><span></span><span></span></div>
					<div id="sl-bb-feedback-loader-msg"><?php esc_html_e( 'Wait ...', 'safelayout-brilliant-buttons' ); ?></div></div></div>
					<div id="sl-bb-feedback-loader-msg-tr"><?php esc_html_e( 'Redirecting ...', 'safelayout-brilliant-buttons' ); ?></div>
				</div>
			</div>
			<?php
		}

		// Load icon packs
		public function load_packs() {
			$packs = $this->get_packs();
			$first = '';
			foreach ( $packs['icons'] as $icon ) {
				if ( $icon['active'] === 'yes' ) {
					$path = SAFELAYOUT_BUTTONS_URL . 'packs/' . $icon['file_name'] . '.js';
					if ( $first === '' ) {
						$first = $icon['file_name'];
					}
					wp_enqueue_script(
						'safelayout-pack-' . $icon['file_name'] . '-script',
						$path,
						array(),
						SAFELAYOUT_BUTTONS_VERSION,
						false
					);
				}
			}

			$temp = "if (!(typeof SLEIiconArray !== 'undefined' && SLEIiconArray)) {SLEIiconArray = []}";
			wp_add_inline_script(
				'safelayout-pack-' . $first . '-script',
				$temp,
				'before'
			);
		}

		// Set translations
		public function set_translations() {
			wp_set_script_translations(
				'safelayout-safelayout-button-editor-script',
				'safelayout-brilliant-buttons',
				plugin_dir_path( __FILE__ ) . 'languages'
			);
		}

		// Add block category
		public function safelayout_blocks_categories_add( $block_categories, $editor_context ) {
			$key = false;
			foreach ( $block_categories as $block_cat ) {
				if ( $block_cat['slug'] === 'blocks-safelayout-category' ) {
					$key = true;
					break;
				}
			}
			if ( ! $key ) {
				array_unshift(
					$block_categories,
					array(
						'slug'  => 'blocks-safelayout-category',
						'title' => __( 'Blocks By Safelayout', 'safelayout-brilliant-buttons' ),
						'icon'  => null,
					)
				);
			}
			return $block_categories;
		}

		// Add an admin menu for plugin
		public function admin_menu() {
			$this->options_page_hook = add_options_page(
				esc_html__( 'Safelayout Brilliant Buttons Options', 'safelayout-brilliant-buttons' ),
				esc_html__( 'Safelayout Buttons', 'safelayout-brilliant-buttons' ),
				'manage_options',
				'safelayout-brilliant-buttons',
				array( $this, 'admin_menu_page' )
			);
		}

		// Admin menu page
		public function admin_menu_page() {
			$packs = $this->get_packs();

			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Safelayout Brilliant Buttons Options', 'safelayout-brilliant-buttons' ); ?></h2>
				<?php settings_errors( 'safelayout-brilliant-buttons' ); ?>
				<div id="sl-bb-packs-settings">
					<form method="post" action="options.php">
						<?php settings_fields( 'safelayout_buttons_packs_group' ); ?>
						<input type="hidden" name="safelayout_icons_packs[validate-key]" value="true">
						<div>
							<table class="sl-bb-packs-table">
								<caption><?php esc_html_e( 'Safelayout Brilliant Buttons Installed Icon Packs', 'safelayout-brilliant-buttons' ); ?></caption>
								<thead>
									<tr>
										<th><?php esc_html_e( 'No.', 'safelayout-brilliant-buttons' ); ?></th>
										<th><?php esc_html_e( 'Pack Name', 'safelayout-brilliant-buttons' ); ?></th>
										<th><?php esc_html_e( 'Pack Status', 'safelayout-brilliant-buttons' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php 
										foreach ( $packs['icons'] as $index => $pack ) {
											echo '<tr><td>' . esc_html( $index + 1 ) . '</td><td>' . esc_html( $pack['name'] ) .
												 '</td><td><input type="checkbox" name="safelayout_icons_packs[safelayout-' .
												 esc_html( $pack['file_name'] ) . ']" value="yes" ' .
												 checked( esc_attr( $pack['active'] ), 'yes', false ) . ' id="safelayout-' .
												 esc_html( $pack['file_name'] ) . '"><label for="safelayout-' . esc_html( $pack['file_name'] ) .
												 '">' . esc_html__( 'Active', 'safelayout-brilliant-buttons' ) . '</label></td></tr>';
										}
									?>
								</tbody>
							</table>
						</div>
						<div style="height: 50px;">
							<?php submit_button( esc_html__( 'Save Changes', 'safelayout-brilliant-buttons' ), 'primary', 'submit', false ); ?>
						</div>
					</form>
				</div>
			</div>
			<?php
		}

		// Add settings fields
		public function add_settings_fields() {
			register_setting(
				'safelayout_buttons_packs_group',
				'safelayout_icons_packs',
				array( $this, 'option_sanitize' )
			);
		}

		// Add css file for settings page
		public function enqueue_scripts( $hook ) {
			if ( ! $hook || $hook != $this->options_page_hook ) {
				return;
			}
			wp_enqueue_style(
				'safelayout-brilliant-buttons-style-admin',
				SAFELAYOUT_BUTTONS_URL . 'assets/css/safelayout-brilliant-buttons-admin.min.css',
				array(),
				SAFELAYOUT_BUTTONS_VERSION
			);
		}

		// Sanitize options
		public function option_sanitize( $input ) {
			if ( ! isset( $input["validate-key"] ) ) {
				return $input;
			}
			$packs = $this->get_packs();
			$packs['version'] = SAFELAYOUT_BUTTONS_VERSION;
			$key = false;
			
			foreach ( $packs['icons'] as $index => $pack ) {
				$id = 'safelayout-' . esc_html( $pack['file_name'] );
				if ( isset( $input[ $id ] ) ) {
					$packs['icons'][$index]['active'] = 'yes';
					$key = true;
				} else {
					$packs['icons'][$index]['active'] = 'no';
				}
			}
			if ( $key ) {
				return $packs;
			} else {
				return $this->get_packs();
			}
		}

		// Return default packs
		public function get_default_packs() {
			$default = array(
				'version'	=> SAFELAYOUT_BUTTONS_VERSION,
				'icons'		=> [
					array( 'name' => 'Themeisle',					'active' => 'yes', 'file_name' => 'themeisle-icons' ),
					array( 'name' => 'Wordpress Dashicons',			'active' => 'yes', 'file_name' => 'wordpress-dashicons-icons' ),
					array( 'name' => 'Wordpress',					'active' => 'yes', 'file_name' => 'wordpress-icons' ),
				],
			);
			return $default;
		}

		// Return packs
		public function get_packs() {
			$packs = get_option( 'safelayout_icons_packs' );
			if ( ! $packs ) {
				$packs = $this->get_default_packs();
				update_option( 'safelayout_icons_packs', $packs );
			}
			return $packs;
		}

		// Add allowed html tags
		public function allowed_html( $tags, $context ) {
			if ( 'post' === $context ) {
				$tags['svg'] = array(
					'id' => true,
					'class' => true,
					'style' => true,
					'viewbox' => true,
					'filter' => true,
					'xmlns' => true,
					'preserveaspectratio' => true,
					'aria-hidden' => true,
					'data-*' => true,
					'role' => true,
					'height' => true,
					'width' => true,
				);

				$tags['defs'] = array(
					'id' => true,
					'key' => true,
				);

				$tags['lineargradient'] = array(
					'id' => true,
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				);

				$tags['radialgradient'] = array(
					'id' => true,
					'cx' => true,
					'cy' => true,
					'r'	=> true,
					'fx' => true,
					'fy' => true,
				);

				$tags['stop'] = array(
					'stop-color' => true,
					'offset' => true,
					'stop-opacity' => true,
					'key' => true,
				);

				$tags['ellipse'] = array(
					'id' => true,
					'class' => true,
					'style' => true,
					'filter' => true,
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
					'fill' => true,
				);

				$tags['g'] = array(
					'id' => true,
					'class' => true,
					'style' => true,
					'filter' => true,
					'viewbox' => true,
					'fill' => true,
					'stroke' => true,
				);

				$tags['rect'] = array(
					'id' => true,
					'class' => true,
					'style' => true,
					'filter' => true,
					'x' => true,
					'y' => true,
					'width' => true,
					'height' => true,
					'rx' => true,
					'fill' => true,
					'stroke' => true,
					'stroke-width' => true,
					'key' => true,
				);

				$tags['path'] = array(
					'id' => true,
					'class' => true,
					'style' => true,
					'filter' => true,
					'd' => true,
					'fill' => true,
					'stroke' => true,
					'stroke-width' => true,
					'vector-effect' => true,
					'key' => true,
				);

				$tags['symbol'] = array(
					'id' => true,
					'class' => true,
					'style' => true,
					'filter' => true,
					'x' => true,
					'y' => true,
					'width' => true,
					'height' => true,
					'viewbox' => true,
				);

				$tags['use'] = array(
					'id' => true,
					'class' => true,
					'style' => true,
					'filter' => true,
					'viewbox' => true,
					'xlink:href' => true,
				);
			}

			return $tags;
		}

		// Add allowed css style
		public function allowed_css( $styles ) {
			$styles[] = 'transform';
			return $styles;
		}

		//add icon block to core navigation
		public function add_button_block_to_core_blocks( $args, $block_type ) {
			if ( 'core/navigation' === $block_type ) {
				$args['allowed_blocks'] ??= [];
				$args['allowed_blocks'][] = 'safelayout/safelayout-button';
			}
			return $args;
		}

		// Add allowed css for filter and transform
		public function attr_allow_css( $allow_css, $css_test_string ) {
			if ( strpos( $css_test_string, 'filter' ) === false &&
				strpos( $css_test_string, 'transform' ) === false ) {
				return $allow_css;
			} else {
				return true;
			}
		}
	}
	new Safelayout_brilliant_buttons();
}