<?php

namespace CouleurCitron\TarteaucitronWP\Services;

/**
 * Class Clicky
 * @property string clicky_id
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class Clicky extends Service {

    public $label = 'Click';

    public $category = 'Mesure d\'audience';

    public $options = [
        'clicky_id' => [
            'label' => 'ID',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        tarteaucitron.user.clickyId = '{$this->clicky_id}';
        tarteaucitron.user.clickyMore = function () { /* add here your optionnal clicky function */ };
        (tarteaucitron.job = tarteaucitron.job || []).push('clicky');
        ";
    }
}