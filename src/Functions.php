<?php

use Cloudcogs\Woocommerce\Gateway\PowerTranz\Plugin;

function cc_woocommerce_gateway_powertranz_plugin(): Plugin
{
    return Plugin::instance();
}

function cc_woocommerce_gateway_powertranz_icon(): string
{
    return get_site_url()."/wp-content/plugins/".Plugin::TEXT_DOMAIN."/assets/fac-visa-mc.png";
}

function is_woocommerce_active(): bool
{
    $active_plugins = (array) get_option('active_plugins', array());

    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }

    return in_array('woocommerce/woocommerce.php', $active_plugins) ||
           array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}
