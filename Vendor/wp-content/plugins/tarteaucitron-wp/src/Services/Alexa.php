<?php

namespace CouleurCitron\TarteaucitronWP\Services;

/**
 * Class Alexa
 * @property string account_id
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class Alexa extends Service {

    public $label = 'Alexa';

    public $category = 'Mesure d\'audience';

    public $options = [
        'account_id' => [
            'label' => 'ID Compte'
        ]
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        tarteaucitron.user.alexaAccountID = '{$this->account_id}';
        (tarteaucitron.job = tarteaucitron.job || []).push('alexa');        
        ";
    }
}