<?php

namespace CouleurCitron\TarteaucitronWP;

use CouleurCitron\TarteaucitronWP\Pages\ServicesPage;
use CouleurCitron\TarteaucitronWP\Pages\SettingsPage;

class Plugin {

    const PATH = __DIR__;

    /**
     * @var static
     */
    protected static $instance;

    /**
     * @var ServicesPage
     */
    protected $servicesPage;

    /**
     * @var SettingsPage
     */
    protected $settingsPage;

    /**
     * Plugin constructor.
     */
    protected function __construct() {
        $this->servicesPage = new ServicesPage();
        $this->settingsPage = new SettingsPage();

        add_action( 'wp_enqueue_scripts', [ $this, 'siteScripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'adminScripts' ] );
        add_action( 'admin_menu', [ $this, 'adminMenu' ] );

        add_action( 'admin_post_tacwp_save_services', [ $this->servicesPage, 'saveServices' ] );
        add_action( 'admin_post_tacwp_save_settings', [ $this->settingsPage, 'saveSettings' ] );

        add_action( 'admin_head', [ $this, 'menuStyle' ] );
    }

    /**
     * @return static
     */
    public static function init() {
        if ( static::$instance === null ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function siteScripts() {
        $services = collect( $this->servicesPage->getActiveServices() );

        if ( $services->isEmpty() ) {
            return;
        }

        $settings = json_encode( $this->settingsPage->getSettings() );

        wp_enqueue_script( 'tarteaucitron', plugins_url( 'dist/tarteaucitronjs/tarteaucitron.js', static::PATH ),
            [], false, true );
        wp_add_inline_script( 'tarteaucitron',
            "tarteaucitron.init({$settings});" . $services->map->script()->values()->implode( '' ) );
    }

    public function adminScripts() {
        if ( get_current_screen()->id === 'toplevel_page_tacwp_services' ) {
            wp_enqueue_script( 'tacwp', plugins_url( 'dist/admin.js', static::PATH ), [], false, true );
        }
    }

    public function adminMenu() {
        $this->servicesPage->register();
        $this->settingsPage->register();
    }

    public function menuStyle() {
        ?>
        <style>
            #adminmenu #toplevel_page_tacwp_services img {
                width: 20px;
                padding-top: 7px;
            }
        </style>
        <?php
    }

}