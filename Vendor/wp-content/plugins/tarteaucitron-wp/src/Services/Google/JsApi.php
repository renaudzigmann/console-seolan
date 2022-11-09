<?php

namespace CouleurCitron\TarteaucitronWP\Services\Google;

use CouleurCitron\TarteaucitronWP\Services\Service;

class JsApi extends Service {

    public $label = 'Google JsApi';

    public $category = 'APIs';

    public function script() {
        return "(tarteaucitron.job = tarteaucitron.job || []).push('jsapi');";
    }

}