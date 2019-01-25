<?php
/**
 * Plugin Name: My Functions
 * Plugin URI: http://yoursite.com
 * Description: Custom functions that I use.
 * Author: Your Name Here
 * Author URI: http://yoursite.com
 * Version: 1.0
 */

function debug_to_console( $data ) {
	if ( is_array( $data ) ) {
		$output = "<script>console.log( 'Debug Keys: " . implode( ',', array_keys($data)) . "' );</script>";
		$output = $output . "<script>console.log( 'Debug Objects: " . implode( ',', $data) . "' );</script>";
	}
	else
		$output = "<script>console.log( 'Debug Objects: " . $data . "' );</script>";
	echo $output;
}

add_action( 'wpmem_pre_register_data', 'my_data_validation' );
function my_data_validation( $fields ) {
  
    // The following can be used to output what is in 
    // the $fields array so you can see what's in it,
    // and then it quits:
    // echo "<pre>"; print_r( $fields ); echo "</pre>";
    // exit();
  
    // In a real, working use of this action, you can
    // do whatever you need to do with registration
    // data before it is inserted.  
    
    // The following is an example using it to perform
    // validation, you can stop registration and return
    // an error message to the user by globalizing 
    // $wpmem_themsg and giving it your error message:
     
    global $wpmem_themsg;
   
    if ( $fields['subscription_type'] == 'active' && 
	     $fields['Tribal_Affiliation'] == '' ) {
        $wpmem_themsg = 'You must enter Tribal Affiliation for an Active membership';
    }
}

add_action( 'init', 'wpmem_adjust_payment_options' );
function wpmem_adjust_payment_options() {
    global $wpmem_pp_sub;
    $wpmem_pp_sub->sub_levels = array(
 
        'option_name' => 'subscription_type',      // field option name
        'tiers' => array(
            'active' => array(
                'name' => 'Active', // subscription title
                'cost' => '25.00'      // subscription cost
            ),
            'associate' => array(
                'name' => 'Associate', // subscription title
                'cost' => '20.00'      // subscription cost
            ),
            'aspiring_educator' => array(
                'name' => 'Aspiring Educator',
                'cost' => '10.00'
            ),
            // add additional arrays as needed...
        ),
        'text' => array(
            'heading'      => 'Membership Category',
            'select'       => 'Please choose the membership category you need.',
            'pending'      => 'Your membership is pending payment.',
            'not_selected' => 'A membership category was not selected.',
        ),
    );
}

/*
 * This filter will insert a new link for upgrading the user level
 * into the bullet list of links that display on the user profile.
 */
//add_filter( 'wpmem_member_links_args', 'my_subscription_level_change_link' );
function my_subscription_level_change_link( $links ) {
    global $wpmem_pp_sub;
    $link = add_query_arg( 'a', 'changelevel', wpmem_profile_url() );
    $new_link = array( '<li><a href="' . $link . '">' . $wpmem_pp_sub->sub_levels['text']['heading'] . '</a></li>' );
    $links['rows'] = wpmem_array_insert( $links['rows'], $new_link, 1 );
    return $links;
}
 
/*
 * This builds the extra user screen for the user to select
 * a new subscription level.
 */
add_filter( 'wpmem_member_links', 'my_subscription_level_change' );
function my_subscription_level_change( $links ) {
    global $wp, $wpmem_pp_sub; 
 
    // Get the current user ID
    $user_id  = get_current_user_id();
    $meta_key = $wpmem_pp_sub->sub_levels['option_name'];
    // Get the user's current level.
    $current_level = get_user_meta( $user_id, $meta_key, true );
     
    // If the page is the changelevel page, display the change level form.
    if ( isset( $_REQUEST['a' ] ) && 'changelevel' == $_REQUEST['a'] ) {
         
        $fields = wpmem_fields();
        $label  = $fields[ $meta_key ]['label'];
        $values = $fields[ $meta_key ]['values'];
         
        // Build the form.
        $links = '<div id="wpmem_reg">';
        $links.= '<form name="form" method="post" action="' . wpmem_profile_url() . '" id="" class="form">';
        $links.= '<legend>' . $wpmem_pp_sub->sub_levels['text']['heading'] . '</legend>';
        $links.= '<p>' . $wpmem_pp_sub->sub_levels['text']['select'] . '</p>';
        // Make radio buttons out of the level selection values.
        foreach ( $values as $item ) {
            $pieces = explode( '|', $item );
            $is_checked = ( $current_level == $pieces[1] ) ? 'checked' : '';
            // If the value is not empty (such as a dropdown placeholder).
            if ( trim( $pieces[1] ) ) {
                $links.= '<input type="radio" name="' . $meta_key . '" value="' . $pieces[1]. '"' . $is_checked . '> ' . $pieces[0] . '<br />';
            }
        }
        // Add a hidden input for us to check if the form is submitted so we can save the selection.
        $links.= '<input type="hidden" name="changelevelsave" value="1" />';
        // Add a form submit button.
        $links.= '<br /><input type="submit" name="submit" value="Upgrade" class="buttons" />';
        // Close the form tag.
        $links.= '</form></div>';
         
    } else {
         
        // If the page is the regular member links, add the upgrade link and if needed, display success message.
 
        // Check if new display name form was submitted so we can save it and add a success message to the top.
        if ( isset( $_POST['changelevelsave'] ) && $_POST['changelevelsave'] == 1 ) {
 
            $new_level = $_POST[ $meta_key ];
             
            if ( $current_level != $new_level ) {
                // User selected a new user level.
                if ( ! is_user_expired() ) {
                    // If the user is expired, go ahead and set the new level so they can renew at the new rate.
                    update_user_meta( $user_id, $meta_key, $new_level );
                } else {
                    // Handle users changing levels mid-subscription.
                    update_user_meta( $user_id, $meta_key . '_pending', $new_level );
                }
				debug_to_console( '$new_level = ' . $new_level );
                $links = '<div class="wpmem_msg" align="center"><p>' . $wpmem_pp_sub->sub_levels['text']['heading'] . '<br>Upgrade to ' . $wpmem_pp_sub->sub_levels['tiers'][ $new_level ][ 'name' ] . '</p>' . wpmem_payment_button() . '</div>' . $links;
            } else {
                $links = '<div class="wpmem_msg" align="center"><p>' . $wpmem_pp_sub->sub_levels['text']['not_selected'] . '</p></div>' . $links;
            }
        }
    }
 
    return $links;
}
 
/*
 * This section handles changing the payment button price
 * values and checks for pending upgrade payment.
 */
add_filter( 'wpmem_payment_button_args', 'wpmem_adjust_payment_button' );
add_filter( 'wpmem_payment_form',        'wpmem_adjust_payment_button' );
function wpmem_adjust_payment_button( $args ){
 
    global $wpmem_regchk, $wpmem_pp_sub;
 
    if ( 'success' == $wpmem_regchk ) {
        // New registration.
        $sub_type = $_POST[ $wpmem_pp_sub->sub_levels['option_name'] ];
        // Define variables.
        $pending_upgrade = false; $user_id = false;
    } else {
        // Everyone else (user is logged in/renewal).
        $user_id     = get_current_user_id();
        $sub_type    = $wpmem_pp_sub->sub_levels['option_name'];
        $pending_key = $sub_type . '_pending';
         
        // Check for pending upgrade first.
        $pending_upgrade = get_user_meta( $user_id, $pending_key, true );
    }
     
    if ( $pending_upgrade ) {
        // Only need to change it if not the default subscription level.
        if ( array_key_exists( $pending_upgrade, $wpmem_pp_sub->sub_levels['tiers'] ) ) {
            $args['subscription_name'] = $wpmem_pp_sub->sub_levels['tiers'][ $pending_upgrade ]['name'];
            $args['subscription_cost'] = $wpmem_pp_sub->sub_levels['tiers'][ $pending_upgrade ]['cost'];
        }
        return $args;
         
    } else {
         
        // If no pending upgrade, check regular pricing.
        if ( $user_id ) {
            $sub_type = get_user_meta( $user_id, $sub_type, true );
        } else {
            $sub_type = $_POST[ $wpmem_pp_sub->sub_levels['option_name'] ];
        }
        foreach( $wpmem_pp_sub->sub_levels['tiers'] as $key => $val ) {
            if ( $sub_type == $key ) {
                $args['subscription_name'] = $val['name'];
                $args['subscription_cost'] = $val['cost'];
                return $args;
            }
        }
        return $args;
 
    }
 
    // If we get here, return $args unchanged.
    return $args;
}
 
/*
 * This handles the upgrade when the user pays.  It is hooked to the 
 * wpmem_ipn_success action that fires at the end of the IPN process.
 * This checks to see if there is a pending upgrade and if so, sets the 
 * subscription type to the pending upgrade and removes the key that marks
 * it as pending.
 */
add_action( 'wpmem_ipn_success', 'wpmem_handle_pending_upgrade', 10, 2 );
function wpmem_handle_pending_upgrade( $user_id, $details ) {
     
    global $wpmem_pp_sub;
     
    // Our levels haven't been added with the ipn_success action fires.
    if ( ! $wpmem_pp_sub->sub_levels['option_name'] ) {
        wpmem_adjust_payment_options();
    }
 
    $sub_key     = $wpmem_pp_sub->sub_levels['option_name'];
    $pending_key = $sub_key . '_pending';
         
    // Check for pending upgrade.
    $pending_upgrade = get_user_meta( $user_id, $pending_key, true );
     
    // If pending upgrade.
    if ( $pending_upgrade ) {
         
        // calculate cutoff period.
     
        // Update level (upgrade) and delete the pending meta.
        delete_user_meta( $user_id, $pending_key );
        update_user_meta( $user_id, $sub_key, $pending_upgrade );
    }
     
}

/* 
 * This function sends an email upon successfull PayPal payment
 *
 * TODO: need to use some of the logic in the wp-membmers notify_admin_email
 */
add_action( 'wpmem_ipn_success', 'my_admin_notification' );
function my_admin_notification( $user_id ) {
    global $wpmem_pp_sub;

    // Who is it going to?
    $to = "markeber@gmail.com, tucker.quetone@gmail.com, crs68@hotmail.com";
	
    // Get the user info.
    $user_info = get_userdata( $user_id );
	
    // Get the expiration date.
    $expires = get_user_meta( $user_id, 'expires', true );

    // Get the amount paid
    $sub_type = $wpmem_pp_sub->sub_levels['option_name'];
    $user_level = get_user_meta( $user_id, $sub_type, true );
    $amount = $wpmem_pp_sub->sub_levels['tiers'][ $user_level ]['cost'];
	
    $subject = "New subscription payment";
	
    $message = "The following user completed subscription payment \r\n";
    $message.= "user:" . $user_info->user_login . "\r\n";
    $message.= "email: " . $user_info->user_email . "\r\n";
    $message.= "New expiration date: " . $expires . "\r\n";
    $message.= "Amount: " . $amount;

    wp_mail( $to, stripslashes( $subject ), stripslashes( $message ) );
	
    return;
}

/*
 * This function removes the level selector from 
 * the user profile update form.
*/
add_filter( 'wpmem_fields', 'my_hide_fields', 10, 2 );
function my_hide_fields( $fields, $tag ) {
    if ( 'profile' == $tag || 'profile_dashboard' == $tag ) {
		unset($fields['Application_Type']);
	}
    return $fields;   
}

/*
 * Change the subscription_type to a readonly text field in the user
 * profile so they can't change it
 */
add_filter( 'wpmem_register_form_rows', 'my_reg_form', 10, 2 );
function my_reg_form( $rows, $tag ) {
        global $wpmem_pp_sub;

        // Meta key for the field being changed.
	$meta = "subscription_type";
	
	if ( 'edit' == $tag ) {
		$rows[ $meta ]['field'] = '<input name="' . $meta . '" value="' . $wpmem_pp_sub->sub_levels[ 'tiers' ][ $rows[ $meta ]['value'] ][ 'name' ] . '" type="text" readonly />';
	}
	
	return $rows;
}
 
/*
 * This handles the IPN validation that checks the price of the PayPal
 * transaction against the price that is expected. When customizing 
 * price levels, we need to adjust for that, otherwise all prices would
 * be validated against the primary saved price and would thus end up
 * being denied (since the prices would not match).
 */
add_action( 'wpmem_ipn_validation', function( $details ) {
    global $wpmem_pp_sub;
    wpmem_adjust_payment_options();
    $user_id  = $details['custom'];
    $sub_type = $wpmem_pp_sub->sub_levels['option_name'];
         
    $pending_key = $sub_type . '_pending';
    $pending_upgrade = get_user_meta( $user_id, $pending_key, true );
     
    if ( $pending_upgrade ) {
        if ( $wpmem_pp_sub->subscriptions['default']['subscription_cost'] != $details['mc_gross'] ) {
            $wpmem_pp_sub->subscriptions['default']['subscription_cost'] = $wpmem_pp_sub->sub_levels['tiers'][ $pending_upgrade ]['cost'];
        }
    } else {
        $user_level = get_user_meta( $user_id, $sub_type, true );
        if ( $wpmem_pp_sub->subscriptions['default']['subscription_cost'] != $details['mc_gross'] ) {
            $wpmem_pp_sub->subscriptions['default']['subscription_cost'] = $wpmem_pp_sub->sub_levels['tiers'][ $user_level ]['cost'];
        }
    }
    return;
},1);

/* 
 * Attemp to send users directly to PayPal instead of presenting a button
 * Currently doesn't work.
 */
//add_action( 'wpmem_post_register_data', 'my_push_user_to_paypal' );
function my_push_user_to_paypal( $fields ) {
     
    global $wpmem, $wpmem_pp_sub; 
 
    // If emails are to be sent, use these lines.
    require_once( WPMEM_PATH . 'inc/email.php' );
    wpmem_inc_regemail( $fields['ID'], $fields['password'], '1', $wpmem->fields, $fields );
    wpmem_notify_admin( $fields['ID'], $wpmem->fields, $fields );
    // End emails - if emails are not used/needed, remove above.
     
    // Get the PayPal Settings.
    $arr = $wpmem_pp_sub->subscriptions['default'];
    $tiers = $wpmem_pp_sub->sub_levels['tiers'];
    $item_name = stripslashes( $tiers[ $fields['subscription_type']['value'] ]['name'] );
    $amount = $tiers[ $fields['subscription_type']['value'] ]['cost'];
    debug_to_console( 'item_name = ' . $item_name );
    debug_to_console( 'amount = ' . $amount );
     
    // Set up defaults.
    $button_args = array(
        "cmd"           => ( ! $wpmem_pp_sub->paypal_cmd ) ? '_xclick' : $wpmem_pp_sub->paypal_cmd,
        "business"      => $wpmem_pp_sub->paypal_id,
        "item_name"     => $item_name, 
        "no_shipping"   => '',
        "return"        => wpmem_chk_qstr( $wpmem->user_pages['profile'] ) . 'a=renew&msg=thankyou',
        "notify_url"    => $wpmem_pp_sub->paypal_ipn,
        "no_note"       => '1', 
        "currency_code" => $wpmem_pp_sub->subscriptions['default']['currency'],
        "rm"            => '2',
        "custom"        => $fields['ID'],
    );
     
    // Add the user ID.
    $button_args['custom'] = $fields['ID'];
     
    // Handle regular vs recurring & recurring with trial.
    if ( $button_args['cmd'] === '_xclick' ) {
     
        $button_args['amount'] = $amount;
         
    } else {
            $button_args['a3']  = $wpmem_pp_sub->subscriptions['default']['subscription_cost'];
            $button_args['p3']  = $wpmem_pp_sub->subscriptions['default']['subscription_num'];
            $button_args['t3']  = strtoupper( $wpmem_pp_sub->subscriptions['default']['subscription_per'][0] );
            $button_args['src'] = "1";
            $button_args['sra'] = "1";
 
        if( $arr['trial_num'] ) {   
            $button_args['a1'] = $wpmem_pp_sub->subscriptions['default']['trial_cost'];
            $button_args['p1'] = $wpmem_pp_sub->subscriptions['default']['trial_num'];
            $button_args['t1'] = strtoupper( $wpmem_pp_sub->subscriptions['default']['trial_per'][0] );
        }
    }
     
    // Build and output the form so it can be submitted.
    echo '<form name="paypalform" action="' . $wpmem_pp_sub->paypal_url . '" method="post">';
    foreach ( $button_args as $key => $val ) {
        echo '<input type="hidden" name="' . $key . '" value="' . $val . '">';
    }
    echo '</form>';
     
    // Submit the form with JS.
    echo '<script language="JavaScript">document.paypalform.submit();</script>';
 
    // Exit so no screen output.
    exit();
}

add_filter( 'wpmem_notify_addr', 'my_admin_email' );
function my_admin_email( $email ) {
 
    // take the default and append a second address to it example:
    $email = $email . ', tucker.quetone@gmail.com, crs68@hotmail.com';
     
    // return the result
    return $email;
}
