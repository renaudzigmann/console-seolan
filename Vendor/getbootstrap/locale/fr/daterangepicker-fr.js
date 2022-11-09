startOfYear = function() {
  var x= Date.parse('today'); x.setMonth(0); x.setDate(1); return x;
};

BSdaterangepicker_locale = {
  "ranges": {
    "Aujourd\'hui": [
      moment(),
      moment()
    ],
    "7 jours précédents": [
      moment().subtract('days', 6),
      moment()
    ],
    "D&eacute;but du mois": [
      moment().startOf('month'),
      moment()
    ],
    "Début de l\'année": [
      moment().startOf('year'),
      moment()
    ],
    "Mois précédent": [
      moment().subtract('month', 1).startOf('month'),
      moment().subtract('month', 1).endOf('month')
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


