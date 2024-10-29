<?php
/*
Plugin Name: Automatic Page Publish / Expire
Plugin URI: http://wordpress.org/extend/plugins/page-publish-start-end-date/
Description: It lets user adds start and end date of a page for publishing and after end date has been passed, page's status is automatic marked as draft. Page will start displaying only from start date rather then on which page is posted.
Author: Harpinder Singh
Version: 1.0
Author URI: http://wordpress.org/support/profile/harpinder
Author Email: singhharpinder@hotmail.com
Translation: NA
*/

/*
    This file is part of ICanLocalize Translator.

    ICanLocalize Translator is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    ICanLocalize Translator is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with ICanLocalize Translator.  If not, see <http://www.gnu.org/licenses/>.
*/


/* Load translation, if it exists */
$plugin_dir = basename(dirname(__FILE__));

// Default Values
$expirationdateDefaultDateFormat = __('l F jS, Y','page-expire');
$expirationdateDefaultTimeFormat = __('g:ia','page-expire');

// Detect WPMU/MultiSite
function pageExpirator_is_wpmu() {
	if (function_exists('is_multisite'))
		return is_multisite();
	else
		return file_exists(ABSPATH."/wpmu-settings.php");
}

// Timezone Setup
function pageExpiratorTimezoneSetup() 
{
	if ( !$timezone_string = get_option( 'timezone_string' ) ) 
	{
		return false;
	}

	@date_default_timezone_set($timezone_string);
}

// Add cron interval of 60 seconds
function pageExpiratorAddCronMinutes($array) 
{
       $array['pageexpiratorminute'] = array(
               'interval' => 60,
               'display' => __('Once a Minute','page-expire')
       );
	return $array;
}
add_filter('cron_schedules','pageExpiratorAddCronMinutes');


/** 
 * Function that does the actualy status update - called by wp_cron
 */
function expirationdate_delete_expired_posts() 
{
	global $wpdb;
	
	/// FOR PAGE EXPIRY - START DATE
	// ###############################
	pageExpiratorTimezoneSetup();

	// Current time
	$dCurrTime = time();
	
	// SQL query
	$sSql = "SELECT post_id, meta_value, meta_key, ID FROM {$wpdb->postmeta} as postmeta, {$wpdb->posts} as posts 
			 WHERE postmeta.post_id = posts.ID 
			 AND postmeta.meta_key IN('expiration-date-start', 'expiration-date') ";
	$result = $wpdb->get_results($sSql);
	if (!empty($result)) 
	foreach ($result as $a)
	{
		$aData[$a->ID][$a->meta_key]  = $a->meta_value;
	}

	if($aData && !empty($aData))
	{
		foreach($aData as $iID=>$aExp)
		{
			// IF current time lies between start and end date, publish the page
			if($dCurrTime >= $aExp['expiration-date-start'] && $dCurrTime <= $aExp['expiration-date'])
			{
				// Change status to Publish
				wp_update_post(array('ID' => $iID, 'post_status' => 'publish'));
			}
			else  // otherwise, update status as draft, so that page does not show up on front end
			{
				// Change status to Draft
				wp_update_post(array('ID' => $iID, 'post_status' => 'draft'));
			}
		}
	}



	/// FOR PAGE EXPIRY - END DATE
	// ###############################
	pageExpiratorTimezoneSetup();
	$dCurrTime = time();
	$result = $wpdb->get_results('select post_id, meta_value from ' . $wpdb->postmeta . ' as postmeta, '.$wpdb->posts.' as posts where postmeta.post_id = posts.ID AND posts.post_status = "publish" AND postmeta.meta_key = "expiration-date" AND postmeta.meta_value <= "' . $dCurrTime . '"');
  	if (!empty($result)) foreach ($result as $a)
	{
		// Update the post which is expired !
		wp_update_post(array('ID' => $a->post_id, 'post_status' => 'draft'));

		// Delete entry from wp_postmeta
		delete_post_meta($a->post_id, 'expiration-date');
	}

}

if (pageExpirator_is_wpmu())
	add_action ('expirationdate_delete_'.$current_blog->blog_id, 'expirationdate_delete_expired_posts');
else
	add_action ('expirationdate_delete', 'expirationdate_delete_expired_posts');

/** 
 * Called at plugin activation
 */
function expirationdate_activate () 
{
	global $current_blog,$expirationdateDefaultDateFormat;

	pageExpiratorTimezoneSetup();

	if (pageExpirator_is_wpmu())
		wp_schedule_event(mktime(date('H'),0,0,date('m'),date('d'),date('Y')), 'pageexpiratorminute', 'expirationdate_delete_'.$current_blog->blog_id);
	else
		wp_schedule_event(mktime(date('H'),0,0,date('m'),date('d'),date('Y')), 'pageexpiratorminute', 'expirationdate_delete');
}
register_activation_hook (__FILE__, 'expirationdate_activate');

/**
 * Called at plugin deactivation
 */
function expirationdate_deactivate () 
{
	global $current_blog;

	if (pageExpirator_is_wpmu())
		wp_clear_scheduled_hook('expirationdate_delete_'.$current_blog->blog_id);
	else
		wp_clear_scheduled_hook('expirationdate_delete');
}
register_deactivation_hook (__FILE__, 'expirationdate_deactivate');

/**
 * adds an 'Expires' column to the page display table.
 */
function expirationdate_add_column ($columns) 
{
  	$columns['expirationdate'] = __('Expires (UTC)','page-expire').'<br/>';
  	return $columns;
}
add_filter ('manage_pages_columns', 'expirationdate_add_column');

/**
 * fills the 'Expires' column of the page display table.
 */
function expirationdate_show_value ($column_name) 
{
	global $wpdb, $post;
	$id = $post->ID;
	if ($column_name === 'expirationdate') 
	{
		pageExpiratorTimezoneSetup();
		$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = \"expiration-date\" AND post_id=$id";
		$ed = $wpdb->get_var($query);
		echo ($ed ? date('Y/m/d H:i',$ed) : __("Never",'page-expire'));
  	}
}
add_action ('manage_pages_custom_column', 'expirationdate_show_value');

/**
 * Adds hooks to get the meta box added to page
 */
function expirationdate_meta_page() 
{
	add_meta_box('expirationdatediv', __('Page Expirator','page-expire'), 'expirationdate_meta_box', 'page', 'advanced', 'high');
}
add_action ('edit_page_form','expirationdate_meta_page');

/**
 * Adds hooks to get the meta box added to custom post types
 */
function expirationdate_meta_custom() {
    $custom_post_types = get_post_types();
    foreach ($custom_post_types as $t) {
       	add_meta_box('expirationdatediv', __('Post Expirator','page-expire'), 'expirationdate_meta_box', $t, 'advanced', 'high');
    }
}
add_action ('edit_form_advanced','expirationdate_meta_custom');

/**
 * Actually adds the meta box
 */
function expirationdate_meta_box($post) 
{ 
	// Get default month
	pageExpiratorTimezoneSetup();
	$expirationdatestartts	= get_post_meta($post->ID,'expiration-date-start',true);// Start Date
	$expirationdatets		= get_post_meta($post->ID,'expiration-date',true);	// End Date

	if (empty($expirationdatets)) 
	{
		// Start Date
		$defaultmonthS	= date('F');
		$defaultdayS	= date('d');
		$defaulthourS	= date('H');
		$defaultyearS	= date('Y');
		$defaultminuteS = date('i');

		// End date
		$defaultmonth	= date('F');
		$defaultday		= date('d');
		$defaulthour	= date('H');
		$defaultyear	= date('Y');
		$defaultminute	= date('i');
		$disabled		= 'disabled="disabled"';

	} else {
		
		// Start Date
		$defaultmonthS	= date('F',$expirationdatestartts);
		$defaultdayS	= date('d',$expirationdatestartts);
		$defaultyearS	= date('Y',$expirationdatestartts);
		$defaulthourS	= date('H',$expirationdatestartts);
		$defaultminuteS = date('i',$expirationdatestartts);

		// End date
		$defaultmonth	= date('F',$expirationdatets);
		$defaultday		= date('d',$expirationdatets);
		$defaultyear	= date('Y',$expirationdatets);
		$defaulthour	= date('H',$expirationdatets);
		$defaultminute	= date('i',$expirationdatets);

		$enabled = ' checked="checked"';
		$disabled = '';
	}

	$rv = array();
	$rv[] = '<p><input type="checkbox" name="enable-expirationdate" id="enable-expirationdate" value="checked"'.$enabled.' onclick="expirationdate_ajax_add_meta(\'enable-expirationdate\')" />';
	$rv[] = '<label for="enable-expirationdate">'.__('Enable Page Expiration','page-expire').'</label></p>';

	// START DATE
	$rv[] = '<table><tr>';
	$rv[] = '<th style="text-align: left;">Start Date</th>';
    $rv[] = '<th style="text-align: left;">'.__('Year','page-expire').'</th>';
    $rv[] = '<th style="text-align: left;">'.__('Month','page-expire').'</th>';
    $rv[] = '<th style="text-align: left;">'.__('Day','page-expire').'</th>';
    $rv[] = '<th style="text-align: left;">'.__('Hour','page-expire').'('.date('T',mktime(0, 0, 0, $i, 1, date("Y"))).')</th>';
	$rv[] = '<th style="text-align: left;">'.__('Minute','page-expire').'</th>';
	$rv[] = '</tr><tr>';
	$rv[] = '<td>&nbsp;</td>';	
	$rv[] = '<td>';	
	$rv[] = '<select name="expirationdate_year_start" id="expirationdate_year_start"'.$disabled.'">';
		$currentyear = date('Y');
		if ($defaultyearS < $currentyear)
			$currentyear = $defaultyearS;
		for($i = $currentyear; $i < $currentyear + 8; $i++) {
			if ($i == $defaultyear)
				$selected = ' selected="selected"';
			else
				$selected = '';
			$rv[] = '<option'.$selected.'>'.($i).'</option>';
		}
		$rv[] = '</select>';
	$rv[] = '</td><td>';
		$rv[] = '<select name="expirationdate_month_start" id="expirationdate_month_start"'.$disabled.'">';
		for($i = 1; $i <= 12; $i++) {
			if ($defaultmonthS == date('F',mktime(0, 0, 0, $i, 1, date("Y"))))
				$selected = ' selected="selected"';
			else
				$selected = '';
			$rv[] = '<option value="'.date('m',mktime(0, 0, 0, $i, 1, date("Y"))).'"'.$selected.'>'.date(__('F','page-expire'),mktime(0, 0, 0, $i, 1, date("Y"))).'</option>';
		}
	$rv[] = '</select>';	 
	$rv[] = '</td><td>';
		$rv[] = '<input type="text" id="expirationdate_day_start" name="expirationdate_day_start" value="'.$defaultdayS.'" size="2"'.$disabled.'" />,';
	$rv[] = '</td><td>';
	 	$rv[] = '<select name="expirationdate_hour_start" id="expirationdate_hour_start"'.$disabled.'">';
		for($i = 1; $i <= 24; $i++) 
		{
			if ($defaulthourS == date('H',mktime($i, 0, 0, date("n"), date("j"), date("Y"))))
				$selected = ' selected="selected"';
			else
				$selected = '';
			$rv[] = '<option value="'.date('H',mktime($i, 0, 0, date("n"), date("j"), date("Y"))).'"'.$selected.'>'.date(__('H','page-expire'),mktime($i, 0, 0, date("n"), date("j"), date("Y"))).'</option>';
		}
		$rv[] = '</td><td>';
		$rv[] = '<input type="text" id="expirationdate_minute_start" name="expirationdate_minute_start" value="'.$defaultminuteS.'" size="2"'.$disabled.'" />';
		$rv[] = '<input type="hidden" name="expirationdate_formcheck_start" value="true" />';
	
	// End Date
	$rv[] = '</tr><tr>';
	$rv[] = '<th style="text-align: left;">End Date</th>';
    $rv[] = '<th style="text-align: left;">'.__('Year','page-expire').'</th>';
    $rv[] = '<th style="text-align: left;">'.__('Month','page-expire').'</th>';
    $rv[] = '<th style="text-align: left;">'.__('Day','page-expire').'</th>';
    $rv[] = '<th style="text-align: left;">'.__('Hour','page-expire').'('.date('T',mktime(0, 0, 0, $i, 1, date("Y"))).')</th>';
	$rv[] = '<th style="text-align: left;">'.__('Minute','page-expire').'</th>';
	$rv[] = '</tr><tr>';
	$rv[] = '<td>&nbsp;</td>';	
	$rv[] = '<td>';	
		$rv[] = '<select name="expirationdate_year" id="expirationdate_year"'.$disabled.'">';
		$currentyear = date('Y');
		if ($defaultyear < $currentyear)
			$currentyear = $defaultyear;
		for($i = $currentyear; $i < $currentyear + 8; $i++) {
			if ($i == $defaultyear)
				$selected = ' selected="selected"';
			else
				$selected = '';
			$rv[] = '<option'.$selected.'>'.($i).'</option>';
		}
		$rv[] = '</select>';
	$rv[] = '</td><td>';
		$rv[] = '<select name="expirationdate_month" id="expirationdate_month"'.$disabled.'">';
		for($i = 1; $i <= 12; $i++) {
			if ($defaultmonth == date('F',mktime(0, 0, 0, $i, 1, date("Y"))))
				$selected = ' selected="selected"';
			else
				$selected = '';
			$rv[] = '<option value="'.date('m',mktime(0, 0, 0, $i, 1, date("Y"))).'"'.$selected.'>'.date(__('F','page-expire'),mktime(0, 0, 0, $i, 1, date("Y"))).'</option>';
		}
	$rv[] = '</select>';	 
	$rv[] = '</td><td>';
		$rv[] = '<input type="text" id="expirationdate_day" name="expirationdate_day" value="'.$defaultday.'" size="2"'.$disabled.'" />,';
	$rv[] = '</td><td>';
 	$rv[] = '<select name="expirationdate_hour" id="expirationdate_hour"'.$disabled.'">';
		for($i = 1; $i <= 24; $i++) {
			if ($defaulthour == date('H',mktime($i, 0, 0, date("n"), date("j"), date("Y"))))
				$selected = ' selected="selected"';
			else
				$selected = '';
			$rv[] = '<option value="'.date('H',mktime($i, 0, 0, date("n"), date("j"), date("Y"))).'"'.$selected.'>'.date(__('H','page-expire'),mktime($i, 0, 0, date("n"), date("j"), date("Y"))).'</option>';
		}
		$rv[] = '</td><td>';
		$rv[] = '<input type="text" id="expirationdate_minute" name="expirationdate_minute" value="'.$defaultminute.'" size="2"'.$disabled.'" />';
		$rv[] = '<input type="hidden" name="expirationdate_formcheck" value="true" />';
	$rv[] = '</td></tr>';
	$rv[] = '</td></tr></table>';

	$rv[] = '<div id="expirationdate_ajax_result"></div>';

	echo implode("\n",$rv);
}


/**
 * Add's ajax javascript
 */
function expirationdate_js_admin_header() {
	// use JavaScript SACK library for Ajax
	wp_print_scripts( array( 'sack' ));

	// Define custom JavaScript function
	?>
<script type="text/javascript">
//<![CDATA[
function expirationdate_ajax_add_meta(expireenable) {
	var mysack = new sack("<?php expirationdate_get_blog_url(); ?>wp-admin/admin-ajax.php");

	var expire		= document.getElementById(expireenable); // Start Date
	var expire_start	= document.getElementById(expireenable); // End Date

	if (expire.checked == true) {
		var enable = 'true';
		document.getElementById('expirationdate_month').disabled = false;
		document.getElementById('expirationdate_day').disabled = false;
		document.getElementById('expirationdate_year').disabled = false;
		document.getElementById('expirationdate_hour').disabled = false;		document.getElementById('expirationdate_minute').disabled = false;
		
		// End date
		document.getElementById('expirationdate_month_start').disabled = false;
		document.getElementById('expirationdate_day_start').disabled = false;
		document.getElementById('expirationdate_year_start').disabled = false;
		document.getElementById('expirationdate_hour_start').disabled = false;
		document.getElementById('expirationdate_minute_start').disabled = false;

	} else {
		document.getElementById('expirationdate_month').disabled = true;
		document.getElementById('expirationdate_day').disabled = true;
		document.getElementById('expirationdate_year').disabled = true;
		document.getElementById('expirationdate_hour').disabled = true;
		document.getElementById('expirationdate_minute').disabled = true;
		
		// End date
		document.getElementById('expirationdate_month_start').disabled = true;
		document.getElementById('expirationdate_day_start').disabled = true;
		document.getElementById('expirationdate_year_start').disabled = true;
		document.getElementById('expirationdate_hour_start').disabled = true;
		document.getElementById('expirationdate_minute_start').disabled = true;

		var enable = 'false';
	}

	mysack.execute = 1;
	mysack.method = 'POST';
	mysack.setVar( "action", "expirationdate_ajax" );
	mysack.setVar( "enable", enable );
	mysack.encVar( "cookie", document.cookie, false );
	mysack.onError = function() { alert('Ajax error in enabling post expiration' )};
	mysack.runAJAX();

	return true;
}
//]]>
</script>
<?php
}
add_action('admin_print_scripts', 'expirationdate_js_admin_header' );

/**
 * Get correct URL (HTTP or HTTPS)
 */
function expirationdate_get_blog_url() {
	global $current_blog;
	$schema = ( isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	
	if (pageExpirator_is_wpmu())	
        echo $schema.$current_blog->domain.$current_blog->path;
	else
        echo get_bloginfo('siteurl').'/';
}

/**
 * Called when page is saved - stores expiration-date meta value
 */
function expirationdate_update_post_meta($id) {
	if (!isset($_POST['expirationdate_formcheck']))
		return false;
		
		// Start date
        $month_start	= $_POST['expirationdate_month_start'];
        $day_start		= $_POST['expirationdate_day_start'];
        $year_start		= $_POST['expirationdate_year_start'];
        $hour_start		= $_POST['expirationdate_hour_start'];
        $minute_start	= $_POST['expirationdate_minute_start'];

		// End date
		$month			= $_POST['expirationdate_month'];
        $day			= $_POST['expirationdate_day'];
        $year			= $_POST['expirationdate_year'];
        $hour			= $_POST['expirationdate_hour'];
        $minute			= $_POST['expirationdate_minute'];


	if (isset($_POST['enable-expirationdate'])) 
	{
		pageExpiratorTimezoneSetup();
        // Format Date - Start Date
        $ts = mktime($hour,$minute,0,$month,$day,$year);

        // Format Date - End Date
        $ts2 = mktime($hour_start, $minute_start, 0, $month_start, $day_start, $year_start);

        // Update Post Meta
		delete_post_meta($id, 'expiration-date');
		delete_post_meta($id, 'expiration-date-start');
	    
		// Save values
		update_post_meta($id, 'expiration-date', $ts, true);
		update_post_meta($id, 'expiration-date-start', $ts2, true);
	} 
	else
	{
		delete_post_meta($id, 'expiration-date');
		delete_post_meta($id, 'expiration-date-start');
	}
}
add_action('save_post','expirationdate_update_post_meta');



?>