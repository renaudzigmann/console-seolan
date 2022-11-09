<?php

namespace CouleurCitron\TarteaucitronWP\Services\Google;

use CouleurCitron\TarteaucitronWP\Services\Service;

/**
 * Class GoogleMaps
 * @property string api_key
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class Maps extends Service {

    public $label = 'Google Maps';

    public $category = 'APIs';

    public $options = [
        'api_key' => [
            'label' => 'Clé API',
        ],
    ];

    public function script() {
        return "
        tarteaucitron.user.googlemapsKey = '{$this->api_key}';
        (tarteaucitron.job = tarteaucitron.job || []).push('googlemaps');
        ";
    }

}