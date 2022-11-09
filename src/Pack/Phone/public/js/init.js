TZR.phoneFormat = function(e) {
  var input = jQuery(e.data.input);
  var hid = jQuery(e.data.hid);
  var iti = e.data.iti;
  var keyCode = e.keyCode || e.which || 48;
  if (hid && input && keyCode >= 48 && (!e.ctrlKey && !e.shiftKey && !e.altKey && !e.metaKey)) {
    if (iti && iti.isValidNumber) {
      if (!iti.isValidNumber()) {
        // Pour mettre en forme un numéro on est obligé d'avoir un numéro valide.
        // Donc, si le numéro n'est pas valide, on ve le compléter avec les chiffres du numéro exemple (utilisé par le
        // placeholder), jusqu'à ce qu'on aie un numéro valide ou trop gros (errorCode === 3). Ensuite on revient
        // au numéro précédent si jamais il est trop gros, puis on le met en forme, et enfin on supprime tous
        // les chiffres ajoutés à partir du numéro exemple.
        // ex : si l'utilisateur tape 0612 on va le compléter jusqu'à avoir 0612345678 puis le mettre en
        // forme 06 12 34 56 78 puis supprimer les chiffres ajoutés 06 12
        var country = iti.getSelectedCountryData();
        var exampleNumber = intlTelInputUtils.getExampleNumber(country.iso2, true, intlTelInputUtils.numberType["MOBILE"]);
        exampleNumber = exampleNumber.replace(/[^0-9+]+/g, '');
        var countNumber = 0;
        var val = prevVal = input.val().replace(/[^0-9+]+/g, '');
        var errorCode = iti.getValidationError();
        while(!iti.isValidNumber() && errorCode >= 0 && errorCode !== 3) {
          prevVal = val;
          if(val.length >= exampleNumber.length || val.substr(0, 1) === '+' || val.substr(0, 2) === '00') {
            val += '0';
          }
          else {
            val += exampleNumber[val.length];
          }
          countNumber++;
          input.val(val);
          errorCode = iti.getValidationError();
          if(val.length>15) break;
        }
        if(countNumber > 0) {
          // On revient au numéro précédent si il est trop gros
          if(!iti.isValidNumber() || countNumber>input.val().length){
            countNumber--;
            input.val(prevVal);
          }
          // On met en forme
          iti.setNumber(iti.getNumber());
          // On supprime ce qu'on a ajouté en plus
          var rg = new RegExp('([0-9+][^0-9+]*){' + countNumber + '}$');
          input.val(input.val().replace(rg, '').trim());
        }
      }
      else {
        // Sinon si on a un numéro valide on met en forme directement
        iti.setNumber(iti.getNumber());
      }
      hid.val(iti.getNumber());
    }
    else {
      hid.val(input.val());
    }
  }
};