<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Plugin Name: RO Session manager
 * Plugin URI: http://www.redolive.com
 * Description: Helper plugin to work with sessions
 * Version: 1.0
 * Author: Red Olive
 * Author URI: http://www.redolive.com
 * License: Proprietary
 */


class RoSession {
	public static $cookie_name = 'ro_session_id';

	public static function get_session( $key = '' ){
		global $wpdb;
		if( ! Self::has_cookie_id() ) return array();

		$session = unserialize( $wpdb->get_var( 'SELECT `data` FROM `' . $wpdb->prefix . 'ro_sessions` WHERE `id` = '.Self::get_cookie_id() ) );
		if( $key ) return ! empty( $session[$key] ) ? $session[$key] : false;

		return $session;
	}

	public static function set_session( $key, $data ){
		global $wpdb;

		$session = Self::get_session();
		$session[$key] = $data;

		return $wpdb->update(
			Self::get_session_table_name(),
			array(
				'data' => serialize( $session ),
				'updated_at' => current_time( 'mysql', true )
			),
			array( 'id' => Self::get_cookie_id() ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function unset_key( $key ){
		global $wpdb;

		$session = Self::get_session();
		if( isset( $session[$key] ) ) {
			unset( $session[$key] );
			return $wpdb->update(
				Self::get_session_table_name(),
				array(
					'data' => serialize( $session ),
					'updated_at' => current_time( 'mysql', true )
				),
				array( 'id' => Self::get_cookie_id() ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		return true;
	}

	public static function get_session_table_name(){
		global $wpdb;
		return $wpdb->prefix . 'ro_sessions';
	}

	public static function has_cookie_id(){
		$cookie_id = ! empty( $_COOKIE[Self::$cookie_name] ) ? $_COOKIE[Self::$cookie_name] : false;
		return $cookie_id;
	}

	public static function get_cookie_id(){
		$cookie_id = ! empty( $_COOKIE[Self::$cookie_name] ) ? $_COOKIE[Self::$cookie_name] : Self::set_cookie();
		return $cookie_id;
	}

	public static function set_cookie(){
		global $wpdb;
		$wpdb->insert(
			Self::get_session_table_name(),
			array(
				'data' => serialize( array() ),
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true )
			),
			array( '%s' )
		);
		setcookie( Self::$cookie_name, $wpdb->insert_id, time() + WEEK_IN_SECONDS, "/", $_SERVER['HTTP_HOST'], FALSE, TRUE );
		return $wpdb->insert_id;
	}

	public static function cleanup(){
		global $wpdb;
		// delete old unused sessions
		$wpdb->query( 'DELETE FROM `'. Self::get_session_table_name() . '` WHERE `updated_at` <= "'. date( 'Y-m-d H:i:s', strtotime( '-1 week' ) ) .'"' );
		// delete empty
		$wpdb->query( 'DELETE FROM `'. Self::get_session_table_name() . '` WHERE `data` = "" AND `updated_at` <= "'. date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) .'"' );
		return true;
	}
}

// schedule cleanup method
if( ! wp_next_scheduled( 'ro_session_cleanup' ) ) wp_schedule_event( time(), 'hourly', 'ro_session_cleanup' );
add_action( 'ro_session_cleanup', array( 'RoSession', 'cleanup' ) );
