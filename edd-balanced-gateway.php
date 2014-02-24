<?php
/**
 * Plugin Name:     Easy Digital Downloads - Balanced Gateway
 * Plugin URI:      https://easydigitaldownloads.com/extension/balanced-payment-gateway
 * Description:     Adds a payment gateway for balancedpayments.com
 * Version:         1.1.0
 * Author:          Daniel J Griffiths
 * Author URI:      http://section214.com
 * Text Domain:     edd-balanced-gateway
 *
 * @package         EDD\Gateway\Balanced
 * @author          Daniel J Griffiths <dgriffiths@section214.com>
 * @copyright       Copyright (c) 2014, Daniel J Griffiths
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


if( !class_exists( 'EDD_Balanced_Gateway' ) ) {

    /**
     * Main EDD_Balanced_Gateway class
     *
     * @since       1.0.0
     */
    class EDD_Balanced_Gateway {

        /**
         * @var         EDD_Balanced_Gateway $instance The one true EDD_Balanced_Gateway
         * @since       1.0.0
         */
        private static $instance;

        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Balanced_Gateway
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new EDD_Balanced_Gateway();
                self::$instance->setup_constants();
                self::$instance->includes();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin path
            define( 'BALANCED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'BALANCED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

            // Plugin version
            define( 'BALANCED_PLUGIN_VER', '1.1.0' );
        }


        /**
         * Include necessary files
         *
         * @access      private
         * @since       1.1.0
         * @return      void
         */
        private function includes() {
            // We need composer autoloader...
            require_once( BALANCED_PLUGIN_DIR . '/includes/libraries/vendor/autoload.php' );
        }

        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {
            // Edit plugin metalinks
            add_filter( 'plugin_row_meta', array( $this, 'plugin_metalinks' ), null, 2 );

            // Handle licensing
            if( class_exists( 'EDD_License' ) ) {
                $license = new EDD_License( __FILE__, 'Balanced Gateway', BALANCED_PLUGIN_VER, 'Daniel J Griffiths' );
            }
            
            // Register settings
            add_filter( 'edd_settings_gateways', array( $this, 'settings' ), 1 );

            // Add the gateway
            add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );

            // Process payment
            add_action( 'edd_gateway_balanced', array( $this, 'process_payment' ) );

            // Enqueue scripts
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'wp_head', array( $this, 'balanced_js_init' ) );

            // Display errors
            add_action( 'edd_after_cc_fields', array( $this, 'errors_div' ), 999 );
        }


        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
            $lang_dir = apply_filters( 'EDD_Balanced_Gateway_lang_dir', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale     = apply_filters( 'plugin_locale', get_locale(), '' );
            $mofile     = sprintf( '%1$s-%2$s.mo', 'edd-balanced-gateway', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-balanced-gateway/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-balanced-gateway/ folder
                load_textdomain( 'edd-balanced-gateway', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-balanced-gateway/languages/ folder
                load_textdomain( 'edd-balanced-gateway', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-balanced-gateway', false, $lang_dir );
            }
        }


        /**
         * Modify plugin metalinks
         *
         * @access      public
         * @since       1.1.0
         * @param       array $links The current links array
         * @param       string $file A specific plugin table entry
         * @return      array $links The modified links array
         */
        public function plugin_metalinks( $links, $file ) {
            if( $file == plugin_basename( __FILE__ ) ) {
                $help_link = array(
                    '<a href="https://easydigitaldownloads.com/support/forum/add-on-plugins/balanced-payment-gateway/" target="_blank">' . __( 'Support Forum', 'edd-balanced-gateway' ) . '</a>'
                );

                $docs_link = array(
                    '<a href="http://section214.com/docs/category/edd-balanced-payment-gateway/" target="_blank">' . __( 'Docs', 'edd-balanced-gateway' ) . '</a>'
                );

                $links = array_merge( $links, $help_link, $docs_link );
            }

            return $links;
        }


        /**
         * Add settings
         *
         * @access      public
         * @since       1.0.0
         * @param       array $settings The existing plugin settings
         * @return      array
         */
        public function settings( $settings ) {
            $new_settings = array(
                array(
                    'id'    => 'edd_balanced_settings',
                    'name'  => '<strong>' . __( 'Balanced Payments Settings', 'edd-balanced-gateway' ) . '</strong>',
                    'desc'  => __( 'Configure your Balanced Payments Gateway settings', 'edd-balanced-gateway' ),
                    'type'  => 'header'
                ),
                array(
                    'id'    => 'edd_balanced_api_key',
                    'name'  => __( 'API Secret Key', 'edd-balanced-gateway' ),
                    'desc'  => __( 'Enter your Balanced Payments API Secret Key (<a href="https://dashboard.balancedpayments.com/">Dashboard</a>)', 'edd-balanced-gateway' ),
                    'type'  => 'text'
                ),
                array(
                    'id'    => 'edd_balanced_marketplace_uri',
                    'name'  => __( 'Marketplace ID', 'edd-balanced-gateway' ),
                    'desc'  => __( 'Enter your marketplace ID (<a href="https://dashboard.balancedpayments.com/">Dashboard</a>)', 'edd-balanced-gateway' ),
                    'type'  => 'text'
                )
            );

            return array_merge( $settings, $new_settings );
        }


        /**
         * Enqueue necessary scripts
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function enqueue_scripts() {
            if( edd_is_checkout() && edd_is_gateway_active( 'balanced' ) ) {
                wp_enqueue_script( 'balanced', 'https://js.balancedpayments.com/v1/balanced.js', array( 'jquery' ) );
                wp_enqueue_script( 'edd-balanced-js', BALANCED_PLUGIN_URL . 'assets/js/edd-balanced.js', array( 'jquery', 'balanced' ), time() );
            }
        }


        /**
         * Initialize balanced.js
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function balanced_js_init() {
            if( edd_is_checkout() && edd_is_gateway_active( 'balanced' ) ) {
                if( edd_get_option( 'edd_balanced_marketplace_uri', '' ) != '' ) {
                    echo '<script type="text/javascript">balanced.init(\'/v1/marketplaces/' . edd_get_option( 'edd_balanced_marketplace_uri', '' ) . '\')</script>';
                }
            }
        }


        /**
         * Register our new gateway
         *
         * @access      public
         * @since       1.0.0
         * @param       array $gateways The current gateway list
         * @return      array $gateways The updated gateway list
         */
        public function register_gateway( $gateways ) {
            $gateways['balanced'] = array(
                'admin_label'       => 'Balanced Payments',
                'checkout_label'    => __( 'Credit Card', 'edd-balanced-gateway' )
            );

            return $gateways;
        }


        /**
         * Process payment submission
         *
         * @access      public
         * @since       1.0.0
         * @param       array $purchase_data The data for a specific purchase
         * @return      void
         */
        public function process_payment( $purchase_data ) {
            $errors = edd_get_errors();

            if( !$errors ) {
                Httpful\Bootstrap::init();
                RESTful\Bootstrap::init();
                Balanced\Bootstrap::init();

                Balanced\Settings::$api_key = edd_get_option( 'edd_balanced_api_key', '' );

                $customer = new Balanced\Customer();
                $customer->save();
                $customer->addCard( $_POST['balancedToken'] );

                try{
                    $amount = number_format( $purchase_data['price'] * 100, 0 );
                    $result = $customer->debit( edd_sanitize_amount( $amount ) );
                } catch( Exception $e ) {
                    edd_record_gateway_error( __( 'Balanced Error', 'edd-balanced-gateway' ), print_r( $e, true ), 0 );
                    edd_set_error( 'card_declined', __( 'Your card was declined!', 'edd-balanced-gateway' ) );
                    edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
                }

                if( $result->status == 'succeeded' ) {
                    $payment_data = array(
                        'price'         => $purchase_data['price'],
                        'date'          => $purchase_data['date'],
                        'user_email'    => $purchase_data['user_email'],
                        'purchase_key'  => $purchase_data['purchase_key'],
                        'currency'      => edd_get_currency(),
                        'downloads'     => $purchase_data['downloads'],
                        'cart_details'  => $purchase_data['cart_details'],
                        'user_info'     => $purchase_data['user_info'],
                        'status'        => 'pending'
                    );

                    $payment = edd_insert_payment( $payment_data );

                    if( $payment ) {
                        edd_insert_payment_note( $payment, sprintf( __( 'Balanced Transaction ID: %s', 'edd-balanced-gateway' ), $result->id ) );
                        edd_update_payment_status( $payment, 'publish' );
                        edd_send_to_success_page();
                    } else {
                        edd_set_error( 'authorize_error', __( 'Error: your payment could not be recorded. Please try again.', 'edd-balanced-gateway' ) );
                        edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
                    }
                } else {
                    wp_die( $result->description, $result->status );
                }
            } else {
                edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            }
        }


        /**
         * Output form errors
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function errors_div() {
            echo '<div id="edd-balanced-payment-errors"></div>';
        }
    }
}


/**
 * The main function responsible for returning the one true EDD_Balanced_Gateway
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      EDD_Balanced_Gateway The one true EDD_Balanced_Gateway
 */
function EDD_Balanced_Gateway_load() {
    if( !class_exists( 'Easy_Digital_Downloads' ) ) {
        if( !class_exists( 'S214_EDD_Activation' ) ) {
            require_once( 'includes/class.s214-edd-activation.php' );
        }

        $activation = new S214_EDD_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
        $activation = $activation->run();
    } else {
        return EDD_Balanced_Gateway::instance();
    }
}
add_action( 'plugins_loaded', 'EDD_Balanced_Gateway_load' );
