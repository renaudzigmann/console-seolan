<?php

namespace CouleurCitron\TarteaucitronWP\Services\Google;

use CouleurCitron\TarteaucitronWP\Services\Service;

/**
 * Class GoogleAnalyticsUniversal
 * @property string $ua_code
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class AnalyticsUniversal extends Service {

    public $label = 'Google Analytics (universal)';

    public $category = 'Mesure d\'audience';

    public $options = [
        'ua_code' => [
            'label' => 'Code UA',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        tarteaucitron.user.analyticsUa = '{$this->ua_code}';
        (tarteaucitron.job = tarteaucitron.job || []).push('analytics');
        ";
    }
}