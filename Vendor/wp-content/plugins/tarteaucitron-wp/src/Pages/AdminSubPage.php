<?php

namespace CouleurCitron\TarteaucitronWP\Pages;

abstract class AdminSubPage {

    /** @var string */
    protected $parent_slug;

    /** @var string */
    protected $page_title;

    /** @var string */
    protected $menu_title;

    /** @var string */
    protected $capability;

    /** @var string */
    protected $slug;

    /**
     * AdminSubPage constructor.
     *
     * @param string $parent_slug
     * @param string $page_title
     * @param string $menu_title
     * @param string $capability
     * @param string $slug
     */
    public function __construct( $parent_slug, $page_title, $menu_title, $capability, $slug ) {
        $this->parent_slug = $parent_slug;
        $this->page_title  = $page_title;
        $this->menu_title  = $menu_title;
        $this->capability  = $capability;
        $this->slug        = $slug;
    }

    public function register() {
        add_submenu_page(
            $this->parent_slug,
            $this->page_title,
            $this->menu_title,
            $this->capability,
            $this->slug,
            [ $this, 'render' ]
        );
    }

    public abstract function render();
}