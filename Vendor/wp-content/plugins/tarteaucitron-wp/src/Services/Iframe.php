<?php

namespace CouleurCitron\TarteaucitronWP\Services;

/**
 * Class Iframe
 * @property string iframe_name
 * @property string url
 * @property string cookies
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class Iframe extends Service {

    public $label = 'Iframe';

    public $category = 'Autre';

    public $options = [
        'iframe_name' => [
            'label' => 'Nom',
        ],
        'url'         => [
            'label' => 'URL',
        ],
        'cookies'     => [
            'label'       => 'Cookies',
            'placeholder' => 'Séparer par des virgules',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        $cookies = json_encode( explode( ',', $this->cookies ) );

        return "
        var tarteaucitron_interval = setInterval(function() {
            if (typeof tarteaucitron.services.iframe.name == 'undefined') {
                return;
            }
            clearInterval(tarteaucitron_interval);
            
            tarteaucitron.services.iframe.name = '{$this->iframe_name}';
            tarteaucitron.services.iframe.uri = '{$this->url}';
            tarteaucitron.services.iframe.cookies = {$cookies};
        }, 10);
        (tarteaucitron.job = tarteaucitron.job || []).push('iframe');
        ";
    }
}