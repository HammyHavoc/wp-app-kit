<?php

class WpakAddon {

	protected $name = '';
	protected $slug = '';
	protected $directory = '';
	protected $url = '';
	protected $js_files = array();
	protected $css_files = array();
	protected $html_files = array();
	protected $app_static_data_callback = null;
	protected $app_static_data = null;
	protected $app_dynamic_data_callback = null;
	protected $app_dynamic_data = null;

	public function __construct( $name, $slug = '' ) {
		$this->name = $name;
		$this->slug = sanitize_title_with_dashes( remove_accents( empty($slug) ? $name : $slug ) );
	}

	public function __get( $property ) {
		if ( in_array( $property, array( 'name', 'slug' ) ) ) {
			return $this->{$property};
		}
		return null;
	}

	public function set_location( $addon_file ) {
		$this->directory = untrailingslashit( dirname( $addon_file ) );
		$this->url = plugins_url( '', $addon_file ); // > An addon must be a plugin
	}

	public function add_js( $js_file, $type = 'module', $position = '' ) {
		
		$file_type = pathinfo( $js_file, PATHINFO_EXTENSION );
		if( $file_type !== 'js' ){
			return;
		}
		
		$full_js_file = '';
		
		if( $type == 'theme' && $position === '' ){
			$position = 'after';
		}

		if ( strpos( $js_file, $this->directory ) !== false ) {
			$full_js_file = $js_file;
			$js_file = ltrim( str_replace( $this->directory, '', $js_file ), '/\\' );
		} else {
			$js_file = ltrim( $js_file, '/\\' );
			$full_js_file = $this->directory . '/' . $js_file;
		}

		if ( file_exists( $full_js_file ) ) {
			if ( !in_array( $js_file, $this->js_files ) ) {
				$this->js_files[] = array( 
					'file' => $js_file, 
					'type' => $type, 
					'position' => $position 
				);
			}
		}
	}

	public function add_css( $css_file, $position = 'after' ) {

		$file_type = pathinfo( $css_file, PATHINFO_EXTENSION );
		if( $file_type !== 'css' ){
			return;
		}
		
		$full_css_file = '';

		if ( strpos( $css_file, $this->directory ) !== false ) {
			$full_css_file = $css_file;
			$css_file = ltrim( str_replace( $this->directory, '', $css_file ), '/\\' );
		} else {
			$css_file = ltrim( $css_file, '/\\' );
			$full_css_file = $this->directory . '/' . $css_file;
		}

		if ( file_exists( $full_css_file ) ) {
			if ( !in_array( $css_file, $this->css_files ) ) {
				$this->css_files[] = array( 
					'file' => $css_file, 
					'type' => 'theme',
					'position' => $position
				);
			}
		}
	}
	
	public function add_html( $html_file, $type = 'layout', $position = 'after', $data = array() ) {

		$file_type = pathinfo( $html_file, PATHINFO_EXTENSION );
		if( $file_type !== 'html' ){
			return;
		}
		
		$full_html_file = '';

		if ( strpos( $html_file, $this->directory ) !== false ) {
			$full_html_file = $html_file;
			$html_file = ltrim( str_replace( $this->directory, '', $html_file ), '/\\' );
		} else {
			$html_file = ltrim( $html_file, '/\\' );
			$full_html_file = $this->directory . '/' . $html_file;
		}

		if ( file_exists( $full_html_file ) ) {
			if ( !in_array( $html_file, $this->html_files ) ) {
				$this->html_files[] = array( 
					'file' => $html_file, 
					'type' => $type ,
					'position' => $position,
					'data' => $data
				);
			}
		}
	}
	
	/**
	 * Set the addon callback that will retrieve additionnal addon static data 
	 * (added to config.js) specific to a given app.
	 * @param type $callback Should be a function that takes $app_id as argument and returns an associative array
	 */
	public function add_app_static_data( $callback ){
		$this->app_static_data_callback = $callback;
	}
	
	public function set_app_static_data( $app_id ){
		if( $this->app_static_data_callback !== null && is_callable($this->app_static_data_callback) ){
			$app_data = call_user_func( $this->app_static_data_callback, $app_id );
			if( $app_data !== false && is_array($app_data) ){
				$this->app_static_data = $app_data;
			}
		}
	}
	
	/**
	 * Set the addon callback that will retrieve additionnal addon dynamic data 
	 * (added to the synchronization web service) specific to a given app.
	 * @param type $callback Should be a function that takes $app_id as argument and returns an associative array
	 */
	public function add_app_dynamic_data( $callback ){
		$this->app_dynamic_data_callback = $callback;
	}
	
	public function set_app_dynamic_data( $app_id ){
		if( $this->app_dynamic_data_callback !== null && is_callable($this->app_dynamic_data_callback) ){
			$app_data = call_user_func( $this->app_dynamic_data_callback, $app_id );
			if( $app_data !== false && is_array($app_data) ){
				$this->app_dynamic_data = $app_data;
			}
		}
	}

	public function get_asset_file( $file_relative_to_addon ) {

		$file_type = pathinfo( $file_relative_to_addon, PATHINFO_EXTENSION );
		if ( isset( $this->{$file_type . '_files'} ) ) {
			foreach ( $this->{$file_type . '_files'} as $file ) {
				if ( $file_relative_to_addon == $file['file'] ) {
					$found = true;
					break;
				}
			}
		}

		$file_full_path = $this->directory . '/' . $file_relative_to_addon;

		return $found && file_exists( $file_full_path ) ? $file_full_path : false;
	}

	public function check_exists() {
		return file_exists( $this->directory );
	}

	/**
	 * Export data for config.js file
	 */
	public function to_config_object() {
		return ( object ) array(
			'name' => $this->name,
			'slug' => $this->slug,
			'url' => $this->url,
			'js_files' => $this->js_files,
			'css_files' => $this->css_files,
			'html_files' => $this->html_files,
			'app_data' => $this->app_static_data
		);
	}
	
	public function get_all_files( $indexed_by_type = false ) {
		$all_files = array();

		$file_types = array( 'js', 'css', 'html' );

		foreach ( $file_types as $file_type ) {
			if ( isset( $this->{$file_type . '_files'} ) ) {
				foreach ( $this->{$file_type . '_files'} as $file ) {
					$file_full_path = $this->directory . '/' . $file['file'];
					if ( file_exists( $file_full_path ) ) {
						$file_paths = array( 'full' => $file_full_path, 'relative' => $file['file'] );
						if ( $indexed_by_type ) {
							$all_files[$file_type] = $file_paths;
						} else {
							$all_files[] = $file_paths;
						}
					}
				}
			}
		}

		return $all_files;
	}

	/**
	 * Retrieves dynamic data to be passed to the synchronization web service
	 */
	public function get_dynamic_data() {
		return ( object ) $this->app_dynamic_data;
	}
}
