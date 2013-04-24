<?php
/*
--------------------------------------------------------------------------------
Plugin Name: BP Group Hierarchy Propagate Activity
Description: Enables propagation of Activity Items up a hierarchy of BuddyPress Groups established by the BP Group Hierarchy plugin.
Version: 0.1
Author: Christian Wach
Author URI: http://haystack.co.uk
Plugin URI: http://haystack.co.uk
--------------------------------------------------------------------------------
*/



// set our version here
define( 'BP_GROUPS_HIERARCHY_PROPAGATE_VERSION', '0.1' );



/*
--------------------------------------------------------------------------------
BpGroupsHierarchyPropagate Class
--------------------------------------------------------------------------------
*/

class BpGroupsHierarchyPropagate {

	/** 
	 * properties
	 */
	
	// builds list of subgroups during recursion
	public $subgroup_ids = array();
	
	
	
	/** 
	 * @description: initialises this object
	 * @return object
	 */
	function __construct() {
	
		// intercept group activity calls and add sub-group items
		add_filter( 'bp_has_activities', array( $this, 'propagate_content_up' ), 10, 3 );

		// --<
		return $this;

	}
	
	
	
	/**
	 * @description: PHP 4 constructor
	 * @return object
	 */
	function BpGroupsHierarchyPropagate() {
		
		// is this php5?
		if ( version_compare( PHP_VERSION, "5.0.0", "<" ) ) {
		
			// call php5 constructor
			$this->__construct();
			
		}
		
		// --<
		return $this;

	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: intercept group activity calls and add sub-group items
	 * @param boolean $has_activities
	 * @param object $activities_template
	 * @param array $template_args
	 * @return array
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
			$template_args['filter']['primary_id'] = $this->_get_children( 
				$template_args['filter']['primary_id'] 
			);
	
			// recreate activities template
			global $activities_template;
			$activities_template = new BP_Activity_Template( $template_args );
	
			// override return value
			$has_activities = $activities_template->has_activities();
			
		}
	
		// --<
		return $has_activities;
	
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: build a comma-delimited list of child groups
	 * @param integer $group_id
	 * @return string
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
		return implode( ',', $this->subgroup_ids );

	}
	
	
	
} // class ends






/**
 * @description: initialise our plugin after BuddyPress initialises
 */
function bp_groups_hierarchy_propagate() {

	// test for presence BP Group Hierarchy plugin
	if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {

		// init plugin
		$bp_groups_hierarchy_propagate = new BpGroupsHierarchyPropagate;
		
	}

}

// add action for plugin init
add_action( 'bp_setup_globals', 'bp_groups_hierarchy_propagate' );





