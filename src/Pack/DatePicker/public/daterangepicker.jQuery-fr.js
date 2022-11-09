daterangepicker_locale = {
    presetRanges: [
        {text: 'Aujourd\'hui', dateStart: 'today', dateEnd: 'today' },
        {text: 'Demain', dateStart: 'tomorrow', dateEnd: 'tomorrow' },
        {text: '7 jours précédents', dateStart: 'today-7days', dateEnd: 'today' },
        {text: 'D&eacute;but du mois', dateStart: function(){ return Date.parse('today').moveToFirstDayOfMonth();  }, dateEnd: 'today' },
        {text: 'Début de l\'année', dateStart: function(){ var x= Date.parse('today'); x.setMonth(0); x.setDate(1); return x; }, dateEnd: 'today' },
        //extras:
        {text: 'Mois précédent', dateStart: function(){ return Date.parse('1 month ago').moveToFirstDayOfMonth();  }, dateEnd: function(){ return Date.parse('1 month ago').moveToLastDayOfMonth();  } }
    ],
    //presetRanges: array of objects for each menu preset.
    //Each obj must have text, dateStart, dateEnd. dateStart, dateEnd accept date.js string or a function which returns a date object
    presets: {
        specificDate: 'Date spécifique',
        allDatesBefore: 'Toutes les dates avant',
        allDatesAfter: 'Toutes les dates après',
        dateRange: 'Intervalle'
    },
    rangeStartTitle: 'Date début',
    rangeEndTitle: 'Date de fin',
    nextLinkText: 'Suivant',
    prevLinkText: 'Précédent',
    doneButtonText: 'Fermer',
    rangeSplitter: '<>', //string to use between dates in single input
    dateFormat: 'dd/mm/yy', // date formatting. Available formats: http://docs.jquery.com/UI/Datepicker/%24.datepicker.formatDate
    closeOnSelect: true, //if a complete selection is made, close the menu
    arrows: false,
    appendTo: 'body',
    onClose: function(){},
    onOpen: function(){},
    onChange: function(){},
    datepickerOptions: null //object containing native UI datepicker API options
}

// datejs
Date.CultureInfo = {
        /* Culture Name */
    name: "fr-FR",
    englishName: "French (France)",
    nativeName: "français (France)",

    /* Day Name Strings */
    dayNames: ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"],
    abbreviatedDayNames: ["dim.", "lun.", "mar.", "mer.", "jeu.", "ven.", "sam."],
    shortestDayNames: ["di", "lu", "ma", "me", "je", "ve", "sa"],
    firstLetterDayNames: ["d", "l", "m", "m", "j", "v", "s"],

    /* Month Name Strings */
    monthNames: ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"],
    abbreviatedMonthNames: ["janv.", "févr.", "mars", "avr.", "mai", "juin", "juil.", "août", "sept.", "oct.", "nov.", "déc."],

        /* AM/PM Designators */
    amDesignator: "",
    pmDesignator: "",

    firstDayOfWeek: 1,
    twoDigitYearMax: 2029,

    /**
     * The dateElementOrder is based on the order of the
     * format specifiers in the formatPatterns.DatePattern.
     *
     * Example:
     <pre>
     shortDatePattern    dateElementOrder
     ------------------  ----------------
     "M/d/yyyy"          "mdy"
     "dd/MM/yyyy"        "dmy"
     "yyyy-MM-dd"        "ymd"
     </pre>
     *
     * The correct dateElementOrder is required by the parser to
     * determine the expected order of the date elements in the
     * string being parsed.
     */
    dateElementOrder: "dmy",

    /* Standard date and time format patterns */
    formatPatterns: {
        shortDate: "dd/MM/yyyy",
        longDate: "dddd d MMMM yyyy",
        shortTime: "HH:mm",
        longTime: "HH:mm:ss",
        fullDateTime: "dddd d MMMM yyyy HH:mm:ss",
        sortableDateTime: "yyyy-MM-ddTHH:mm:ss",
        universalSortableDateTime: "yyyy-MM-dd HH:mm:ssZ",
        rfc1123: "ddd, dd MMM yyyy HH:mm:ss GMT",
        monthDay: "d MMMM",
        yearMonth: "MMMM yyyy"
    },

    /**
     * NOTE: If a string format is not parsing correctly, but
     * you would expect it parse, the problem likely lies below.
     *
     * The following regex patterns control most of the string matching
     * within the parser.
     *
     * The Month name and Day name patterns were automatically generated
     * and in general should be (mostly) correct.
     *
     * Beyond the month and day name patterns are natural language strings.
     * Example: "next", "today", "months"
     *
     * These natural language string may NOT be correct for this culture.
     * If they are not correct, please translate and edit this file
     * providing the correct regular expression pattern.
     *
     * If you modify this file, please post your revised CultureInfo file
     * to the Datejs Forum located at http://www.datejs.com/forums/.
     *
     * Please mark the subject of the post with [CultureInfo]. Example:
     *    Subject: [CultureInfo] Translated "da-DK" Danish(Denmark)
     *
     * We will add the modified patterns to the master source files.
     *
     * As well, please review the list of "Future Strings" section below.
     */
    regexPatterns: {
        jan: /^janv(.(ier)?)?/i,
        feb: /^févr(.(ier)?)?/i,
        mar: /^mars/i,
        apr: /^avr(.(il)?)?/i,
        may: /^mai/i,
        jun: /^juin/i,
        jul: /^juil(.(let)?)?/i,
        aug: /^août/i,
        sep: /^sept(.(embre)?)?/i,
        oct: /^oct(.(obre)?)?/i,
        nov: /^nov(.(embre)?)?/i,
        dec: /^déc(.(embre)?)?/i,

        sun: /^di(m(.(anche)?)?)?/i,
        mon: /^lu(n(.(di)?)?)?/i,
        tue: /^ma(r(.(di)?)?)?/i,
        wed: /^me(r(.(credi)?)?)?/i,
        thu: /^je(u(.(di)?)?)?/i,
        fri: /^ve(n(.(dredi)?)?)?/i,
        sat: /^sa(m(.(edi)?)?)?/i,

        future: /^next/i,
        past: /^last|past|prev(ious)?/i,
        add: /^(\+|aft(er)?|from|hence)/i,
        subtract: /^(\-|bef(ore)?|ago)/i,

        yesterday: /^yes(terday)?/i,
        today: /^t(od(ay)?)?/i,
        tomorrow: /^tom(orrow)?/i,
        now: /^n(ow)?/i,

        millisecond: /^ms|milli(second)?s?/i,
        second: /^sec(ond)?s?/i,
        minute: /^mn|min(ute)?s?/i,
                hour: /^h(our)?s?/i,
                week: /^w(eek)?s?/i,
        month: /^m(onth)?s?/i,
        day: /^d(ay)?s?/i,
        year: /^y(ear)?s?/i,

        shortMeridian: /^(a|p)/i,
        longMeridian: /^(a\.?m?\.?|p\.?m?\.?)/i,
        timezone: /^((e(s|d)t|c(s|d)t|m(s|d)t|p(s|d)t)|((gmt)?\s*(\+|\-)\s*\d\d\d\d?)|gmt|utc)/i,
        ordinalSuffix: /^\s*(st|nd|rd|th)/i,
        timeContext: /^\s*(\:|a(?!u|p)|p)/i
    },

        timezones: [{name:"UTC", offset:"-000"}, {name:"GMT", offset:"-000"}, {name:"EST", offset:"-0500"}, {name:"EDT", offset:"-0400"}, {name:"CST", offset:"-0600"}, {name:"CDT", offset:"-0500"}, {name:"MST", offset:"-0700"}, {name:"MDT", offset:"-0600"}, {name:"PST", offset:"-0800"}, {name:"PDT", offset:"-0700"}]
};

