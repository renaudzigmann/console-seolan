if( typeof(TZR.Fields) == "undefined" ) {
  TZR.Fields=new Object();
}
if( typeof(TZR.Fields.Password) == "undefined" ) {
  TZR.Fields.Password=new Object();
}
if( typeof(TZR.Fields.Password.pwstrengthInit) == "undefined" ) {
  // Fonction d'initialisation pour les champs password en edition affichant la force
  TZR.Fields.Password.pwstrengthInit = function(varid, strengthOptions){
    var varhiden = varid+"_HID";
    var strengthMess = document.getElementById(strengthOptions.messageId);
    var passField = document.getElementById(varid);
    passField.setAttribute('data-strongEnough', false);
   
    var options = {};
    options.i18n = {
      t: function (key) {
        var result = "";
        switch(key){
          case "weak": result = strengthOptions.forceValues.weak; break;
          case "normal":result = strengthOptions.forceValues.normal; break;
          case "medium":result = strengthOptions.forceValues.medium; break;
          case "strong":result = strengthOptions.forceValues.strong; break;
          case "veryStrong":result = strengthOptions.forceValues.veryStrong; break;
        }
        return result === key ? '' : result;
      }
    };
    options.ui = {
      bootstrap3: true,
      container: "#"+strengthOptions.containerId,
      showVerdictsInsideProgressBar: true,
      viewports: {
        progress: "#"+strengthOptions.strengthId
      },
      scores: [0, 14, 30, 40, 50],
      colorClasses: ["danger", "danger", "warning", "warning", "success", "success"],
    };
    options.rules = {
      activated: {
        wordRepetitions: true
      }
    };
    options.common = {
      // debug: true,
      onScore: function (options, word, totalScoreCalculated){
        strengthMess.innerHTML = "";
        strengthMess.style.display = "none";
        totalScoreModified = totalScoreCalculated;
        // vérification de l'expression reguliére
        let regex = new RegExp(strengthOptions.edit_format);
        if (!regex.test(word)){
          strengthMess.style.display = "";
          strengthMess.innerHTML = strengthOptions.strengthErrorRegex;
          passField.setAttribute('data-strongEnough', false);
          totalScoreModified = 0;
        } else {
          passField.setAttribute('data-strongEnough', true);
        }
        return totalScoreModified;
      }
    };
   
    jQuery("#"+varid).pwstrength(options);
   
    // surcharge de la validation
    if ( typeof(TZR.isPassWordValid_) == "undefined" ){
      TZR.isPassWordValid_ = TZR.isPassWordValid;
      TZR.isPassWordValid = function(val){
        strengthMess.innerHTML = "";
        strengthMess.style.display = "none";
        if (TZR.isPassWordValid_(val)) {
          var strongEnough = passField.getAttribute("data-strongEnough");
          if (strongEnough === "false" || strongEnough === false){
            passField.isValid = false;
            TZR.isFormOk = false;
            strengthMess.style.display = "";
            strengthMess.innerHTML = strengthOptions.strengthErrorRegex;
          } 
        }
        return passField.isValid;
      };
    }
  }

}