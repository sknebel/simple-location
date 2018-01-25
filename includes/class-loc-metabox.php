<?php
// Adds Post Meta Box for Location
add_action( 'init', array( 'Loc_Metabox', 'init' ) );
add_action( 'admin_init', array( 'Loc_Metabox', 'admin_init' ) );

class Loc_Metabox {
	public static function admin_init() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Loc_Metabox', 'add_meta_boxes' ) );
	}

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( 'Loc_Metabox', 'enqueue' ) );
		add_action( 'save_post', array( 'Loc_Metabox', 'save_post_meta' ) );
		add_action( 'save_post', array( 'Loc_Metabox', 'last_seen' ), 20, 2 );
		add_action( 'edit_attachment', array( 'Loc_Metabox', 'save_post_meta' ) );
		add_action( 'edit_comment', array( 'Loc_Metabox', 'save_comment_meta' ) );
		add_action( 'show_user_profile', array( 'Loc_Metabox', 'user_profile' ), 12 );
		add_action( 'edit_user_profile', array( 'Loc_Metabox', 'user_profile' ), 12 );
		add_action( 'personal_options_update', array( 'Loc_Metabox', 'save_user_meta' ), 12 );
		add_action( 'edit_user_profile_update', array( 'Loc_Metabox', 'save_user_meta' ), 12 );
	}

	public static function screens() {
		$screens = array( 'post', 'comment', 'attachment' );
		return apply_filters( 'sloc_post_types', $screens );
	}

	public static function enqueue( $hook_suffix ) {
		$screens = self::screens();
		if ( in_array( get_current_screen()->id, $screens, true ) || 'profile.php' === $hook_suffix ) {
			wp_enqueue_script(
				'sloc_location',
				plugins_url( 'js/location.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				Simple_Location_Plugin::$version
			);
			wp_enqueue_style(
				'sloc_metabox',
				plugins_url( 'css/location-admin-meta-box.css', dirname( __FILE__ ) ),
				array(),
				Simple_Location_Plugin::$version
			);
		}
	}

	/* Create location meta boxes to be displayed on the post editor screen. */
	public static function add_meta_boxes() {
		add_meta_box(
			'locationbox-meta',      // Unique ID
			esc_html__( 'Location', 'simple-location' ),    // Title
			array( 'Loc_Metabox', 'metabox' ),   // Callback function
			self::screens(),         // Admin page (or post type)
			'normal',         // Context
			'default'         // Priority
		);
	}

	public static function geo_public( $public ) {
		?>
		<label for="geo_public"><?php _e( 'Show:', 'simple-location' ); ?></label><br />
		<select name="geo_public">
		<option value=0 <?php selected( $public, 0 ); ?>><?php _e( 'Hide', 'simple-location' ); ?></option>
		<option value=1 <?php selected( $public, 1 ); ?>><?php _e( 'Show Map and Description', 'simple-location' ); ?></option>
		<option value=2 <?php selected( $public, 2 ); ?>><?php _e( 'Description Only', 'simple-location' ); ?></option>
		</select><br /><br />
		<?php
	}

	public static function geo_public_user( $user ) {
		$public = get_the_author_meta( 'geo_public', $user->ID );
		if ( ! $public ) {
			$public = get_option( 'geo_public' );
		}
		$public = (int) $public;
?>
		<tr>
		<th><label for="geo_public"><?php _e( 'Show:', 'simple-location' ); ?></label></th>
		<td><select name="geo_public">
		<option value=0 <?php selected( $public, 0 ); ?>><?php _e( 'Hide', 'simple-location' ); ?></option>
		<option value=1 <?php selected( $public, 1 ); ?>><?php _e( 'Show Map and Description', 'simple-location' ); ?></option>
		<option value=2 <?php selected( $public, 2 ); ?>><?php _e( 'Description Only', 'simple-location' ); ?></option>
		</select></td>
		</tr>
		<?php
	}

	public static function temp_unit() {
		switch ( get_option( 'sloc_measurements' ) ) {
			case 'imperial':
				return 'F';
			default:
				return 'C';
		}
	}


	public static function metabox( $object, $box ) {
		$geodata = WP_Geo_Data::get_geodata( $object );
		load_template( plugin_dir_path( __DIR__ ) . 'templates/loc-metabox.php' );
	}

	public static function user_profile( $user ) {
		echo '<h3>' . esc_html__( 'Last Reported Location', 'simple-location' ) . '</h3>';
		echo '<p>' . esc_html__( 'This allows you to set the last reported location for this author. See Simple Location settings for options.', 'simple-location' ) . '</p>';
		echo '<a class="hide-if-no-js lookup-address-button">';
				echo '<span class="dashicons dashicons-location" aria-label="' . __( 'Location Lookup', 'simple-location' ) . '" title="' . __( 'Location Lookup', 'simple-location' ) . '"></span></a>';
		echo '<table class="form-table">';
		self::profile_text_field( $user, 'latitude', __( 'Latitude', 'simple-location' ), 'Description' );
		self::profile_text_field( $user, 'longitude', __( 'Longitude', 'simple-location' ), 'Description' );
		self::profile_text_field( $user, 'address', __( 'Address', 'simple-location' ), 'Description' );
		self::geo_public_user( $user );
		echo '</table>';
	}




	public static function profile_text_field( $user, $key, $title, $description ) {
	?>
	<tr>
		<th><label for="<?php echo esc_html( $key ); ?>"><?php echo esc_html( $title ); ?></label></th>
		<td>
			<input type="text" name="<?php echo esc_html( $key ); ?>" id="<?php echo esc_html( $key ); ?>" value="<?php echo esc_attr( get_the_author_meta( 'geo_' . $key, $user->ID ) ); ?>" class="regular-text" /><br />
			<span class="description"><?php echo esc_html( $description ); ?></span>
		</td>
	</tr>
	<?php
	}


	public static function last_seen( $post_id, $post ) {
		if ( 0 === (int) get_option( 'sloc_last_report' ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		if ( $post->post_date !== $post->post_modified ) {
			return;
		}
		$geodata = WP_Geo_Data::get_geodata( $post );
		$author  = new WP_User( $post->post_author );
		WP_Geo_Data::set_geodata( $author, $geodata );
	}

	/* Save the meta box's post metadata. */
	public static function save_post_meta( $post_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */
		if ( ! isset( $_POST['location_metabox_nonce'] ) ) {
			return;
		}
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['location_metabox_nonce'], 'location_metabox' ) ) {
			return;
		}
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		if ( has_term( '', 'venue' ) ) {
			return;
		}
		/* OK, its safe for us to save the data now. */
		if ( ! empty( $_POST['latitude'] ) ) {
			update_post_meta( $post_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_post_meta( $post_id, 'geo_latitude' );
		}
		if ( ! empty( $_POST['longitude'] ) ) {
			update_post_meta( $post_id, 'geo_longitude', $_POST['longitude'] );
		} else {
			delete_post_meta( $post_id, 'geo_longitude' );
		}
		if ( ! empty( $_POST['address'] ) ) {
			update_post_meta( $post_id, 'geo_address', sanitize_text_field( $_POST['address'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_address' );
		}

		if ( ! empty( $_POST['map_zoom'] ) ) {
			update_post_meta( $post_id, 'geo_zoom', sanitize_text_field( $_POST['map_zoom'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_zoom' );
		}

		if ( ! empty( $_POST['altitude'] ) ) {
			update_post_meta( $post_id, 'geo_altitude', sanitize_text_field( $_POST['altitude'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_altitude' );
		}

		if ( ! empty( $_POST['speed'] ) && 'NaN' !== $_POST['speed'] ) {
			update_post_meta( $post_id, 'geo_speed', sanitize_text_field( $_POST['speed'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_speed' );
		}

		if ( ! empty( $_POST['heading'] ) && 'NaN' !== $_POST['heading'] ) {
			update_post_meta( $post_id, 'geo_heading', sanitize_text_field( $_POST['heading'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_heading' );
		}

		$weather = array();

		if ( ! empty( $_POST['temperature'] ) ) {
			$weather['temperature'] = sanitize_text_field( $_POST['temperature'] );
		}

		if ( ! empty( $_POST['units'] ) ) {
			$weather['units'] = sanitize_text_field( $_POST['units'] );
		}

		if ( ! empty( $_POST['humidity'] ) ) {
			$weather['humidity'] = sanitize_text_field( $_POST['humidity'] );
		}
		if ( ! empty( $_POST['pressure'] ) ) {
			$weather['pressure'] = sanitize_text_field( $_POST['pressure'] );
		}
		if ( ! empty( $_POST['weather_summary'] ) ) {
			$weather['summary'] = sanitize_text_field( $_POST['weather_summary'] );
		}
		if ( ! empty( $_POST['weather_icon'] ) ) {
			$weather['icon'] = sanitize_text_field( $_POST['weather_icon'] );
		}
		if ( ! empty( $_POST['visibility'] ) ) {
			$weather['visibility'] = sanitize_text_field( $_POST['visibility'] );
		}

		$wind = array();
		if ( ! empty( $_POST['wind_speed'] ) ) {
			$wind['speed'] = sanitize_text_field( $_POST['wind_speed'] );
		}
		if ( ! empty( $_POST['wind_degree'] ) ) {
			$wind['degree'] = sanitize_text_field( $_POST['wind_degree'] );
		}
		if ( ! empty( $wind ) ) {
			$weather['wind'] = $wind;
		}

		if ( ! empty( $weather ) ) {
			update_post_meta( $post_id, 'geo_weather', $weather );
		} else {
			delete_post_meta( $post_id, 'geo_weather' );
		}

		if ( ! empty( $_POST['address'] ) ) {
			if ( isset( $_POST['geo_public'] ) ) {
				update_post_meta( $post_id, 'geo_public', $_POST['geo_public'] );
			}
		}
	}

	/* Save the meta box's comment metadata. */
	public static function save_comment_meta( $comment_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */
		if ( ! isset( $_POST['location_metabox_nonce'] ) ) {
			return;
		}
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['location_metabox_nonce'], 'location_metabox' ) ) {
			return;
		}
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Check the user's permissions.
		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			return;
		}
		/* OK, its safe for us to save the data now. */
		if ( ! empty( $_POST['latitude'] ) ) {
			update_comment_meta( $comment_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_comment_meta( $comment_id, 'geo_latitude' );
		}
		if ( ! empty( $_POST['longitude'] ) ) {
			update_comment_meta( $comment_id, 'geo_longitude', $_POST['longitude'] );
		} else {
			delete_comment_meta( $comment_id, 'geo_longitude' );
		}
		if ( ! empty( $_POST['address'] ) ) {
			update_comment_meta( $comment_id, 'geo_address', sanitize_text_field( $_POST['address'] ) );
		} else {
			delete_comment_meta( $comment_id, 'geo_address' );
		}

		if ( ! empty( $_POST['map_zoom'] ) ) {
			update_comment_meta( $comment_id, 'geo_zoom', sanitize_text_field( $_POST['map_zoom'] ) );
		} else {
			delete_comment_meta( $comment_id, 'geo_zoom' );
		}

		if ( ! empty( $_POST['address'] ) ) {
			if ( isset( $_POST['geo_public'] ) ) {
				update_comment_meta( $comment_id, 'geo_public', $_POST['geo_public'] );
			}
		}
	}


	/* Save the user metadata. */
	public static function save_user_meta( $user_id ) {
		// Check the user's permissions.
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		/* OK, its safe for us to save the data now. */
		if ( ! empty( $_POST['latitude'] ) ) {
			update_user_meta( $user_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_user_meta( $user_id, 'geo_latitude' );
		}
		if ( ! empty( $_POST['longitude'] ) ) {
			update_user_meta( $user_id, 'geo_longitude', $_POST['longitude'] );
		} else {
			delete_user_meta( $user_id, 'geo_longitude' );
		}

		if ( ! empty( $_POST['address'] ) ) {
			update_user_meta( $user_id, 'geo_address', $_POST['address'] );
		} else {
			delete_user_meta( $user_id, 'geo_address' );
		}
		if ( ! empty( $_POST['latitude'] ) && ! empty( $_POST['longitude'] ) ) {
			if ( isset( $_POST['geo_public'] ) ) {
				update_user_meta( $user_id, 'geo_public', $_POST['geo_public'] );
			}
		}
	}



}
?>
