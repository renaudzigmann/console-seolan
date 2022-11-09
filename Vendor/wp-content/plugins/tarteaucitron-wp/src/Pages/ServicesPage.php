<?php

namespace CouleurCitron\TarteaucitronWP\Pages;

use CouleurCitron\TarteaucitronWP\Plugin;
use CouleurCitron\TarteaucitronWP\Services\Alexa;
use CouleurCitron\TarteaucitronWP\Services\Clicky;
use CouleurCitron\TarteaucitronWP\Services\Disqus;
use CouleurCitron\TarteaucitronWP\Services\Facebook\Comments;
use CouleurCitron\TarteaucitronWP\Services\Facebook\Pixel;
use CouleurCitron\TarteaucitronWP\Services\Google\AdwordsRemarketing;
use CouleurCitron\TarteaucitronWP\Services\Google\AnalyticsTag;
use CouleurCitron\TarteaucitronWP\Services\Google\AnalyticsUniversal;
use CouleurCitron\TarteaucitronWP\Services\Google\JsApi;
use CouleurCitron\TarteaucitronWP\Services\Google\Maps;
use CouleurCitron\TarteaucitronWP\Services\Google\TagManager;
use CouleurCitron\TarteaucitronWP\Services\Iframe;
use CouleurCitron\TarteaucitronWP\Services\Service;
use CouleurCitron\TarteaucitronWP\Services\TimelineJS;
use CouleurCitron\TarteaucitronWP\Services\Typekit;

class ServicesPage extends AdminPage {

    protected $services = [
        JsApi::class,
        Maps::class,
        TagManager::class,
        TimelineJS::class,
        Typekit::class,
        Iframe::class,
        Disqus::class,
        Comments::class,
        Alexa::class,
        Clicky::class,
        AnalyticsTag::class,
        AnalyticsUniversal::class,
        Pixel::class,
        AdwordsRemarketing::class,
    ];

    public function __construct() {
        parent::__construct(
            'Services',
            'Cookie Manager',
            'manage_options',
            'tacwp_services',
            plugins_url( 'assets/cookie.svg', Plugin::PATH ),
            100
        );

        add_action( 'admin_notices', function () {
            $result = filter_input( INPUT_GET, 'result', FILTER_VALIDATE_BOOLEAN );
            if ( get_current_screen()->id !== 'toplevel_page_tacwp_services' || $result === null ) {
                return;
            }
            if ( $result ): ?>
                <div class="notice notice-success">
                    <p>Les services ont bien été sauvegardés.</p>
                </div>
            <?php endif;
        } );
    }

    public function render() {
        $services = collect( $this->services )
            ->map( function ( $class ) {
                return ( new $class() )->toArray();
            } )
            ->groupBy( 'category' );
        ?>
        <div class="wrap">
            <div id="app"
                 data-services="<?= htmlentities( $services->toJson() ) ?>"
                 data-action="<?= admin_url( 'admin-post.php' ) ?>"></div>
        </div>
        <?php
    }

    public function saveServices() {
        $services = collect( $_POST['services'] )->map( function ( $data, $name ) {
            $serviceClass = Service::getClassFromName( $name );

            /** @var Service $service */
            $service         = new $serviceClass();
            $service->active = (bool) $data['active'];
            foreach ( ( isset( $data['options'] ) ? $data['options'] : [] ) as $option => $value ) {
                $service->$option = $value;
            }

            return $service;
        } );

        if ( update_option( 'tacwp_services', $services->toJson() ) ) {
            wp_cache_set( 'tacwp_services', $services );
        }

        wp_redirect( admin_url( 'admin.php?page=tacwp_services&result=1' ) );
        die();
    }

    /**
     * @return array
     */
    public function getServices() {
        $services = wp_cache_get( 'tacwp_services', '', false, $found );
        if ( ! $found ) {
            $services = json_decode( get_option( 'tacwp_services' ), JSON_OBJECT_AS_ARRAY ) ?: [];
            $services = collect( $services )
                ->map( function ( $data, $name ) {
                    $serviceClass = Service::getClassFromName( $name );

                    return new $serviceClass();
                } )
                ->toArray();
            wp_cache_set( 'tacwp_services', $services );
        }

        return $services;
    }

    /**
     * @return array
     */
    public function getActiveServices() {
        return collect( $this->getServices() )->filter->active->toArray();
    }

}