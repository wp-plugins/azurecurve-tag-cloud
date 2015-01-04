<?php
/*
Plugin Name: azurecurve Tag Cloud
Plugin URI: http://wordpress.azurecurve.co.uk/plugins/tag-cloud/

Description: Displays a tag cloud with easy control of settings and exclusion of tags from the cloud.
Version: 1.0.4

Author: azurecurve
Author URI: http://wordpress.azurecurve.co.uk/

Text Domain: azurecurve-tag-cloud
Domain Path: /languages

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

The full copy of the GNU General Public License is available here: http://www.gnu.org/licenses/gpl.txt
 */

function azc_tc_load_plugin_textdomain(){
	$loaded = load_plugin_textdomain( 'azurecurve-tag-cloud', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	//if ($loaded){ echo 'true'; }else{ echo 'false'; }
}
add_action('plugins_loaded', 'azc_tc_load_plugin_textdomain');

function azc_tc_load_css(){
	wp_enqueue_style( 'azurecurve-tag-cloud', plugins_url( 'style.css', __FILE__ ) );
}
add_action('admin_enqueue_scripts', 'azc_tc_load_css');
 
function azc_tc_set_default_options($networkwide) {
	
	$new_options = array(
				'use_network_settings' => 1,
				'smallest' => '8',
				'largest' => '22',
				'unit' => 'pt',
				'number' => '45',
				'format' => 'flat',
				'orderby' => 'Name',
				'order' => 'ASC'
			);
	
	// set defaults for multi-site
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			global $wpdb;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			$original_blog_id = get_current_blog_id();

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );

				if ( get_option( 'azc_tc_options' ) === false ) {
					add_option( 'azc_tc_options', $new_options );
				}
			}

			switch_to_blog( $original_blog_id );
		}else{
			if ( get_option( 'azc_tc_options' ) === false ) {
				add_option( 'azc_tc_options', $new_options );
			}
		}
		if ( get_site_option( 'azc_tc_options' ) === false ) {
			add_site_option( 'azc_tc_options', $new_options );
		}
	}
	//set defaults for single site
	else{
		if ( get_option( 'azc_tc_options' ) === false ) {
			add_option( 'azc_tc_options', $new_options );
		}
	}
}
register_activation_hook( __FILE__, 'azc_tc_set_default_options' );

function azc_tc_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=azurecurve-tag-cloud">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}
add_filter('plugin_action_links', 'azc_tc_plugin_action_links', 10, 2);

function azc_tc_settings_menu() {
	add_options_page( 'azurecurve Tag Cloud Settings',
	'azurecurve Tag Cloud', 'manage_options',
	'azurecurve-tag-cloud', 'azc_tc_config_page' );
}
add_action( 'admin_menu', 'azc_tc_settings_menu' );

function azc_tc_config_page() {
	if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'azurecurve-tag-cloud'));
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_tc_options' );
	?>
	<div id="azc-tc-general" class="wrap">
		<fieldset>
			<h2><?php _e('azurecurve Tag Cloud Settings', 'azurecurve-tag-cloud'); ?></h2>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_tc_options" />
				<input name="page_options" type="hidden" value="smallest, largest, number" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_tc_nonce', 'azc_tc_nonce' ); ?>
				<table class="form-table">
				<tr><th scope="row"><label for="include_exclude"><?php _e('Include/Exclude Tags?', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="include_exclude">
						<option value="include" <?php if($options['include_exclude'] == 'include'){ echo ' selected="selected"'; } ?>>Include</option>
						<option value="exclude" <?php if($options['include_exclude'] == 'exclude'){ echo ' selected="selected"'; } ?>>Exclude</option>
					</select>
					<p class="description"><?php _e('Flag whether marked tags should be included or excluded from the tag cloud', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				
				<tr><th scope="row">Tags to Include/Exclude</th><td>
					<div class='azc_tc_scrollbox'>
						<?php
							global $wpdb;
							$query = "SELECT t.term_id AS `term_id`, t.name AS `name` FROM $wpdb->term_taxonomy tt INNER JOIN $wpdb->terms t On t.term_id = tt.term_id WHERE tt.taxonomy = 'post_tag' ORDER BY t.name";
							$_query_result = $wpdb->get_results( $query );
							foreach( $_query_result as $data ) {
								?>
								<label for="<?php echo $data->term_id; ?>"><input name="tag[<?php echo $data->term_id; ?>]" type="checkbox" id="tag" value="1" <?php checked( '1', $options['tag'][$data->term_id] ); ?> /><?php echo $data->name; ?></label><br />
								<?php
							}
							unset( $_query_result );
						?>
					</div>
					<p class="description"><?php _e('Mark the tags you want to include/exclude from the tag cloud', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				
				<?php if (function_exists('is_multisite') && is_multisite()) { ?>
					<tr><th scope="row">Use Network Settings</th><td>
						<fieldset><legend class="screen-reader-text"><span><?php _e('Use Network Settings', 'azurecurve-tag-cloud'); ?></span></legend>
						<label for="use_network_settings"><input name="use_network_settings" type="checkbox" id="use_network_settings" value="1" <?php checked( '1', $options['use_network_settings'] ); ?> /><?php _e('Use Network Settings? The settings below will be ignored', 'azurecurve-tag-cloud'); ?></label>
						</fieldset>
					</td></tr>
				<?php } ?>
				<tr><th scope="row"><label for="smallest"><?php _e('Smallest Size', 'azurecurve-tag-cloud'); ?></label></th><td>
					<input type="text" name="smallest" value="<?php echo esc_html( stripslashes($options['smallest']) ); ?>" class="small-text" />
					<p class="description"><?php _e('The text size of the tag with the lowest count value', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="largest"><?php _e('Largest Size', 'azurecurve-tag-cloud'); ?></label></th><td>
					<input type="text" name="largest" value="<?php echo esc_html( stripslashes($options['largest']) ); ?>" class="small-text" />
					<p class="description"><?php _e('The text size of the tag with the highest count value', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="unit"><?php _e('Unit', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="unit">
						<option value="pt" <?php if($options['unit'] == 'pt'){ echo 'selected="selected"'; } ?>>pt</option>
						<option value="px" <?php if($options['unit'] == 'px'){ echo 'selected="selected"'; } ?>>px</option>
						<option value="em" <?php if($options['unit'] == 'em'){ echo 'selected="selected"'; } ?>>em</option>
						<option value="pc" <?php if($options['unit'] == 'pc'){ echo 'selected="selected"'; } ?>>%</option>
					</select>
					<p class="description"><?php _e('Unit of measure as pertains to the smallest and largest values', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="number"><?php _e('Number Of Tags', 'azurecurve-tag-cloud'); ?></label></th><td>
					<input type="text" name="number" value="<?php echo esc_html( stripslashes($options['number']) ); ?>" class="small-text" />
					<p class="description"><?php _e('The number of actual tags to display in the cloud', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="format"><?php _e('Format', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="format">
						<option value="flat" <?php if($options['format'] == 'flat'){ echo ' selected="selected"'; } ?>>Flat</option>
						<option value="list" <?php if($options['format'] == 'list'){ echo ' selected="selected"'; } ?>>List</option>
					</select>
					<p class="description"><?php _e('Format of the cloud display', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="orderby"><?php _e('Order By', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="orderby">
						<option value="name" <?php if($options['orderby'] == 'name'){ echo ' selected="selected"'; } ?>>Name</option>
						<option value="count" <?php if($options['orderby'] == 'count'){ echo ' selected="selected"'; } ?>>Count</option>
					</select>
					<p class="description"><?php _e('Order of the tags', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="order"><?php _e('Order', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="order">
						<option value="ASC" <?php if($options['order'] == 'ASC'){ echo ' selected="selected"'; } ?>>Ascending</option>
						<option value="DESC" <?php if($options['order'] == 'DESC'){ echo ' selected="selected"'; } ?>>Descending</option>
						<option value="RAND" <?php if($options['order'] == 'RAND'){ echo ' selected="selected"'; } ?>>Random</option>
					</select>
					<p class="description"><?php _e('Sort order', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }


function azc_tc_admin_init() {
	add_action( 'admin_post_save_azc_tc_options', 'process_azc_tc_options' );
}
add_action( 'admin_init', 'azc_tc_admin_init' );

function process_azc_tc_options() {
	// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){
		wp_die( __('You do not have permissions to perform this action', 'azurecurve-tag-cloud') );
	}
	// Check that nonce field created in configuration form is present
	if ( ! empty( $_POST ) && check_admin_referer( 'azc_tc_nonce', 'azc_tc_nonce' ) ) {
		// Retrieve original plugin options array
		$options = get_site_option( 'azc_tc_options' );
		
		$option_name = 'include_exclude';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'tag';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'use_network_settings';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		
		$option_name = 'smallest';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'largest';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'unit';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'number';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'format';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'orderby';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'order';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		// Store updated options array to database
		update_option( 'azc_tc_options', $options );
		
		// Redirect the page to the configuration form that was processed
		wp_redirect( add_query_arg( 'page', 'azurecurve-tag-cloud', admin_url( 'options-general.php' ) ) );
		exit;
	}
}

function add_azc_tc_network_settings_page() {
	if (function_exists('is_multisite') && is_multisite()) {
		add_submenu_page(
			'settings.php',
			'azurecurve Tag Cloud Settings',
			'azurecurve Tag Cloud',
			'manage_network_options',
			'azurecurve-tag-cloud',
			'azc_tc_network_settings_page'
			);
	}
}
add_action('network_admin_menu', 'add_azc_tc_network_settings_page');

function azc_tc_network_settings_page(){
	if(!current_user_can('manage_network_options')) wp_die(__('You do not have permissions to perform this action', 'azurecurve-tag-cloud'));
	$options = get_site_option('azc_tc_options');

	?>
	<div id="azc-tc-general" class="wrap">
		<fieldset>
			<h2><?php _e('azurecurve Tag Cloud Settings', 'azurecurve-tag-cloud'); ?></h2>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_tc_options" />
				<input name="page_options" type="hidden" value="smallest, largest, number" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_tc_nonce', 'azc_tc_nonce' ); ?>
				<table class="form-table">
				<tr><th scope="row"><label for="smallest"><?php _e('Smallest Size', 'azurecurve-tag-cloud'); ?></label></th><td>
					<input type="text" name="smallest" value="<?php echo esc_html( stripslashes($options['smallest']) ); ?>" class="small-text" />
					<p class="description"><?php _e('The text size of the tag with the lowest count value', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="largest"><?php _e('Largest Size', 'azurecurve-tag-cloud'); ?></label></th><td>
					<input type="text" name="largest" value="<?php echo esc_html( stripslashes($options['largest']) ); ?>" class="small-text" />
					<p class="description"><?php _e('The text size of the tag with the highest count value', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="unit"><?php _e('Unit', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="unit">
						<option value="pt" <?php if($options['unit'] == 'pt'){ echo 'selected="selected"'; } ?>>pt</option>
						<option value="px" <?php if($options['unit'] == 'px'){ echo 'selected="selected"'; } ?>>px</option>
						<option value="em" <?php if($options['unit'] == 'em'){ echo 'selected="selected"'; } ?>>em</option>
						<option value="pc" <?php if($options['unit'] == 'pc'){ echo 'selected="selected"'; } ?>>%</option>
					</select>
					<p class="description"><?php _e('Unit of measure as pertains to the smallest and largest values', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="number"><?php _e('Number Of Tags', 'azurecurve-tag-cloud'); ?></label></th><td>
					<input type="text" name="number" value="<?php echo esc_html( stripslashes($options['number']) ); ?>" class="small-text" />
					<p class="description"><?php _e('The number of actual tags to display in the cloud', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="format"><?php _e('Format', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="format">
						<option value="flat" <?php if($options['format'] == 'flat'){ echo ' selected="selected"'; } ?>>Flat</option>
						<option value="list" <?php if($options['format'] == 'list'){ echo ' selected="selected"'; } ?>>List</option>
					</select>
					<p class="description"><?php _e('Format of the cloud display', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="orderby"><?php _e('Order By', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="orderby">
						<option value="name" <?php if($options['orderby'] == 'name'){ echo ' selected="selected"'; } ?>>Name</option>
						<option value="count" <?php if($options['orderby'] == 'count'){ echo ' selected="selected"'; } ?>>Count</option>
					</select>
					<p class="description"><?php _e('Order of the tags', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="order"><?php _e('Order', 'azurecurve-tag-cloud'); ?></label></th><td>
					<select name="order">
						<option value="ASC" <?php if($options['order'] == 'ASC'){ echo ' selected="selected"'; } ?>>Ascending</option>
						<option value="DESC" <?php if($options['order'] == 'DESC'){ echo ' selected="selected"'; } ?>>Descending</option>
						<option value="RAND" <?php if($options['order'] == 'RAND'){ echo ' selected="selected"'; } ?>>Random</option>
					</select>
					<p class="description"><?php _e('Sort order', 'azurecurve-tag-cloud'); ?></p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
	<?php
}

function process_azc_tc_network_options(){     
	if(!current_user_can('manage_network_options')) wp_die(__('You do not have permissions to perform this action', 'azurecurve-tag-cloud'));
	if ( ! empty( $_POST ) && check_admin_referer( 'azc_tc_nonce', 'azc_tc_nonce' ) ) {
		// Retrieve original plugin options array
		$options = get_site_option( 'azc_tc_options' );
		
		$option_name = 'smallest';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'largest';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'unit';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'number';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'format';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'orderby';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'order';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		update_site_option( 'azc_tc_options', $options );

		wp_redirect(network_admin_url('settings.php?page=azurecurve-tag-cloud'));
		exit;  
	}
}
add_action('network_admin_edit_update_azc_tc_network_options', 'process_azc_tc_network_options');



// Register function to be called when widget initialization occurs
add_action( 'widgets_init', 'azurecurve_tag_cloud_create_widget' );

// Create new widget
function azurecurve_tag_cloud_create_widget() {
	register_widget( 'azurecurve_tag_cloud' );
}

// Widget implementation class
class azurecurve_tag_cloud extends WP_Widget {
	// Constructor function
	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		
		// Widget creation function
		parent::__construct( 'azurecurve_tag_cloud',
							 'azurecurve Tag Cloud',
							 array( 'description' =>
									__('A customizable cloud of your most used tags.', 'azurecurve-tag-cloud') ) );
	}

	/**
	 * enqueue function.
	 *
	 * @access public
	 * @return void
	 */
	public function enqueue() {
		// Enqueue Styles
		wp_enqueue_style( 'azurecurve-tag-cloud', plugins_url( 'style.css', __FILE__ ), '', '1.0.0' );
	}

	// Code to render options form
	function form( $instance ) {
		// Retrieve previous values from instance
		// or set default values if not present
		$widget_title = ( !empty( $instance['azc_tc_title'] ) ? 
							esc_attr( $instance['azc_tc_title'] ) :
							'Tag Cloud' );
		?>

		<!-- Display field to specify title  -->
		<p>
			<label for="<?php echo 
						$this->get_field_id( 'azc_tc_title' ); ?>">
			<?php echo 'Widget Title:'; ?>			
			<input type="text" 
					id="<?php echo $this->get_field_id( 'azc_tc_title' ); ?>"
					name="<?php echo $this->get_field_name( 'azc_tc_title' ); ?>"
					value="<?php echo $widget_title; ?>" />			
			</label>
		</p> 

		<?php
	}

	// Function to perform user input validation
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['azc_tc_title'] = strip_tags( $new_instance['azc_tc_title'] );

		return $instance;
	}
	
	// Function to display widget contents
	function widget ( $args, $instance ) {
		// Extract members of args array as individual variables
		extract( $args );

		// Display widget title
		echo $before_widget;
		echo $before_title;
		$widget_title = ( !empty( $instance['azc_tc_title'] ) ? 
					esc_attr( $instance['azc_tc_title'] ) :
					'Tag Cloud' );
		echo apply_filters( 'widget_title', $widget_title );
		echo $after_title; 
		
		$options = get_option( 'azc_tc_options' );
		$siteoptions = $options;
		if ($options['use_network_settings'] == 1){
			$options = get_site_option( 'azc_tc_options' );
		}
		$args = array(
					'smallest'                  => $options['smallest'],
					'largest'                   => $options['largest'],
					'unit'                      => $options['unit'],
					'number'                    => $options['number'],
					'format'                    => $options['format'],
					'orderby'                   => strtolower($options['orderby']),
					'order'                     => strtoupper($options['order'])
				//	'include'					=> ($options['include_exclude'] == 'include' ? $options['tag'] : null)
				//	'exclude'					=> ($options['include_exclude'] == 'exclude' ? $options['tag'] : null)
				);
		$tags = '';
		foreach ($siteoptions['tag'] as $key => $value){
			$tags .= $key.',';
		}
		if ($siteoptions['include_exclude'] == 'include'){
			$args['include'] = $tags;
		}else{
			$args['exclude'] = $tags;
		}
		wp_tag_cloud( $args );
		
		echo $after_widget;
	}
}

?>