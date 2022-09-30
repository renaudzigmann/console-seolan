/**
 * Action javascript ouvrant une fenêtre modale pour la sélection des signataires du/des document(s) numérique(s).
 *
 * @param moid
 * @param foid
 * @param fname
 * @param fsigned
 */
TZR.openContactListForElectronicSignature = function (moid, foid, fname, fsigned) {
  var url = './admin.php?moid=' + moid + '&foid=' + foid + '&fname=' + fname + '&fsigned=' + fsigned;
  TZR.Dialog.openURL(url, {
    _function: 'browseElectronicSignatureContacts',
    template: 'Core/Module.browseElectronicSignatureContacts.html',
  });
}
