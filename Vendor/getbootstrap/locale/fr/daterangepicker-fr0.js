startOfYear = function() {
  var x= Date.parse('today'); x.setMonth(0); x.setDate(1); return x;
};

BSdaterangepicker_locale = {
  "ranges": {
    "Aujourd\'hui": [
      Date.parse('today'),
      Date.parse('today')
    ],
    "7 jours précédents": [
      Date.parse('today-7days'),
      Date.parse('today')
    ],
    "D&eacute;but du mois": [
      Date.parse('today').moveToFirstDayOfMonth(),
      Date.parse('today')
    ],
    "Début de l\'année": [
      startOfYear(),
      Date.parse('today')
    ],
    "Mois précédent": [
      Date.parse('1 month ago').moveToFirstDayOfMonth(),
      Date.parse('1 month ago').moveToLastDayOfMonth()
    ]
  },
  "locale": {
    "format": "DD/MM/YYYY",
    "separator": " - ",
    "applyLabel": "Appliquer",
    "cancelLabel": "Annuler",
    "fromLabel": "De",
    "toLabel": "A",
    "customRangeLabel": "Intervalle",
    "daysOfWeek": [
    "dim.",
    "lun.",
    "mar.",
    "mer.",
    "jeu.",
    "ven.",
    "sam."
    ],
    "monthNames": [
    "janvier",
    "février",
    "mars",
    "avril",
    "mai",
    "juin",
    "juillet",
    "août",
    "septembre",
    "octobre",
    "novembre",
    "décembre"
    ],
    "firstDay": 1
  }
};


