<?php

namespace CouleurCitron\TarteaucitronWP\Services\Google;

use CouleurCitron\TarteaucitronWP\Services\Service;

/**
 * Class AdwordsRemarketing
 * @property string $adwords_id
 * @package CouleurCitron\TarteaucitronWP\Services\Google
 */
class AdwordsRemarketing extends Service {

    public $label = 'Google Adwords (remarketing)';

    public $category = 'Régie publicitaire';

    public $options = [
        'adwords_id' => [
            'label' => 'ID',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        tarteaucitron.user.adwordsremarketingId = '{$this->adwords_id}';
        (tarteaucitron.job = tarteaucitron.job || []).push('googleadwordsremarketing');
        ";
    }
}