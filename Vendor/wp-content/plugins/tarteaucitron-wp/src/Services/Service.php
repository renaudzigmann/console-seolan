<?php

namespace CouleurCitron\TarteaucitronWP\Services;

abstract class Service implements \JsonSerializable {

    public $name;

    public $label;

    public $category;

    public $options = [];

    public $active = false;

    /**
     * Service constructor.
     */
    public function __construct() {
        $this->name = static::buildName( static::class );

        $data         = $this->getSavedData();
        $this->active = isset( $data['active'] ) ? $data['active'] : false;
        foreach ( ( isset( $data['options'] ) ? $data['options'] : [] ) as $optionKey => $option ) {
            if ( array_key_exists( 'value', $option ) ) {
                $this->$optionKey = $option['value'];
            }
        }
    }

    /**
     * @param string $class
     *
     * @return string
     */
    public static function buildName( $class ) {
        return str_replace( __NAMESPACE__ . '\\', '', $class );
    }

    public static function getClassFromName( $name ) {
        return __NAMESPACE__ . '\\' . $name;
    }

    /**
     * @return string
     */
    public abstract function script();

    /**
     * @return bool
     */
    public function save() {
        $services                = $this->getSavedData();
        $services[ $this->name ] = $this->toArray();

        return update_option( 'tacwp_services', json_encode( $services ) );
    }

    /**
     * @return array
     */
    public function toArray() {
        return [
            'name'     => $this->name,
            'label'    => $this->label,
            'category' => $this->category,
            'options'  => (object) $this->options,
            'active'   => $this->active,
        ];
    }

    /**
     * @return string
     */
    public function toJson() {
        return json_encode( $this->jsonSerialize() );
    }

    /**
     * Specify data which should be serialized to JSON
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize() {
        return $this->toArray();
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this->toJson();
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get( $name ) {
        return isset( $this->options[ $name ]['value'] ) ? $this->options[ $name ]['value'] : null;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set( $name, $value ) {
        $this->options[ $name ]['value'] = $value;
    }

    /**
     * @return array
     */
    protected function getSavedData() {
        $services = json_decode( get_option( 'tacwp_services' ), JSON_OBJECT_AS_ARRAY ) ?: [];

        return isset( $services[ $this->name ] ) ? $services[ $this->name ] : [];
    }
}