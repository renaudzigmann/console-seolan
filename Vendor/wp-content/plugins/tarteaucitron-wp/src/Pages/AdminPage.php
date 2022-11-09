<?php

namespace CouleurCitron\TarteaucitronWP\Pages;

abstract class AdminPage {

    /** @var string */
    protected $page_title;

    /** @var string */
    protected $menu_title;

    /** @var string */
    protected $capability;

    /** @var string */
    protected $slug;

    /** @var string */
    protected $icon;

    /** @var int */
    protected $position;

    /**
     * AdminPage constructor.
     *
     * @param string $page_title
     * @param string $menu_title
     * @param string $capability
     * @param string $slug
     * @param string $icon
     * @param int    $position
     */
    public function __construct( $page_title, $menu_title, $capability, $slug, $icon, $position ) {
        $this->page_title = $page_title;
        $this->menu_title = $menu_title;
        $this->capability = $capability;
        $this->slug       = $slug;
        $this->icon       = $icon;
        $this->position   = $position;
    }

    public function register() {
        add_menu_page(
            $this->page_title,
            $this->menu_title,
            $this->capability,
            $this->slug,
            [ $this, 'render' ],
            $this->icon,
            $this->position
        );
    }

    public abstract function render();

}