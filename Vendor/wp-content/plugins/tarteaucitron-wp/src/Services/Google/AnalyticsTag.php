<?php

namespace CouleurCitron\TarteaucitronWP\Services\Google;

use CouleurCitron\TarteaucitronWP\Services\Service;

/**
 * Class AnalyticsTag
 * @property string $ua_code
 * @package CouleurCitron\TarteaucitronWP\Services\Google
 */
class AnalyticsTag extends Service {

    public $label = 'Google Analytics (gtag.js)';

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
        tarteaucitron.user.gtagUa = '{$this->ua_code}';
        (tarteaucitron.job = tarteaucitron.job || []).push('gtag');
        ";
    }
}