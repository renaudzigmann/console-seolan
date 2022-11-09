<?php

namespace CouleurCitron\TarteaucitronWP\Pages;

class SettingsPage extends AdminSubPage {

    protected $defaultSettings = [
        'privacyUrl'              => '',
        'hashtag'                 => '#tarteaucitron',
        'cookieName'              => 'tartaucitron',
        'orientation'             => 'top',
        'showAlertSmall'          => true,
        'cookieslist'             => true,
        'adblocker'               => false,
        'AcceptAllCta'            => true,
        'highPrivacy'             => false,
        'handleBrowserDNTRequest' => false,
        'removeCredit'            => false,
        'moreInfoLink'            => true,
        'useExternalCss'          => false,
        'cookieDomain'            => '',
    ];

    public function __construct() {
        parent::__construct(
            'tacwp_services',
            'Paramètres',
            'Paramètres',
            'manage_options',
            'tacwp_settings'
        );

        add_action( 'admin_notices', function () {
            $result = filter_input( INPUT_GET, 'result', FILTER_VALIDATE_BOOLEAN );
            if ( get_current_screen()->id !== 'cookie-manager_page_tacwp_settings' || $result === null ) {
                return;
            }
            if ( $result ): ?>
                <div class="notice notice-success">
                    <p>Les paramètres ont bien été sauvegardés.</p>
                </div>
            <?php endif;
        } );
    }

    public function render() {
        $settings = $this->getSettings();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Paramètres</h1>
            <form action="<?= admin_url( 'admin-post.php' ) ?>" method="post">
                <input type="hidden" name="action" value="tacwp_save_settings">
                <table class="form-table">
                    <tr>
                        <th>
                            <label for="privacyUrl">URL de la page de confidentialité</label>
                        </th>
                        <td>
                            <input type="url" name="privacyUrl" id="privacyUrl" class="regular-text"
                                   value="<?= $settings->get( 'privacyUrl' ) ?>">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <hr>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="hashtag">Hashtag</label>
                        </th>
                        <td>
                            <input type="text" name="hashtag" id="hashtag" class="regular-text"
                                   value="<?= $settings->get( 'hashtag' ) ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="cookieName">Nom du cookie</label>
                        </th>
                        <td>
                            <input type="text" name="cookieName" id="cookieName" class="regular-text"
                                   value="<?= $settings->get( 'cookieName' ) ?>">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <hr>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="orientation">Position</label>
                        </th>
                        <td>
                            <select name="orientation" id="orientation" class="regular-text">
                                <option value="top" <?php selected( $settings->get( 'orientation' ), 'top' ) ?>>
                                    Haut
                                </option>
                                <option value="bottom" <?php selected( $settings->get( 'orientation' ), 'bottom' ) ?>>
                                    Bas
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="showAlertSmall">Afficher le petit bandeau en bas à droite</label>
                        </th>
                        <td>
                            <input type="hidden" name="showAlertSmall" value="0">
                            <input type="checkbox" name="showAlertSmall" id="showAlertSmall" value="1"
                                <?php checked( $settings->get( 'showAlertSmall' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="cookieslist">Afficher la liste des cookies installés</label>
                        </th>
                        <td>
                            <input type="hidden" name="cookieslist" value="0">
                            <input type="checkbox" name="cookieslist" id="cookieslist" value="1"
                                <?php checked( $settings->get( 'cookieslist' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <hr>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="adblocker">Afficher un message si un adblocker est détecté</label>
                        </th>
                        <td>
                            <input type="hidden" name="adblocker" value="0">
                            <input type="checkbox" name="adblocker" id="adblocker" value="1"
                                <?php checked( $settings->get( 'adblocker' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="AcceptAllCta">Afficher un bouton "Accepter tout" lorsque que le consentement
                                implicite est désactivé</label>
                        </th>
                        <td>
                            <input type="hidden" name="AcceptAllCta" value="0">
                            <input type="checkbox" name="AcceptAllCta" id="AcceptAllCta" value="1"
                                <?php checked( $settings->get( 'AcceptAllCta' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="highPrivacy">Désactiver le consentement implicite</label>
                        </th>
                        <td>
                            <input type="hidden" name="highPrivacy" value="0">
                            <input type="checkbox" name="highPrivacy" id="highPrivacy" value="1"
                                <?php checked( $settings->get( 'highPrivacy' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="handleBrowserDNTRequest">
                                Prendre en compte <a href="https://fr.wikipedia.org/wiki/Do_Not_Track" target="_blank">
                                    <abbr title="Do Not Track">DNT</abbr></a>
                            </label>
                        </th>
                        <td>
                            <input type="hidden" name="handleBrowserDNTRequest" value="0">
                            <input type="checkbox" name="handleBrowserDNTRequest" id="handleBrowserDNTRequest" value="1"
                                <?php checked( $settings->get( 'handleBrowserDNTRequest' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <hr>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="removeCredit">Supprimer le lien vers la source</label>
                        </th>
                        <td>
                            <input type="hidden" name="removeCredit" value="0">
                            <input type="checkbox" name="removeCredit" id="removeCredit" value="1"
                                <?php checked( $settings->get( 'removeCredit' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="moreInfoLink">Afficher le lien "Plus d'informations"</label>
                        </th>
                        <td>
                            <input type="hidden" name="moreInfoLink" value="0">
                            <input type="checkbox" name="moreInfoLink" id="moreInfoLink" value="1"
                                <?php checked( $settings->get( 'moreInfoLink' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="useExternalCss">Utiliser une feuille de style externe</label>
                        </th>
                        <td>
                            <input type="hidden" name="useExternalCss" value="0">
                            <input type="checkbox" name="useExternalCss" id="useExternalCss" value="1"
                                <?php checked( $settings->get( 'useExternalCss' ) ) ?>>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="cookieDomain">Domaine du cookie</label>
                        </th>
                        <td>
                            <input type="text" name="cookieDomain" id="cookieDomain" class="regular-text"
                                   value="<?= $settings->get( 'cookieDomain' ) ?>">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Enregistrer</button>
                </p>
            </form>
        </div>
        <?php
    }

    public function saveSettings() {
        $settings = filter_input_array( INPUT_POST, [
            'privacyUrl'              => FILTER_SANITIZE_URL,
            'hashtag'                 => FILTER_SANITIZE_STRING,
            'cookieName'              => FILTER_SANITIZE_STRING,
            'orientation'             => FILTER_SANITIZE_STRING,
            'showAlertSmall'          => FILTER_VALIDATE_BOOLEAN,
            'cookieslist'             => FILTER_VALIDATE_BOOLEAN,
            'adblocker'               => FILTER_VALIDATE_BOOLEAN,
            'AcceptAllCta'            => FILTER_VALIDATE_BOOLEAN,
            'highPrivacy'             => FILTER_VALIDATE_BOOLEAN,
            'handleBrowserDNTRequest' => FILTER_VALIDATE_BOOLEAN,
            'removeCredit'            => FILTER_VALIDATE_BOOLEAN,
            'moreInfoLink'            => FILTER_VALIDATE_BOOLEAN,
            'useExternalCss'          => FILTER_VALIDATE_BOOLEAN,
            'cookieDomain'            => FILTER_SANITIZE_STRING,
        ] );

        foreach ( $settings as $key => $value ) {
            if ( $value === null ) {
                $settings[ $key ] = $this->defaultSettings[ $key ];
            }
        }

        if ( update_option( 'tacwp_settings', json_encode( $settings ) ) ) {
            // Update cache only if data has changed
            wp_cache_set( 'tacwp_settings', $settings );
        }

        wp_redirect( admin_url( 'admin.php?page=tacwp_settings&result=1' ) );
        die();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getSettings() {
        $settings = wp_cache_get( 'tacwp_settings', '', false, $found );
        if ( ! $found ) {
            $settings = json_decode( get_option( 'tacwp_settings' ), JSON_OBJECT_AS_ARRAY ) ?: $this->defaultSettings;
            wp_cache_set( 'tacwp_settings', $settings );
        }

        return collect( $settings );
    }
}