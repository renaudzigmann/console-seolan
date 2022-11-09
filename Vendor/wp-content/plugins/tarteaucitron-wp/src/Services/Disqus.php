<?php

namespace CouleurCitron\TarteaucitronWP\Services;

/**
 * Class Disqus
 * @property string shortname
 * @package CouleurCitron\TarteaucitronWP\Services
 */
class Disqus extends Service {

    public $label = 'Disqus';

    public $category = 'Commentaire';

    public $options = [
        'shortname' => [
            'label' => 'ID Site (shortname)',
        ],
    ];

    /**
     * @return string
     */
    public function script() {
        return "
        var disqus_shortname = '{$this->shortname}';
        /* * * DON'T EDIT BELOW THIS LINE * * */
        (function() {
            var dsq = document.createElement('script');
            dsq.type = 'text/javascript';
            dsq.async = true;
            dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
            (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
        })();
        ";
    }
}