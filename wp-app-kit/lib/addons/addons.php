<?php
require_once(dirname( __FILE__ ) . '/addon.php');

class WpakAddons {

	const meta_id = '_wpak_addons';
	
	protected static $addons = null;

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'rewrite_rules' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 5 );
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_main_meta_box' ), 20 );
			add_action( 'save_post', array( __CLASS__, 'save_post' ) );
		}
	}

	public static function get_addons( $force_reload = false ) {
		self::load_addons( $force_reload );
		return self::$addons;
	}

	public static function rewrite_rules() {
		add_rewrite_tag( '%wpak_addon_file%', '([^&]+)' );
		$wp_content = str_replace( ABSPATH, '', WP_CONTENT_DIR );
		$url_to_addons_files = plugins_url( 'app/addons', dirname( dirname( __FILE__ ) ) );
		$wp_content_pos = strpos( $url_to_addons_files, $wp_content );
		if ( $wp_content_pos !== false ) {
			$addons_file_prefix = substr( $url_to_addons_files, $wp_content_pos ); //Something like "wp-content/plugins/wp-app-kit/app"
			add_rewrite_rule( '^' . $addons_file_prefix . '/(.*[\.js|\.css|\.html])$', 'index.php?wpak_addon_file=$matches[1]', 'top' );
		}
	}

	public static function template_redirect() {
		global $wp_query;

		if ( isset( $wp_query->query_vars['wpak_addon_file'] ) && !empty( $wp_query->query_vars['wpak_addon_file'] ) ) {

			if ( !empty( $_GET['wpak_app_id'] ) ) {

				$app_id = esc_attr( $_GET['wpak_app_id'] ); //can be ID or slug

				$app = WpakApps::get_app( $app_id );

				if ( !empty( $app ) ) {
					$app_id = $app->ID;

					$default_capability = current_user_can( 'wpak_edit_apps' ) ? 'wpak_edit_apps' : 'manage_options';

					$capability = apply_filters( 'wpak_private_simulation_capability', $default_capability, $app_id );

					if ( WpakApps::get_app_simulation_is_secured( $app_id ) && !current_user_can( $capability ) ) {
						wp_nonce_ays( 'wpak-addon-file' );
					}

					$file = $wp_query->query_vars['wpak_addon_file'];

					if ( preg_match( '/([^\/]+?)\/(.+[\.js|\.css|\.html])$/', $file, $matches ) ) {
						$addon_slug = $matches[1];
						$asset_file = $matches[2];
						$app_addons = self::get_app_addons( $app_id );
						if ( array_key_exists( $addon_slug, $app_addons ) ) {
							$addon = $app_addons[$addon_slug];
							if ( $asset_full_path = $addon->get_asset_file( $asset_file ) ) {
								$file_type = pathinfo( $asset_full_path, PATHINFO_EXTENSION );
								if ( $file_type == 'js' ) {
									header( "Content-type: text/javascript;  charset=utf-8" );
								} elseif ( $file_type == 'css' ) {
									header( "Content-type: text/css;  charset=utf-8" );
								} elseif ( $file_type == 'html' ) {
									header( "Content-type: text/html;  charset=utf-8" );
								}
								echo file_get_contents( $asset_full_path );
								exit();
							} else {
								header("HTTP/1.0 404 Not Found");
								_e( 'Addon file not found', WpAppKit::i18n_domain );
								exit();
							}
						} else {
							header("HTTP/1.0 404 Not Found");
							_e( 'Addon not found for this app', WpAppKit::i18n_domain );
							exit();
						}
					} else {
						header("HTTP/1.0 404 Not Found");
						_e( 'Wrong addon file', WpAppKit::i18n_domain );
						exit();
					}
				} else {
					header("HTTP/1.0 404 Not Found");
					_e( 'App not found', WpAppKit::i18n_domain ) . ' : [' . $app_id . ']';
					exit();
				}
			} else {
				header("HTTP/1.0 404 Not Found");
				_e( 'App id not found in _GET parmeters', WpAppKit::i18n_domain );
				exit();
			}
		}
	}

	public static function add_main_meta_box() {

		$addons = self::get_addons();

		if ( !empty( $addons ) ) {
			add_meta_box(
				'wpak_app_addons', __( 'Addons', WpAppKit::i18n_domain ), array( __CLASS__, 'inner_addon_box' ), 'wpak_apps', 'normal', 'default'
			);
		}
	}

	public static function inner_addon_box( $post ) {
		$app_addons = self::get_app_addons( $post->ID );
		?>
		<div class="wpak_addons">
			<span><?php _e( 'Addons activated for this App', WpAppKit::i18n_domain ) ?> :</span><br/>
			<?php foreach ( self::get_addons() as $addon ): ?>
				<?php $checked = array_key_exists( $addon->slug, $app_addons ) ? 'checked' : '' ?>
					<input type="checkbox" name="wpak-addons[]" id="<?php echo $addon->slug ?>" value="<?php echo $addon->slug ?>" <?php echo $checked ?> />
					<label for="<?php echo $addon->slug ?>"><?php echo $addon->name ?></label>
			<?php endforeach ?>
			<?php wp_nonce_field( 'wpak-addons-' . $post->ID, 'wpak-nonce-addons' ) ?>
		</div>
		<style>
			.wpak_addons{}
		</style>
		<?php
	}

	public static function get_app_addons( $app_id_or_slug ) {
		$app_addons = array();

		$app_id = WpakApps::get_app_id( $app_id_or_slug );
		if ( !empty( $app_id ) ) {

			$app_addons_raw = get_post_meta( $app_id, self::meta_id, true );
			
			if ( !empty( $app_addons_raw ) ) {
				//Check if the app addons are still installed :
				$all_addons = self::get_addons();
				foreach ( $app_addons_raw as $addon_slug ) {
					if ( array_key_exists( $addon_slug, $all_addons ) ) {
						$addon = $all_addons[$addon_slug];
						$addon->set_app_static_data( $app_id );
						$addon->set_app_dynamic_data( $app_id );
						$app_addons[$addon_slug] = $addon;
					}
				}
			}
		}

		return $app_addons;
	}

	public static function addon_activated_for_app( $addon_slug, $app_id_or_slug ) {
		$app_addons_raw = self::get_app_addons( $app_id_or_slug );
		return array_key_exists( $addon_slug, $app_addons_raw );
	}

	/**
	 * Retrieves app data to add to the config.js file.
	 * @param int|string $app_id_or_slug
	 * @return array Array of addons
	 */
	public static function get_app_addons_for_config( $app_id_or_slug ) {
		$app_addons = array();

		$app_addons_raw = self::get_app_addons( $app_id_or_slug );
		if ( !empty( $app_addons_raw ) ) {
			foreach ( $app_addons_raw as $app_addon_raw ) {
				$app_addons[] = $app_addon_raw->to_config_object();
			}
		}

		return $app_addons;
	}
	
	/**
	 * Retrieves app data to add to the synchronization web service.
	 * @param int|string $app_id_or_slug
	 * @return array Array of addons dynamic data
	 */
	public static function get_app_addons_dynamic_data( $app_id_or_slug ){
		$app_addons_dyn_data = array();
		
		$app_addons_raw = self::get_app_addons( $app_id_or_slug );
		if ( !empty( $app_addons_raw ) ) {
			foreach ( $app_addons_raw as $addon_slug => $app_addon_raw ) {
				$app_addons_dyn_data[$addon_slug] = $app_addon_raw->get_dynamic_data();
			}
		}
		
		return $app_addons_dyn_data;
	}

	protected static function load_addons( $force_reload = false ) {
		if ( self::$addons === null || $force_reload ) {
			$addons = array();

			$addons_raw = apply_filters( 'wpak_addons', array() );

			if ( !empty( $addons_raw ) && is_array( $addons_raw ) ) {
				foreach ( $addons_raw as $addon ) {
					if ( $addon instanceof WpakAddon ) {
						$addons[$addon->slug] = $addon;
					}
				}
			}
			self::$addons = $addons;
		}
	}

	public static function save_post( $post_id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( empty( $_POST['post_type'] ) || $_POST['post_type'] != 'wpak_apps' ) {
			return;
		}
		
		if ( !current_user_can( 'edit_post', $post_id ) && !current_user_can( 'wpak_edit_apps', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['wpak-addons'] ) && !check_admin_referer( 'wpak-addons-' . $post_id, 'wpak-nonce-addons' ) ) {
			return;
		}

		if ( isset( $_POST['wpak-addons'] ) && is_array( $_POST['wpak-addons'] ) ) {

			$app_addons = array();
			$all_addons = array_keys( self::get_addons() );
			foreach ( $_POST['wpak-addons'] as $addon_slug ) {
				if ( in_array( $addon_slug, $all_addons ) ) {
					$app_addons[] = $addon_slug;
				}
			}

			if ( !empty( $app_addons ) ) {
				update_post_meta( $post_id, self::meta_id, $app_addons );
			} else {
				delete_post_meta( $post_id, self::meta_id );
			}
			
		}else{
			//$_POST['wpak-addons'] is null if no addons checked.
			//At this point, wpak-nonce-addons is ok so we can be sure that
			//the form has been submitted : we can delete the meta :
			delete_post_meta( $post_id, self::meta_id );
		}
	}
	
}

WpakAddons::hooks();
	