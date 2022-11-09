/*jQuery(function($){*/
/*jQuery(document).ready(function($) {*/
(function() {
  $(document).ready(function() {
    jQuery.ajaxSettings.cache=false;
    //Menu moved to genericX
  });

  $(document).ready(function() {
    var touch;
    $(".box .box-remove").live("click", function(e) {
      $(this).parents(".box").first().remove();
      e.preventDefault();
      return false;
    });
    $(".box .box-collapse").live("click", function(e) {
      var box;
      box = $(this).parents(".box").first();
      box.toggleClass("box-collapsed");
      e.preventDefault();
      return false;
    });
    if (jQuery().pwstrength) {
      $('.pwstrength').pwstrength({
        showVerdicts: false
      });
    }
    $(".check-all").live("click", function(e) {
      return $(this).parents("table:eq(0)").find(".only-checkbox :checkbox").attr("checked", this.checked);
    });
    if (jQuery().tabdrop) {
      $('.nav-responsive.nav-pills, .nav-responsive.nav-tabs').tabdrop();
    }
    
    if (jQuery().wysihtml5) {
      $('.wysihtml5').wysihtml5();
    }
    if (jQuery().nestable) {
      $('.dd-nestable').nestable();
    }
    if (!$("body").hasClass("fixed-header")) {
      if (jQuery().affix) {
        $('#main-nav.main-nav-fixed').affix({
          offset: 40
        });
      }
    }
    touch = false;
    if (window.Modernizr) {
      touch = Modernizr.touch;
    }
    if (!touch) {
      $("body").on("mouseenter", ".has-popover", function() {
        var el;
        el = $(this);
        if (el.data("popover") === undefined) {
          el.popover({
            placement: el.data("placement") || "top",
            container: "body"
          });
        }
        return el.popover("show");
      });
      $("body").on("mouseleave", ".has-popover", function() {
        return $(this).popover("hide");
      });
    }
    touch = false;
    if (window.Modernizr) {
      touch = Modernizr.touch;
    }
    if (!touch) {
      $("body").on("mouseenter", ".has-tooltip", function() {
        var el;
        el = $(this);
        if (el.data("tooltip") === undefined) {
          el.tooltip({
            placement: el.data("placement") || "top",
            container: "body"
          });
        }
        return el.tooltip("show");
      });
      $("body").on("mouseleave", ".has-tooltip", function() {
        return $(this).tooltip("hide");
      });
    }
    if (window.Modernizr && Modernizr.svg === false) {
      $("img[src*=\"svg\"]").attr("src", function() {
        return $(this).attr("src").replace(".svg", ".png");
      });
    }
    if (jQuery().colorpicker) {
      $(".colorpicker-hex").colorpicker({
        format: "hex"
      });
      $(".colorpicker-rgb").colorpicker({
        format: "rgb"
      });
    }
    /*
    if (jQuery().datetimepicker) {
      $(".datetimepicker").datetimepicker();
      $(".datepicker").datetimepicker({
        pickTime: false
      });
      $(".timepicker").datetimepicker({
        pickDate: false
      });
    }
    */
    if (jQuery().bootstrapFileInput) {
      $('input[type=file]').bootstrapFileInput();
    }
    if (window.Modernizr) {
      if (!Modernizr.input.placeholder) {
        $("[placeholder]").focus(function() {
          var input;
          input = $(this);
          if (input.val() === input.attr("placeholder")) {
            input.val("");
            return input.removeClass("placeholder");
          }
        }).blur(function() {
          var input;
          input = $(this);
          if (input.val() === "" || input.val() === input.attr("placeholder")) {
            input.addClass("placeholder");
            return input.val(input.attr("placeholder"));
          }
        }).blur();
        return $("[placeholder]").parents("form").submit(function() {
          return $(this).find("[placeholder]").each(function() {
            var input;
            input = $(this);
            if (input.val() === input.attr("placeholder")) {
              return input.val("");
            }
          });
        });
      }
    }
  });
}).call(this);

jQuery(document).ready(function($) {
  
  setTimeAgo();
  setScrollable();
  setSortable();
  setSelect2();
  setAutoSize();
  setCharCounter();
  setMaxLength();
  setValidateForm();
  //setDataTable();
  
  //setSortable($(".sortable"));
  //setDataTable($(".data-table"));
  //setDataTable($(".data-table-column-filter"));

  function setMaxLength(selector) {
    if (selector == null) {
      selector = $(".char-max-length");
    }
    if (jQuery().maxlength) {
      return selector.maxlength();
    }
  };

  function setCharCounter(selector) {
    if (selector == null) {
      selector = $(".char-counter");
    }
    if (jQuery().charCount) {
      return selector.charCount({
        allowed: selector.data("char-allowed"),
        warning: selector.data("char-warning"),
        cssWarning: "text-warning",
        cssExceeded: "text-error"
      });
    }
  };

  function setAutoSize(selector) {
    if (selector == null) {
      selector = $(".autosize");
    }
    if (jQuery().autosize) {
      return selector.autosize();
    }
  };

  function setTimeAgo(selector) {
    if (selector == null) {
      selector = $(".timeago");
    }
    if (jQuery().timeago) {
      jQuery.timeago.settings.allowFuture = true;
      jQuery.timeago.settings.refreshMillis = 60000;
      selector.timeago();
      return selector.addClass("in");
    }
  };

  function setScrollable(selector) {
    if (selector == null) {
      selector = $(".scrollable");
    }
    if (jQuery().slimScroll) {
      return selector.each(function(i, elem) {
        return $(elem).slimScroll({
          height: $(elem).data("scrollable-height"),
          start: $(elem).data("scrollable-start") || "top"
        });
      });
    }
  };

  function setSortable(selector) {
    if (selector == null) {
      selector = $(".sortable");
    }
    if (selector) {
      return selector.each(function(i, elem) {
        return $(elem).sortable({
          axis: selector.data("sortable-axis"),
          connectWith: selector.data("sortable-connect")
        });
      });
    }
  };

  function setSelect2(selector) {
    if (selector == null) {
      selector = $(".select2");
    }
    if (jQuery().select2) {
      return selector.each(function(i, elem) {
        return $(elem).select2();
      });
    }
  };

  function setValidateForm(selector) {
    if (selector == null) {
      selector = $(".validate-form");
    }
    if (jQuery().validate) {
      return selector.each(function(i, elem) {
        return $(elem).validate({
          errorElement: "span",
          errorClass: "help-block has-error",
          errorPlacement: function(e, t) {
            return t.parents(".controls").first().append(e);
          },
          highlight: function(e) {
            return $(e).closest('.form-group').removeClass("has-error has-success").addClass('has-error');
          },
          success: function(e) {
            return e.closest(".form-group").removeClass("has-error");
          }
        });
      });
    }
  };

});
