<?php

namespace CouleurCitron\TarteaucitronWP\Services\Facebook;

use CouleurCitron\TarteaucitronWP\Services\Service;

/**
 * Class FacebookPixel
 * @property mixed pixel_id
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class Pixel extends Service {

    public $label = 'Facebook Pixel';

    public $category = 'Réseaux Sociaux';

    public $options = [
        'pixel_id' => [
            'label' => 'ID',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        tarteaucitron.user.facebookpixelId = '{$this->pixel_id}';
        (tarteaucitron.job = tarteaucitron.job || []).push('facebookpixel');
        ";
    }
}