<?php

namespace CouleurCitron\TarteaucitronWP\Services\Facebook;

use CouleurCitron\TarteaucitronWP\Services\Service;

class Comments extends Service {

    public $label = 'Facebook Comments';

    public $category = 'Commentaire';

    /**
     * @return string
     */
    public function script() {
        return "(tarteaucitron.job = tarteaucitron.job || []).push('facebookcomment');";
    }
}