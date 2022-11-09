<?php

namespace CouleurCitron\TarteaucitronWP\Services\Google;

use CouleurCitron\TarteaucitronWP\Services\Service;

/**
 * Class GoogleTagManager
 * @property string google_tag_manager_id
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class TagManager extends Service {

    public $label = 'Google Tag Manager';

    public $category = 'APIs';

    public $options = [
        'google_tag_manager_id' => [
            'label' => 'ID GTM',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        tarteaucitron.user.googletagmanagerId = '{$this->google_tag_manager_id}';
        (tarteaucitron.job = tarteaucitron.job || []).push('googletagmanager');
        ";
    }
}