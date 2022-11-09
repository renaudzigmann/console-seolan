/**
 * XSiteApp
 * Choix du sous-site administré
 * @constructor
 */
TZR.XSiteApp = function() {};

/**
 * Ecouter les choix de sous-site à administrer.
 * @param select: Element HTML select permettant le choix de l'APP
 */
TZR.XSiteApp.prototype.listenChoices = function(select) {
    var that = this;
    var $select = jQuery(select);

    $select.change(function(){
        var $option_selected = jQuery(jQuery('option:selected', $select));
        var topic_manager_moid = $option_selected.data('xmodinfotree_moid');
        that.setTopicManagerLink(topic_manager_moid);
        that.setForcedApp($select.val(), function(){
            that.refreshContents();
        });
    });

    // On affiche dés le départ un lien vers le gestionnaire de rubrique
    var $option_selected = jQuery(jQuery('option:selected', $select));
    var topic_manager_moid = $option_selected.data('xmodinfotree_moid');
    that.setTopicManagerLink(topic_manager_moid);
};

/**
 * Notifie à Seolan le choix de l'app.
 * @param app_koid: KOID de l'app choisis.
 * @param callback: callback executé après l'appel ajax et si il as réussis
 */
TZR.XSiteApp.prototype.setForcedApp = function (app_koid, callback) {
    jQuery.ajax({
        url: "admin.php?class=\Seolan\Module\Application\Application&function=setForcedApps&apps_koids[]="+app_koid,
        context: document.body
    }).done(callback);
};

/**
 * Rafraichissement des différentes zones rafraichissables
 */
TZR.XSiteApp.prototype.refreshContents = function () {
    jQuery('a.cv8-refresh').trigger('click');
};

/**
 * Permet l'affichage d'un lien vers le gestionnaire de rubrique correspondant à l'app choisis.
 * @param topic_manager_moid_to_show: moid du gestionnaire de rubrique correspondant au sous-site choisis.
 */
TZR.XSiteApp.prototype.setTopicManagerLink = function (topic_manager_moid_to_show) {
    jQuery('.cv8-subsite_current_topic_manager').remove();
    var module_link = "/csx/scripts/admin.php?_bdx=5_3&moid="+topic_manager_moid_to_show+"&function=home&tplentry=mit&template=Module/InfoTree.index.html";

    var topic_manager_selected_menu_element =
        '<span class="cv8-subsite_current_topic_manager">' +
            '<li class="line">&nbsp;</li>' +
              '<li id="node_CS8_sub_site_current_topic_manager_'+topic_manager_moid_to_show+'" data-oid="'+topic_manager_moid_to_show+'" class="doc">' +
                '<div style="position:absolute;">' +
                  '<div class="ico">' +
                  '</div>' +
                '</div>' +
                '<a href="'+module_link+'" onclick="home_viewmodule(this); return false;">Gestion des pages</a>' +
            '</li>' +
            '<li class="line-last"></li>' +
        '</span>'
    ;

    jQuery(topic_manager_selected_menu_element).insertAfter(jQuery('span#cv8-infotreemenu'));
};
