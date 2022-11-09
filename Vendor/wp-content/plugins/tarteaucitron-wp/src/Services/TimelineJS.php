<?php

namespace CouleurCitron\TarteaucitronWP\Services;

class TimelineJS extends Service {

    public $label = 'Timeline JS';

    public $category = 'APIs';

    /**
     * @return string
     */
    public function script() {
        return '(tarteaucitron.job = tarteaucitron.job || []).push(\'timelinejs\');';
    }
}