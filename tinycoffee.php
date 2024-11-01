<?php
/*
Plugin Name: tinyCoffee
Plugin URI: http://arunas.co/tinycoffee
Description: Ask people for coffee money
Version: 0.3.0
Author: Arūnas Liuiza
Author URI: http://arunas.co
Donate Link: http://arunas.co#coffee
Text Domain: tinycoffee
Domain Path: /languages
*/

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}

/**
 * Main plugin class
 *
 */
class Tiny_Coffee {

	const VERSION = '0.3.0';

	/**
	 * Holds status
	 *
	 * @var bool
	 */
	protected static $active = false;


	/**
	 * Holds FontAwesome version
	 *
	 * @var string
	 */
	private static $fontawesome = '4.7.0';
	/**
	 * Holds current option values
	 *
	 * @var array
	 */
	protected static $options = array();

	/**
	 * Holds includes dir path
	 *
	 * @var string
	 */
	protected static $includes_dir;


	public static function init() {
		self::$includes_dir = plugin_dir_path( __FILE__ ) . 'includes/';

		// PayPal IPN listener.
		add_action( 'init', array( 'Tiny_Coffee', 'ipn_listener' ) );
		# FontAwesome version init
		self::$fontawesome = self::get_fontawesome_version( self::$fontawesome, false );
		# hook into cron
		add_action( 'tinycoffee_daily', array( 'Tiny_Coffee', 'get_fontawesome_version' ) );

		# i18n
		load_plugin_textdomain( 'tinycoffee', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		# Settings page
		require_once self::$includes_dir . 'options-data.php';
		require_once self::$includes_dir . 'options.php';
		$coffee_settings = new Tiny_Coffee_Options( Tiny_Coffee_Options_Data::get() );
		self::$options   = $coffee_settings->get();

		// No callbacks activated, nothing to do.
		if ( ! is_array( self::$options['callback_activate'] )
			|| empty( self::$options['callback_activate'] )
		) {
			return;
		}

		self::$active = true;
		self::activate_callbacks();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ), 9 );
	}

	public static function ipn_listener() {
		if ( ! isset( $_GET['tinycoffee_notify'] ) ) {
			return false;
		}
		do_action( 'tinycoffee_ipn' );
		die();
	}


	public static function activate_callbacks() {
		// Activate callbacks
		$callbacks = self::$options['callback_activate'];
		// shortcode
		if ( isset( $callbacks['shortcode'] ) ) {
			add_shortcode( 'coffee', array( __CLASS__, 'shortcode' ) );
			add_shortcode( 'tiny_coffee', array( __CLASS__, 'shortcode' ) );
		}
		// widget
		if ( isset( $callbacks['widget'] ) ) {
			add_action( 'widgets_init', array( __CLASS__, 'widget' ) );
		}
		// modal view
		if ( isset( $callbacks['modal_view'] ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'modal_view' ) );
		}
	}


	public static function scripts() {
		$dir_url = plugin_dir_url( __FILE__ );
		$suffix  = ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) ? '.min' : '';

		wp_register_script( 'jquery-noui-slider', $dir_url . 'js/nouislider.jquery.min.js', 'jquery', null, true );
		wp_enqueue_script( 'tinycoffee', $dir_url . 'js/tinycoffee' . $suffix . '.js', array( 'jquery', 'jquery-noui-slider' ), self::VERSION, true );

		wp_register_style( 'font-awesome', '//netdna.bootstrapcdn.com/font-awesome/' . self::$fontawesome . '/css/font-awesome.css' );
		wp_enqueue_style( 'font-awesome' );

		wp_enqueue_style( 'jquery-noui-slider', $dir_url . 'js/nouislider.jquery.min.css' );
		wp_enqueue_style( 'tinycoffee', $dir_url . 'css/tinycoffee' . $suffix . '.css', false, self::VERSION );
	}


	public static function modal_view( $attr ) {
		?>
			<div id="modal-container">
				<article class="modal-info modal-style-wide fade js-modal">
					<section class="modal-content">
						<a class="coffee_close" href="#"><i class="fa fa-times"></i><span class="hidden"><?php _e( 'Close', 'tinycoffee' ) ?></span></a>
						<?php echo Tiny_Coffee::shortcode( $attr ); ?>
					</section>
				</article>
			</div>
			<div class="modal-background fade">&nbsp;</div>
		<?php
	}


	public static function widget() {
		require_once self::$includes_dir . 'widget.php';
		register_widget( 'Tiny_Coffee_Widget' );
	}


	public static function tag( $attr = false ) {
		return Tiny_Coffee::shortcode( $attr );
	}


	public static function shortcode( $attr = false, $content = false ) {
		if ( ! self::$active ) {
			return false;
		}

		$settings = array();

		if ( empty( $attr ) ) {
			$attr = array();
		}

		foreach ( $attr as $key => $val ) {
			switch ( $key ) {
				case 'title'  : $key = 'coffee_title'; break;
				case 'text'   : $key = 'coffee_text'; break;
				case 'icon'   : $key = 'coffee_icon'; break;
				case 'price'  : $key = 'coffee_price'; break;
				case 'for'    : $key = 'paypal_text'; break;
				case 'test'   : $key = 'paypal_testing'; break;
				case 'success': $key = 'callback_success'; break;
				case 'cancel' : $key = 'callback_cancel'; break;
			}
			$settings[ $key ] = $val;
		}

		if ( ! empty( $content ) ) {
			$settings['coffee_text'] = $content;
		}

		return Tiny_Coffee::build( $settings );
	}

	public static function _get_current_uri() {
		global $wp;
		$current_url = home_url( add_query_arg( array(), $wp->request ) );
		return $current_url;
	}
	public static function build( $settings = false ) {
		if ( empty( $settings ) ) {
			$settings = array();
		}
		$defaults = array(
			'callback_notify' => add_query_arg( 'tinycoffee_notify', true, get_bloginfo( 'url' ) ),
		);
		$settings = wp_parse_args( $settings, $defaults );
		$options = wp_parse_args( $settings, self::$options );
		if ( $options['callback_auto'] ) {
			$options['callback_success'] = add_query_arg( 'tinycoffee_success', true, Tiny_Coffee::_get_current_uri() );
			$options['callback_cancel']  = add_query_arg( 'tinycoffee_cancel', true, Tiny_Coffee::_get_current_uri() );
		}
		$options = apply_filters( 'tinycoffee_options', $options );
		$form_data = array(
			'business'      => $options['paypal_email'],
			'cmd'           => '_xclick',
			'rm'            => '2',
			'amount'        => 0,
			'rm'						=> 2,
			'return'        => $options['callback_success'],
			'cancel_return' => $options['callback_cancel'],
			'notify_url'		=> $options['callback_notify'],
			'item_name'     => $options['paypal_text'],
			'currency_code' => $options['paypal_currency'],
			'no_shipping'   => 1,
			//'no_note'       => '1'
		);

		if ( ! empty( $options['paypal_testing'] ) ) {
			$paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}
		else {
			$paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
		}

		ob_start();
		?>
		<?php if ( ! empty( $options['widget'] ) ) : ?>
			<div class="tiny_coffee tiny_coffee_widget">
		<?php else : ?>
			<div class="tiny_coffee">
				<header class="modal-header">
					<h1><?php echo $options['coffee_title'] ?></h1>
				</header>
		<?php endif; ?>
				<?php printf(
					'<section class="modal-body" data-icon="%s" data-price="%s" data-rate="%s" data-currency="%s" data-hash="%s" data-default="%s">',
					esc_attr( $options['coffee_icon'] ),
					esc_attr( $options['coffee_price'] ),
					esc_attr( $options['paypal_exchange'] ),
					esc_attr( $options['coffee_currency'] ),
					esc_attr( $options['coffee_hash'] ),
					esc_attr( $options['coffee_default'] )
				) ?>
					<?php if ( ! empty( $options['coffee_text'] ) ) : ?>
						<div class="tiny_coffee_text">
							<?php echo wpautop( $options['coffee_text'] ) ?>
						</div>
					<?php endif; ?>
					<div class="tiny_coffee_slider"></div>
					<div class="right"><span class="count"></span> <small class="count2"></small></div>
					<form action="<?php echo esc_attr( $paypal_url ) ?>" method="post" class="tiny_coffee_form">
						<?php foreach ( $form_data as $key => $value ) : ?>
							<?php printf(
								'<input type="hidden" name="%s" value="%s"/>',
								esc_attr( $key ),
								esc_attr( $value )
							) ?>
						<?php endforeach; ?>
						<button type="submit"><i class="fa fa-shopping-cart"></i></button>
					</form>
				</section>
			</div>
		<?php
		return ob_get_clean();
	}
	public static function get_fontawesome_version( $current= false, $fetch = true ) {
		$version = get_transient('tinycoffee_fontawesome');
		if ( false === $version && true === $fetch ) {
			$response = wp_remote_get( 'http://api.jsdelivr.com/v1/bootstrap/libraries/font-awesome' );
			if ( !is_wp_error($response) ) {
				$response = wp_remote_retrieve_body( $response );
				if ( !is_wp_error($response) ) {
					$response = json_decode( $response, true );
					if ( isset( $response[0]['lastversion']) ) {
						$version = $response[0]['lastversion'];
						set_transient( 'tinycoffee_fontawesome', $version, DAY_IN_SECONDS + HOUR_IN_SECONDS );
					}
				}
			}
		}
		if ( 1 == version_compare( $version, $current) ) {
			$version = $version;
		} else {
			$version = $current;
		}
		$version = apply_filters( 'tinycoffee_fontawesome_version', $version );
		return $version;
	}
	public static function activate() {
		self::$fontawesome = self::get_fontawesome_version( self::$fontawesome );
		wp_schedule_event( time(), 'daily', 'tinycoffee_daily' );
	}
	// remove WP-Cron hook on deactivation
	public static function deactivate() {
		wp_clear_scheduled_hook( 'tinycoffee_daily' );
	}
}

add_action( 'plugins_loaded', array( 'Tiny_Coffee', 'init' ) );
register_activation_hook( __FILE__,  array( 'Tiny_Coffee', 'activate' ) );
register_deactivation_hook( __FILE__,  array( 'Tiny_Coffee', 'deactivate' ) );

function get_coffee( $options ) { return Tiny_Coffee::tag( $options ); }
function the_coffee( $options ) { echo Tiny_Coffee::tag( $options ) ; }