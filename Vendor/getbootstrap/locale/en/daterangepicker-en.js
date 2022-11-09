startOfYear = function() {
  var x= Date.parse('today'); x.setMonth(0); x.setDate(1); return x;
};

BSdaterangepicker_locale = {
  "ranges": {
    "Today": [
      Date.parse('today'),
      Date.parse('today')
    ],
    "Last 7 Days": [
      Date.parse('today-7days'),
      Date.parse('today')
    ],
    "Month to date": [
      Date.parse('today').moveToFirstDayOfMonth(),
      Date.parse('today')
    ],
    "Year to date": [
      startOfYear(),
      Date.parse('today')
    ],
    "The previous Month": [
      Date.parse('1 month ago').moveToFirstDayOfMonth(),
      Date.parse('1 month ago').moveToLastDayOfMonth()
    ]
  },
  "locale": {
    "format": "MM/DD/YYYY",
    "separator": " - ",
    "applyLabel": "Apply",
    "cancelLabel": "Cancel",
    "fromLabel": "From",
    "toLabel": "To",
    "customRangeLabel": "Custom",
    "daysOfWeek": [
    "Su",
    "Mo",
    "Tu",
    "We",
    "Th",
    "Fr",
    "Sa"
    ],
    "monthNames": [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December"
    ],
    "firstDay": 1
  }
};




