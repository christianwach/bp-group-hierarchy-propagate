<?php
/*
--------------------------------------------------------------------------------
Plugin Name: BP Group Hierarchy Propagate
Description: Enables propagation of Activity Items up or down a hierarchy of BuddyPress Groups established by the BP Group Hierarchy plugin.
Version: 0.3.1
Author: Christian Wach
Author URI: http://haystack.co.uk
Plugin URI: https://github.com/christianwach/bp-group-hierarchy-propagate
Text Domain: bp-group-hierarchy-propagate
Domain Path: /languages
--------------------------------------------------------------------------------
*/



// set our version here
define( 'BP_GROUPS_HIERARCHY_PROPAGATE_VERSION', '0.3.1' );

// store reference to this file
if ( !defined( 'BP_GROUPS_HIERARCHY_PROPAGATE_FILE' ) ) {
	define( 'BP_GROUPS_HIERARCHY_PROPAGATE_FILE', __FILE__ );
}

// store URL to this plugin's directory
if ( !defined( 'BP_GROUPS_HIERARCHY_PROPAGATE_URL' ) ) {
	define( 'BP_GROUPS_HIERARCHY_PROPAGATE_URL', plugin_dir_url( BP_GROUPS_HIERARCHY_PROPAGATE_FILE ) );
}
// store PATH to this plugin's directory
if ( !defined( 'BP_GROUPS_HIERARCHY_PROPAGATE_PATH' ) ) {
	define( 'BP_GROUPS_HIERARCHY_PROPAGATE_PATH', plugin_dir_path( BP_GROUPS_HIERARCHY_PROPAGATE_FILE ) );
}



/*
--------------------------------------------------------------------------------
BP_Groups_Hierarchy_Propagate Class
--------------------------------------------------------------------------------
*/

class BP_Groups_Hierarchy_Propagate {

	/**
	 * Properties
	 */

	// builds list of subgroups during recursion
	public $subgroup_ids = array();



	/**
	 * Initialises this object
	 *
	 * @return object
	 */
	function __construct() {

		// use translation
		add_action( 'plugins_loaded', array( $this, 'translation' ) );

		// show setting on BP Group Hierarchy admin page
		add_action( 'bpgh_admin_after_settings', array( $this, 'admin_option' ) );

		// receive data from BP Group Hierarchy admin page
		add_action( 'bpgh_admin_after_save', array( $this, 'admin_save' ), 10, 1 );

		// get existing option, defaulting to 'up'
		$direction = get_site_option( 'bpghp_propagation_direction', 'up' );

		// which way then?
		if ( $direction == 'up' ) {

			// intercept group activity calls and add sub-group items
			add_filter( 'bp_has_activities', array( $this, 'propagate_content_up' ), 10, 3 );

		} elseif ( $direction == 'down' ) {

			// intercept group activity calls and add parent group items
			add_filter( 'bp_has_activities', array( $this, 'propagate_content_down' ), 10, 3 );

		} elseif ( $direction == 'both' ) {

			// intercept group activity calls and add parent group items
			add_filter( 'bp_has_activities', array( $this, 'propagate_content_both' ), 10, 3 );

		}

		// --<
		return $this;

	}



	/**
	 * PHP 4 constructor
	 *
	 * @return object
	 */
	function BP_Groups_Hierarchy_Propagate() {

		// is this php5?
		if ( version_compare( PHP_VERSION, "5.0.0", "<" ) ) {

			// call php5 constructor
			$this->__construct();

		}

		// --<
		return $this;

	}



	/**
	 * Loads translation, if present
	 *
	 * @return void
	 */
	function translation() {

		// only use, if we have it...
		if( function_exists('load_plugin_textdomain') ) {

			// enable translations
			load_plugin_textdomain(

				// unique name
				'bp-group-hierarchy-propagate',

				// deprecated argument
				false,

				// relative path to directory containing translation files
				dirname( plugin_basename( BP_GROUPS_HIERARCHY_PROPAGATE_FILE ) ) . '/languages/'

			);

		}

	}



	//##########################################################################



	/**
	 * Intercept group activity calls and add sub-group items
	 *
	 * @param boolean $has_activities True if there are activities, false otherwise
	 * @param object $activities_template The BP activities template object
	 * @param array $template_args The arguments used to init $activities_template
	 * @return boolean $has_activities True if there are activities, false otherwise
	 */
	function propagate_content_up( $has_activities, $activities_template, $template_args ) {

		// does group have children?
		if (

			isset( $template_args['filter'] ) AND
			isset( $template_args['filter']['object'] ) AND
			$template_args['filter']['object'] == 'groups' AND
			class_exists( 'BP_Groups_Hierarchy' ) AND
			BP_Groups_Hierarchy::has_children( $template_args['filter']['primary_id'] )

		) {

			// add children to query filter
			$children = $this->_get_children(
				$template_args['filter']['primary_id']
			);

			// add children to query filter
			$template_args['filter']['primary_id'] = implode( ',', $children );

			// allow plugins to modify the arguments
			$template_args = apply_filters( 'bpghp_args_up', $template_args );

			// recreate activities template
			global $activities_template;
			$activities_template = new BP_Activity_Template( $template_args );

			// override return value
			$has_activities = $activities_template->has_activities();

			// allow plugins to intercept the result
			$has_activities = apply_filters( 'bpghp_has_activities_up', $has_activities, $activities_template, $template_args );

		}

		// --<
		return $has_activities;

	}



	/**
	 * Intercept group activity calls and add parent group items
	 *
	 * @param boolean $has_activities True if there are activities, false otherwise
	 * @param object $activities_template The BP activities template object
	 * @param array $template_args The arguments used to init $activities_template
	 * @return boolean $has_activities True if there are activities, false otherwise
	 */
	function propagate_content_down( $has_activities, $activities_template, $template_args ) {

		// does group have at least one parent?
		if (

			isset( $template_args['filter'] ) AND
			isset( $template_args['filter']['object'] ) AND
			$template_args['filter']['object'] == 'groups' AND
			bp_group_hierarchy_has_parent()

		) {

			// get the parents
			$parents = bp_group_hierarchy_get_parents();

			// add current group
			$parents[] = $template_args['filter']['primary_id'];

			// add parents to query filter
			$template_args['filter']['primary_id'] = implode( ',', $parents );

			// allow plugins to modify the arguments
			$template_args = apply_filters( 'bpghp_args_down', $template_args );

			// recreate activities template
			global $activities_template;
			$activities_template = new BP_Activity_Template( $template_args );

			// override return value
			$has_activities = $activities_template->has_activities();

			// allow plugins to intercept the result
			$has_activities = apply_filters( 'bpghp_has_activities_down', $has_activities, $activities_template, $template_args );

		}

		// --<
		return $has_activities;

	}



	/**
	 * Intercept group activity calls and add parent AND sub-group items
	 *
	 * @param boolean $has_activities True if there are activities, false otherwise
	 * @param object $activities_template The BP activities template object
	 * @param array $template_args The arguments used to init $activities_template
	 * @return boolean $has_activities True if there are activities, false otherwise
	 */
	function propagate_content_both( $has_activities, $activities_template, $template_args ) {

		// init parents
		$parents = array();

		// does group have at least one parent?
		if (

			isset( $template_args['filter'] ) AND
			isset( $template_args['filter']['object'] ) AND
			$template_args['filter']['object'] == 'groups' AND
			bp_group_hierarchy_has_parent()

		) {

			// get the parents
			$parents = bp_group_hierarchy_get_parents();

		}

		// init children
		$children = array();

		// does group have children?
		if (

			isset( $template_args['filter'] ) AND
			isset( $template_args['filter']['object'] ) AND
			$template_args['filter']['object'] == 'groups' AND
			class_exists( 'BP_Groups_Hierarchy' ) AND
			BP_Groups_Hierarchy::has_children( $template_args['filter']['primary_id'] )

		) {

			// get children
			$children = $this->_get_children(
				$template_args['filter']['primary_id']
			);

		}

		// did we get any of either?
		if ( count( $parents ) > 0 OR count( $children ) > 0 ) {

			// merge the arrays
			$hierarchy = array_merge( $parents, $children );

			// add current group
			$hierarchy[] = $template_args['filter']['primary_id'];

			// make unique
			$hierarchy = array_unique( $hierarchy );

			// add groups to query filter
			$template_args['filter']['primary_id'] = implode( ',', $hierarchy );

			// allow plugins to modify the arguments
			$template_args = apply_filters( 'bpghp_args_both', $template_args );

			// recreate activities template
			global $activities_template;
			$activities_template = new BP_Activity_Template( $template_args );

			// override return value
			$has_activities = $activities_template->has_activities();

			// allow plugins to intercept the result
			$has_activities = apply_filters( 'bpghp_has_activities_both', $has_activities, $activities_template, $template_args );

		}

		// --<
		return $has_activities;

	}



	/**
	 * Save admin option on BP Group Hierarchy admin page
	 *
	 * @param array $options
	 * @return void
	 */
	function admin_save( $options ) {

		// get direction safely, defaulting to 'up'
		$direction = isset( $options['propagation'] ) ? $options['propagation'] : 'none';

		// save as site option
		update_site_option( 'bpghp_propagation_direction', $direction );

	}



	/**
	 * Show admin option on BP Group Hierarchy admin page
	 *
	 * @return void
	 */
	function admin_option() {

		// get existing option, defaulting to 'up'
		$direction = get_site_option( 'bpghp_propagation_direction', 'up' );

		?>

		<tr valign="top">
			<th scope="row"><label for="propagation"><?php _e( 'Propagate Activity', 'bp-group-hierarchy-propagate' ) ?></label></th>
			<td>
				<label>
					<select id="propagation" name="options[propagation]">
						<option value="up" <?php if( $direction == 'up' ) echo ' selected="selected"'; ?>><?php _e( 'Up', 'bp-group-hierarchy-propagate' ) ?></option>
						<option value="down" <?php if( $direction == 'down' ) echo ' selected="selected"'; ?>><?php _e( 'Down', 'bp-group-hierarchy-propagate' ) ?></option>
						<option value="both" <?php if( $direction == 'both' ) echo ' selected="selected"'; ?>><?php _e( 'Up &amp; Down', 'bp-group-hierarchy-propagate' ) ?></option>
					</select>
					<?php _e( 'Select the direction in which you want group activity to propagate.', 'bp-group-hierarchy-propagate' ); ?>
				</label>
			</td>
		</tr>

		<?php

	}



	//##########################################################################



	/**
	 * Build a list of child group IDs (includes current group ID)
	 *
	 * @param integer $group_id The numeric ID of the BuddyPress group
	 * @return array $subgroup_ids An array of numeric IDs of the child groups
	 */
	function _get_children( $group_id ) {

		// build group ids
		$this->subgroup_ids[] = $group_id;

		// get children from BP Group Hierarchy
		$children = bp_group_hierarchy_get_by_hierarchy(
			array( 'parent_id' => $group_id )
		);

		// did we get any?
		if ( isset( $children['groups'] ) AND count( $children['groups'] ) > 0 ) {

			// check them
			foreach( $children['groups'] AS $child ) {

				// is the user allowed to see content from this group?
				if (

					// allow if public
					'public' == $child->status OR

					// allow if they are a member
					groups_is_user_member( bp_loggedin_user_id(), $group_id )

				) {

					// recurse down the group hierarchy
					$this->_get_children( $child->id );

				}

			}

		}

		// --<
		return $this->subgroup_ids;

	}



} // class ends






/**
 * Initialise our plugin after BuddyPress initialises
 *
 * @return void
 */
function bp_groups_hierarchy_propagate() {

	// test for presence BP Group Hierarchy plugin
	if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {

		// init plugin
		$bp_groups_hierarchy_propagate = new BP_Groups_Hierarchy_Propagate;

	}

}

// add action for plugin init
add_action( 'bp_setup_globals', 'bp_groups_hierarchy_propagate' );





