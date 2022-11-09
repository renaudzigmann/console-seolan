<?php

namespace CouleurCitron\TarteaucitronWP\Services;

/**
 * Class Typekit
 * @property string typekit_id
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class Typekit extends Service {

    public $label = 'Adobe Typekit';

    public $category = 'APIs';

    public $options = [
        'typekit_id' => [
            'label' => 'ID Typekit',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        tarteaucitron.user.typekitId = '{$this->typekit_id}';
        (tarteaucitron.job = tarteaucitron.job || []).push('typekit');
        ";
    }
}