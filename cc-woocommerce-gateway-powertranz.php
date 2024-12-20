<?php
/**
 * Plugin Name: PowerTranz 3DS2 - WooCommerce Payment Gateway
 * Plugin URI: https://github.com/cloudcogsio/omnipay-powertranz-3ds2-gateway
 * Description: WooCommerce Payment Gateway for First Atlantic Commerce (PowerTranz) 3DS2 (https://firstatlanticcommerce.com)
 * Author: cloudcogs.io
 * Author URI: https://www.cloudcogs.io/
 * Version: 0.2.0
 * Text Domain: cc-woocommerce-gateway-powertranz
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2011-2022, SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author    cloudcogs.io (info@tsiana.ca)
 * @copyright Copyright (c) 2022, cloudcogs.io
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * WC requires at least: 8.0
 * WC tested up to: 9.4.2
 */

defined('ABSPATH') or exit;

/**
 * The plugin loader class.
 *
 * @since 0.1.0
 */
class WC_PowerTranz_Loader
{

    /** minimum PHP version required by this plugin */
    const MINIMUM_PHP_VERSION = '7.4';

    /** minimum WordPress version required by this plugin */
    const MINIMUM_WP_VERSION = '5.2';

    /** minimum WooCommerce version required by this plugin */
    const MINIMUM_WC_VERSION = '4.9.2';

    /** SkyVerge plugin framework version used by this plugin */
    const FRAMEWORK_VERSION = '5.15.1';

    /** the plugin name, for displaying notices */
    const PLUGIN_NAME = 'PowerTranz 3DS2 - WooCommerce Payment Gateway';

    private static $instance;

    private array $notices = array();


    /**
     * Constructs the class.
     *
     * @since 0.1.0
     */
    protected function __construct()
    {

        register_activation_hook(__FILE__, array( $this, 'activation_check' ));

        add_action('admin_init', array( $this, 'check_environment' ));
        add_action('admin_init', array( $this, 'add_plugin_notices' ));
        add_action('admin_notices', array( $this, 'admin_notices' ), 15);

        add_filter('extra_plugin_headers', array( $this, 'add_documentation_header'));

        if ($this->is_environment_compatible()) {
            add_action('plugins_loaded', array( $this, 'init_plugin' ));
        }
    }


    /**
     * Cloning instances is forbidden due to singleton pattern.
     *
     * @since 0.1.0
     */
    public function __clone()
    {

        _doing_it_wrong(__FUNCTION__, sprintf('You cannot clone instances of %s.', get_class($this)), '1.0.0');
    }


    /**
     * Unserializing instances is forbidden due to singleton pattern.
     *
     * @since 0.1.0
     */
    public function __wakeup()
    {

        _doing_it_wrong(__FUNCTION__, sprintf('You cannot unserialize instances of %s.', get_class($this)), '1.0.0');
    }


    /**
     * Initializes the plugin.
     *
     * @since 0.1.0
     */
    public function init_plugin()
    {

        if (! $this->plugins_compatible()) {
            return;
        }

        $this->load_framework();

        $loader = require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');

        // depending on how the plugin is structured, you may need to manually load the file that contains the initial plugin function
        require_once(plugin_dir_path(__FILE__) . 'src/Functions.php');

        // if WooCommerce is inactive, render a notice and bail
        if (!is_woocommerce_active()) {
            add_action('admin_notices', static function () {

                echo '<div class="error"><p>';
                esc_html_e(
                    self::PLUGIN_NAME.' is inactive because WooCommerce is not installed.',
                    \Cloudcogs\Woocommerce\Gateway\PowerTranz\Plugin::TEXT_DOMAIN
                );
                echo '</p></div>';
            });

            return;
        }

        cc_woocommerce_gateway_powertranz_plugin();
    }


    /**
     * Loads the base framework classes.
     *
     * @since 0.1.0
     */
    private function load_framework()
    {

        if (! class_exists('\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Plugin')) {
            require_once(plugin_dir_path(__FILE__) . 'vendor/skyverge/wc-plugin-framework/woocommerce/class-sv-wc-plugin.php');
        }

        if (! class_exists('\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Payment_Gateway_Plugin')) {
            require_once(plugin_dir_path(__FILE__) . 'vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/class-sv-wc-payment-gateway-plugin.php');
        }
    }


    /**
     * Gets the framework version in namespace form.
     *
     * @since 0.1.0
     *
     * @return string
     */
    public function get_framework_version_namespace(): string {

        return 'v' . str_replace('.', '_', $this->get_framework_version());
    }


    /**
     * Gets the framework version used by this plugin.
     *
     * @since 0.1.0
     *
     * @return string
     */
    public function get_framework_version(): string {

        return self::FRAMEWORK_VERSION;
    }


    /**
     * Checks the server environment and other factors and deactivates plugins as necessary.
     *
     * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
     *
     * @internal
     *
     * @since 0.1.0
     */
    public function activation_check()
    {

        if (! $this->is_environment_compatible()) {
            $this->deactivate_plugin();

            wp_die(self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message());
        }
    }


    /**
     * Checks the environment on loading WordPress, just in case the environment changes after activation.
     *
     * @internal
     *
     * @since 0.1.0
     */
    public function check_environment()
    {

        if (! $this->is_environment_compatible() && is_plugin_active(plugin_basename(__FILE__))) {
            $this->deactivate_plugin();

            $this->add_admin_notice(
                'bad_environment',
                'error',
                self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message()
            );
        }
    }


    /**
     * Adds notices for out-of-date WordPress and/or WooCommerce versions.
     *
     * @internal
     *
     * @since 0.1.0
     */
    public function add_plugin_notices()
    {

        if (! $this->is_wp_compatible()) {
            $this->add_admin_notice('update_wordpress', 'error', sprintf(
                '%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WP_VERSION,
                '<a href="' . esc_url(admin_url('update-core.php')) . '">',
                '</a>'
            ));
        }

        if (! $this->is_wc_compatible()) {
            $this->add_admin_notice('update_woocommerce', 'error', sprintf(
                '%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s
                to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WC_VERSION,
                '<a href="' . esc_url(admin_url('update-core.php')) . '">',
                '</a>',
                '<a href="' . esc_url('https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip') . '">',
                '</a>'
            ));
        }
    }


    /**
     * Determines if the required plugins are compatible.
     *
     * @since 0.1.0
     *
     * @return bool
     */
    private function plugins_compatible(): bool {

        return $this->is_wp_compatible() && $this->is_wc_compatible();
    }


    /**
     * Determines if the WordPress compatible.
     *
     * @since 0.1.0
     *
     * @return bool
     */
    private function is_wp_compatible(): bool {

        if (! self::MINIMUM_WP_VERSION) {
            return true;
        }

        return version_compare(get_bloginfo('version'), self::MINIMUM_WP_VERSION, '>=');
    }


    /**
     * Determines if the WooCommerce compatible.
     *
     * @since 0.1.0
     *
     * @return bool
     */
    private function is_wc_compatible(): bool {

        if (! self::MINIMUM_WC_VERSION) {
            return true;
        }

        return defined('WC_VERSION') && version_compare(WC_VERSION, self::MINIMUM_WC_VERSION, '>=');
    }


    /**
     * Deactivates the plugin.
     *
     * @internal
     *
     * @since 0.1.0
     */
    protected function deactivate_plugin()
    {

        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }


    /**
     * Adds an admin notice to be displayed.
     *
     * @param string $slug the slug for the notice
     * @param string $class the css class for the notice
     * @param string $message the notice message
     *
     *@since 0.1.0
     *
     */
    private function add_admin_notice( string $slug, string $class, string $message)
    {

        $this->notices[ $slug ] = array(
            'class'   => $class,
            'message' => $message
        );
    }


    /**
     * Displays any admin notices added with \SV_WC_Framework_Plugin_Loader::add_admin_notice()
     *
     * @internal
     *
     * @since 0.1.0
     */
    public function admin_notices()
    {

        foreach ($this->notices as $notice_key => $notice) {
            ?>
            <div class="<?php echo esc_attr($notice['class']); ?>">
                <p><?php echo wp_kses($notice['message'], array( 'a' => array( 'href' => array() ) )); ?></p>
            </div>
            <?php
        }
    }


    /**
     * Adds the Documentation URI header.
     *
     * @param string[] $headers original headers
     *
     * @return string[]
     * @internal
     *
     * @since 0.1.0
     *
     */
    public function add_documentation_header( array $headers): array {

        $headers[] = 'Documentation URI';

        return $headers;
    }


    /**
     * Determines if the server environment is compatible with this plugin.
     *
     * Override this method to add checks for more than just the PHP version.
     *
     * @since 0.1.0
     *
     * @return bool
     */
    private function is_environment_compatible(): bool {

        return version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=');
    }


    /**
     * Gets the message for display when the environment is incompatible with this plugin.
     *
     * @since 0.1.0
     *
     * @return string
     */
    private function get_environment_message(): string {

        return sprintf(
            'The minimum PHP version required for this plugin is %1$s. You are running %2$s.',
            self::MINIMUM_PHP_VERSION,
            PHP_VERSION
        );
    }


    /**
     * Gets the main \SV_WC_Framework_Plugin_Loader instance.
     *
     * Ensures only one instance can be loaded.
     *
     * @return \WC_PowerTranz_Loader
     * @since 0.1.0
     *
     */
    public static function instance(): WC_PowerTranz_Loader
    {

        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}

WC_PowerTranz_Loader::instance();
