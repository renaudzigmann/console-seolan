/*
 * history 1.0 - Plugin for jQuery
 *
 *
 * IE8 is supporting onhashchange event
 * http://msdn.microsoft.com/en-us/library/cc288209(VS.85).aspx
 *
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Depends:
 *   jquery.js
 *
 *  Copyright (c) 2008 Oleg Slobodskoi (ajaxsoft.de)
 */

;(function($){


    $.fn.history = function( option ) {
	    var    args = Array.prototype.slice.call(arguments, 1);
         return this.each(function() {
	             var instance = $.data(window, 'history') || $.data( window, 'history', new hist()).init();

	              typeof option == 'string' ? instance[option].apply( this, args ) : instance.bind(this, option);
	           });

	    };

     function hist() {
	     var self = this,
              IE67 = $.browser.msie && parseInt($.browser.version) < 8 ? true : false,
	              IE8 = $.browser.msie && parseInt($.browser.version) >= 8 ? true : false,
	               $iframe,
		        $listeners,
	          interval;

	       this.init = function() {
	            if ( IE67 ) $iframe = $('<iframe style="display: none;" class="x-history-iframe"/>').appendTo(document.body);

	             self.value = top.location.hash.replace('#','');

	              if ( IE67 ) {
		               checkIFrame();
		         } else  if ( !IE8 ){
	                   var hash = top.location.hash;
		                interval = setInterval(function() {
		                      var newHash = top.location.hash;
			                if (newHash !== hash) {
		                           hash = newHash;
			                         change(hash);
			                    };
		                   }, 50);
		             };
	               return self;
		    };

          this.bind = function(elem, callback) {
	              $listeners = !$listeners ?  $(elem) : $listeners.add(elem);
	               $(elem).bind('hashchange', IE8 ? function(e){
		                change(top.location.hash);
		              callback.apply(elem, [$.Event(e), 'r']);
		        } : callback );
	      };

           this.unbind = function() {
	               delete $listeners[$listeners.index(this)];
		        $(this).unbind('hashchange');
	      };

           this.add = function( value, params ) {
	               self.params = params;
		        top.location.hash = value;
	          IE67 && updateIFrame(value)
	           change(value);
	        };

	     this.forward = function() {
	          history.go(1);
	       };

	    this.back = function() {
	         history.go(-1);
	      };


           this.destroy = function() {
	               clearInterval(interval);
		        $iframe && $iframe.remove();
	          $listeners.unbind('hashchange');
	           $.removeData(window, 'history');
	        };


	     function checkIFrame()
          {
	              var iHash = iDoc().location.hash,
	                   hash = top.location.hash;

		            interval = setInterval(function () {
		              var newiHash = iDoc().location.hash,
		                newHash = top.location.hash;

		              if (newiHash !== iHash) {
		                    iHash = newiHash;
		                      change(iHash);
			                top.location.hash = iHash;
		                   hash = iHash;
		                 } else if (newHash !== hash) {
		                       hash = newHash;
			                 updateIFrame(newHash);
		                }

		          }, 50);

	        };

	     function change(value) {
	          self.value = value.replace('#','');
	           !IE8 && $.event.trigger('hashchange', [self]);
	        };

	     function updateIFrame(value) {
	          iDoc().open();
	           iDoc().close();
	            iDoc().location.hash = value;
	         };

	      function iDoc() {
	           return $iframe[0].contentWindow.document;
	        };

	 };


  })(jQuery);
  