(function ($) {
    //TODO Ajouter préfixe aux id (ex: ie-id)
    //TODO Télécharger grande image chromium non fonctionnelle
    //TODO Ajouter une image par dessus (ex: image de l'auteur)
    //TODO Ajouter une fonction annuler dernière action (stockage image_modif, index actuel, annuler annulation)
    //Problème slider lensBlur -> angle sur firefox affiche une error/devient rouge lorsque les limite sont -Math.PI et Math.PI mais toujours fonctionnel

    var script_to_load = [{
            url: "dependence/cropper.min.js",
            ok: false
        },
        {
            url: "dependence/caman.full.min.js",
            ok: false
        },
        {
            url: "dependence/glfx.js",
            ok: false
        },
        {
            url: "border/index.js",
            ok: false
        }
    ];
    var script_loaded = 0;
    var devices_glfx_flip = [
        'iPad Simulator',
        'iPhone Simulator',
        'iPod Simulator',
        'iPad',
        'MacIntel',
        'iPhone',
        'iPod'
    ];
    var lang_possible = [
        "fr",
        "en"
    ];
    var format_possible = [
        'png',
        'jpeg',
        'webp'
    ];


    //Déterminer le path du script
    $("script").on('load', function () {
        var filename = 'image-editor';
        var scripts = document.getElementsByTagName('script');
        if (scripts && scripts.length > 0) {
            for (var i = 0; i < scripts.length; i++) {
                if (scripts[i].src && scripts[i].src.match(new RegExp(filename + '\\.js$'))) {
                    var dir = scripts[i].src.replace(new RegExp('(.*)' + filename + '\\.js$'), '$1');
                    defaults.path = dir;
                    break;
                }
            }
        }
    });

    function loadScripts() {
        return $.Deferred(function () {
            var self = this;

            function isDone() {
                if (script_loaded >= script_to_load.length) {
                    self.resolve();
                }
            }
            for (var i = 0; i < script_to_load.length; i++) {
                if (!script_to_load[i].ok) {
                    $.when({
                        i: i
                    }, $.getScript(settings.path + script_to_load[i].url)).done(
                        function (event) {
                            script_to_load[event.i].ok = true;
                            script_loaded++;
                            isDone();
                        }
                    ).fail(function () {
                        settings.onLoadScriptError(this.url);
                    });
                }
            }
            isDone();
        });
    }

    function getScript(url, async, done, fail) {
        //Principal utilité, charger en sync
        $.ajax({
            url: url,
            type: "GET",
            dataType: "script",
            async: async,
            success: done,
            error: fail
        });

    };


    // pour la barre de chargement de l'image de base
    Image.prototype.load = function (url, callback) {
        var thisImage = this,
            xhr = new XMLHttpRequest();

        thisImage.completedPercentage = 0;

        xhr.open('GET', url, true);
        xhr.responseType = 'arraybuffer';
        //xhr.responseType = 'blob';

        xhr.onload = function () {
            var h = xhr.getAllResponseHeaders(),
                m = h.match(/^Content-Type\:\s*(.*?)$/mi),
                type = m[1] || 'image/png';

            var uInt8Array = new Uint8Array(this.response);
            var i = uInt8Array.length;
            var binaryString = new Array(i);
            while (i--) {
                binaryString[i] = String.fromCharCode(uInt8Array[i]);
            }
            var data = binaryString.join('');

            var base64 = window.btoa(data);
            thisImage.src = "data:" + type + ";base64," + base64;

            // affectation de l'image de base pour les modifs
            image_modif.src = thisImage.src;

            //var blob = this.response;
            //var imageBlob = new Image;
            //imageBlob.onload = function(){
            //var canvas = document.createElement('CANVAS');
            //canvas.height = this.naturalHeight;
            //canvas.width = this.naturalWidth;
            //canvas.getContext('2d').drawImage(imageBlob, 0, 0, canvas.width, canvas.height, 0, 0, canvas.width, canvas.height);
            //thisImage.src = canvas.toDataURL('image/png',1);
            //window.URL.revokeObjectURL(this.src);
            //delete this;
            //}
            //imageBlob.src = window.URL.createObjectURL(blob)

        };

        xhr.onprogress = function (e) {
            if (e.lengthComputable)
                thisImage.completedPercentage = (e.loaded / e.total) * 100;
            $('#progressBar', settings.modal).css('width', thisImage.completedPercentage + '%').attr('aria-valuenow', thisImage.completedPercentage).text((thisImage.completedPercentage).toFixed(1) + " %");
        };

        xhr.onloadstart = function () {
            // Display your progress bar here, starting at 0
            thisImage.completedPercentage = 0;
            $('#progressBar', settings.modal).css('width', thisImage.completedPercentage + '%').attr('aria-valuenow', thisImage.completedPercentage).text((thisImage.completedPercentage).toFixed(1) + " %");
            $('#progressBar', settings.modal).parent().show();
        };

        xhr.onloadend = function () {
            thisImage.completedPercentage = 100;
            $('#progressBar', settings.modal).parent().hide();
            if (callback)
                callback(this);
        };
        xhr.onerror = function () {
            thisImage.src = "";
        };

        xhr.send(null);
    };

    var image_base = new Image();
    var image_modif = new Image();
    //taille réel (pour save/upload)
    var image_affiche = new Image();
    //petite taille préview (pour traitement CamanJS)
    var canvas_traitement = document.createElement('canvas');
    //pour effectuer les traitements de filtre
    var filtre_utilise = null;
    var ratio_image;
    var canvas_glfx = null;
    var texture = null;
    var settings = {};
    var defaults = {
        urlImage: null,
        urlServeur: null,
        formatImageSave: 'png',
        imageName: 'image',
        path: "",
        lang: "fr",
        maxHeight: 4096,
        maxWidth: 4096,
        modal: null,
        uploadData: {},
        onUpload: function (serverMsg) {},
        onUploadError: function () {},
        onHide: function () {},
        onLoadImageError: function () {},
        onGlfxNoSupport: function () {
            alert(settings.lang.error_glfx_support_msg);
        },
        onLoadScriptError: function (urlScript) {
            alert("Le scipt suivant n'a pas chargé:\n\n" + urlScript.replace(/\?.*$/, ""));
        },
        onLoadLangError: function (exception) {
            alert("La langue '" + settings.lang + "' n'a pas chargé.\n\nErreur : " + exception);
        },
        onShow: function () {}
    };
    var modifNoSave = false;
    var uploading = false;
    image_affiche.id = "image";

    image_affiche.className = "img-responsive center-block";
    image_base.className = "img-responsive center-block";


    //image_base.crossOrigin = "Anonymous";
    //image_affiche.crossOrigin = "Anonymous";
    //image_modif.crossOrigin = "Anonymous";

    $.fn.imageEditor = function (options, action) {
        if (options === undefined) {
            options = {};
        }

        if (!action && typeof (options) === 'string') {
            action = options;
            options = {};
        }
        if (this !== window) {
            options.selector = this;
        } else {
            options.selector = null;
        }

        if (!action && options.urlImage) {
            //Si une image en option
            action = 'show';
        }
        if (!settings.modal && action === 'remove') {
            return;
        }

        $.when({
                action: action,
                options: options
            },
            imageEditorInit(options)
        ).done(function (event) {
            var action = event.action;
            var options = event.options;

            delete options.modal;
            //delete options.path;
            delete options.lang;
            settings = $.extend({}, defaults, settings, options);

            if (action) {
                switch (action) {
                    case 'show':
                        imageEditorEdit(options);
                        break;
                    case 'hide':
                        settings.modal.modal('hide');
                        break;
                    case 'remove':
                        if ($(settings.modal).hasClass('in')) {
                            settings.modal.on('hidden.bs.modal', function () {
                                this.remove();
                            });
                            settings.modal.modal('hide');
                        } else {
                            settings.modal.remove();
                        }
                        delete settings.modal;
                        break;
                }
            }
        });
    };

    /////// Après la déclaration de imageEditor
    $.fn.imageEditor.personaliseLang = {};
    $.fn.imageEditor.noDisplayTraitements = [];
    $.fn.imageEditor.noDisplayFiltres = [];

    var deferred = $.Deferred();
    ///	Pour le when ... done

    function imageEditorInit(options) {

        if (settings.modal != null) {
            return deferred.resolve();
        }

        if (options) {
            //delete options.path;
        }
        settings = $.extend({}, defaults, options);
        var zone = null;
        if (options.selector) {
            options.selector.each(function () {
                zone = $(this);
            });
        }
        $.when(
            //quand tout les scripts sont chargé
            loadScripts()
        ).done(function (event) {
            if (options.lang) {
                for (var i = 0; i < lang_possible.length; i++) {
                    if (settings.lang == lang_possible[i]) {
                        break;
                    } else if (i == lang_possible.length - 1) {
                        settings.lang = defaults.lang;
                    }
                }

            }
            getScript(settings.path + "lang/" + settings.lang + ".js", false, function (data, textStatus) {
                    settings.lang = $.extend({}, $.fn.imageEditor.prototype.lang, $.fn.imageEditor.personaliseLang);
                    delete $.fn.imageEditor.prototype.lang;
                    initTraitements();
                },
                function (jqxhr, setting, exception) {
                    if (settings.lang instanceof String) {
                        settings.onLoadLangError(exception);
                    }
                    return;
                }
            );

            $(document).ready(function (event) {
                try {
                    canvas_glfx = fx.canvas();
                } catch (e) {
                    canvas_glfx = undefined;
                    settings.onGlfxNoSupport();
                }

                if (zone == null) {
                    zone = 'body';
                }
                var id = 'modalImageEditor';
                var i = 0;
                while ($('#' + id + i).length) {
                    i++;
                }
                var modalAttr = {
                    main: {
                        class: 'modal fade image-editor',
                        id: id + i,
                        //tabindex: '-1',
                        role: 'dialog',
                        'aria-labelledby': 'modalImageEditor',
                        'data-backdrop': 'static',
                        'data-keyboard': false
                    },
                    dialog: {
                        class: 'modal-dialog modal-lg',
                        role: 'document'
                    },
                    content: {
                        class: 'modal-content'
                    }
                };
                settings.modal = $('<div />').attr(modalAttr.main).appendTo(zone);
                var dialog = $('<div />').attr(modalAttr.dialog).appendTo(settings.modal);
                var content = $('<div />').attr(modalAttr.content).appendTo(dialog);

                //Affichage du modal par défaut
                $('<div />').attr({
                    class: 'modal-body'
                }).text(settings.lang.error_msg).appendTo(content);
                var footer = $('<div />').attr({
                    class: 'modal-footer'
                }).appendTo(content);
                $('<button />').attr({
                    'data-dismiss': 'modal'
                }).text('close').appendTo(footer);

                settings.modal.on('hidden.bs.modal', settings.onHide);
                settings.modal.on('shown.bs.modal', settings.onShow);

                return deferred.resolve();
            });
        });
        return deferred;
    };

    function resizeCanvasImage(img, canvas, maxWidth, maxHeight) {
        var imgWidth = img.width,
            imgHeight = img.height;

        var ratio = 1,
            ratio1 = 1,
            ratio2 = 1;
        ratio1 = maxWidth / imgWidth;
        ratio2 = maxHeight / imgHeight;


        // Use the smallest ratio that the image best fit into the maxWidth x maxHeight box.
        if (ratio1 < ratio2) {
            ratio = ratio1;
        } else {
            ratio = ratio2;
        }

        if (ratio > 1) {
            ratio = 1;
        }

        // pour faire simple ///////////////////
        //
        canvas.height = imgHeight * ratio;
        canvas.width = imgWidth * ratio;

        canvas.getContext('2d').drawImage(img, 0, 0, img.width, img.height, 0, 0, canvas.width, canvas.height);
        return ratio;
        /////////////////////////////////////////


        var canvasContext = canvas.getContext("2d");
        var canvasCopy = document.createElement("canvas");
        var copyContext = canvasCopy.getContext("2d");
        var canvasCopy2 = document.createElement("canvas");
        var copyContext2 = canvasCopy2.getContext("2d");
        canvasCopy.width = imgWidth;


        canvasCopy.height = imgHeight;
        copyContext.drawImage(img, 0, 0);

        // init
        canvasCopy2.width = imgWidth;
        canvasCopy2.height = imgHeight;
        copyContext2.drawImage(canvasCopy, 0, 0, canvasCopy.width, canvasCopy.height, 0, 0, canvasCopy2.width, canvasCopy2.height);


        var rounds = 2;
        var roundRatio = ratio * rounds;
        for (var i = 1; i <= rounds; i++) {

            // tmp
            canvasCopy.width = imgWidth * roundRatio / i;
            canvasCopy.height = imgHeight * roundRatio / i;

            copyContext.drawImage(canvasCopy2, 0, 0, canvasCopy2.width, canvasCopy2.height, 0, 0, canvasCopy.width, canvasCopy.height);

            // copy back
            canvasCopy2.width = imgWidth * roundRatio / i;
            canvasCopy2.height = imgHeight * roundRatio / i;
            copyContext2.drawImage(canvasCopy, 0, 0, canvasCopy.width, canvasCopy.height, 0, 0, canvasCopy2.width, canvasCopy2.height);

        }
        // end for


        // copy back to canvas
        canvas.width = imgWidth * roundRatio / rounds;
        canvas.height = imgHeight * roundRatio / rounds;
        canvasContext.drawImage(canvasCopy2, 0, 0, canvasCopy2.width, canvasCopy2.height, 0, 0, canvas.width, canvas.height);

        $(canvasCopy).remove();
        $(canvasCopy2).remove();
        return ratio;
    }

    function checkFormatName() {
        var index = settings.imageName.lastIndexOf(".");
        if (index > 0) {
            settings.imageName = settings.imageName.replace(settings.imageName.substring(index, settings.imageName.length), "");
        }
    }

    function imageEditorEdit(options) {


        if (settings.modal == null)
            return;

        image_affiche.src = "";

        if (settings.maxHeight > defaults.maxHeight) {
            settings.maxHeight = defaults.maxHeight;
        }
        if (settings.maxWidth > defaults.maxWidth) {
            settings.maxWidth = defaults.maxHeight;
        }

        checkFormatName();

        var format_ok = false;
        for (var i = 0; i < format_possible.length; i++) {
            if (settings.formatImageSave == format_possible[i]) {
                format_ok = true;
                break;
            }
        }
        if (!format_ok) {
            settings.formatImageSave = 'png';
        }
        if (settings.formatImageSave == 'png') {
            image_affiche.style.background = "url(" + settings.path + "dependence/background.png) repeat";
        } else {
            image_affiche.style.background = "black";
        }
        settings.modal.modal().load(settings.path + 'image-editor.html' + '?' + (new Date().getTime()), function (e) {
            $('#image_zone', settings.modal).empty().html('<p class="text-center">' + settings.lang.loading_image_msg + '</p>');
            $('.modal-title', settings.modal).text(settings.lang.title + ' - ' + settings.imageName + '.' + settings.formatImageSave);
            $('#loading_circle', settings.modal).attr({
                src: settings.path + '/dependence/loading_circle.gif'
            });
            $('#famille li', settings.modal).removeClass("active");
            $('.tab-content div', settings.modal).removeClass("active");
            $('#loading_circle', settings.modal).show();
            $('#image_url', settings.modal).empty();
            image_affiche.onload = function () {
                //premier chargement de l'image affichée
                $('#image_zone', settings.modal).empty().append(image_affiche);
                $('#loading_circle', settings.modal).hide();
                reset();

                image_affiche.onload = null;

                //définir la taille maximal (pour canvas_glfx)
                resizeCanvasImage(image_modif, canvas_traitement, settings.maxWidth, settings.maxHeight);
                image_modif.src = canvas_traitement.toDataURL('image/' + settings.formatImageSave, 1);

                $(canvas_traitement).remove();
                canvas_traitement = document.createElement('canvas');
                $('#li_cropper', settings.modal).on('click', function () {
                    annuler();
                    crop();
                }).text(settings.lang.crop + '/' + settings.lang.rotate);
                $('#li_filtre', settings.modal).on('click', function () {
                    annuler();
                }).text(settings.lang.filters);
                $('#li_traitement', settings.modal).on('click', function () {
                    annuler();
                }).text(settings.lang.image_process);
                $('#li_border', settings.modal).on('click', function () {
                    annuler();
                }).text(settings.lang.border);
                $('#li_comparer', settings.modal).on('click', function () {
                    //$('#image_zone #image', settings.modal).cropper("destroy");
                    affiche_base();
                }).text(settings.lang.compare);
                $('#li_reset', settings.modal).on('click', function () {
                    annuler();
                    reset();
                }).text(settings.lang.reset);

                $('#crop button', settings.modal).on('click', function () {
                    cropValidation(this.value);
                });
                //image_modif.onload();
                $("#imageInfo", settings.modal).show();
            };

            image_modif.onload = function () {

                if (image_modif.height > 4096 || image_modif.width > 4096) {
                    var canvas = document.createElement('canvas');
                    resizeCanvasImage(image_modif, canvas, 4096, 4096);
                    image_modif.src = canvas.toDataURL("image/png");
                    return;
                }

                ratio_image = resizeCanvasImage(image_modif, canvas_traitement, 550, 550);
                image_affiche.src = canvas_traitement.toDataURL("image/png");

                if (canvas_glfx) {
                    /*$(canvas_glfx).remove();
                     canvas_glfx = fx.canvas();*/
                    canvas_glfx.height = canvas_traitement.height;
                    canvas_glfx.width = canvas_traitement.width;
                    if (texture) {
                        texture.destroy();
                    }
                    texture = canvas_glfx.texture(canvas_traitement);
                    canvas_glfx.draw(texture).update();
                }

                $("#heightInfo", settings.modal).text(image_modif.height);
                $("#widthInfo", settings.modal).text(image_modif.width);

            };

            var erreur = function () {
                $('#image_zone', settings.modal).empty().html('<p class="text-center">' + settings.lang.error_loading_image_msg + '(' + settings.urlImage + ').</p>');
                $('#loading_circle', settings.modal).hide();
                settings.onLoadImageError();
            };
            image_base.onerror = erreur;
            image_affiche.onerror = erreur;

            $('#li_cropper', settings.modal).text(settings.lang.crop + '/' + settings.lang.rotate);
            $('#li_filtre', settings.modal).text(settings.lang.filters);
            $('#li_traitement', settings.modal).text(settings.lang.image_process);
            $('#li_comparer', settings.modal).text(settings.lang.compare);
            $('#li_reset', settings.modal).text(settings.lang.reset);
            $('#cropper #valider', settings.modal).text(settings.lang.validate_button);
            $('#cropper #annuler', settings.modal).text(settings.lang.cancel_button);


            //button close
            var quit_validate = $('<div/>').appendTo('.modal-footer', settings.modal).hide().append('<p id="close_msg">' + settings.lang.close_msg + '</p>');
            $('<button/>').attr({
                class: 'btn btn-success',
                type: 'button'
            }).text(settings.lang.cancel_button).appendTo(quit_validate);
            $('<button/>').attr({
                class: 'btn btn-danger',
                type: 'button'
            }).text(settings.lang.close_button).appendTo(quit_validate).on('click', function () {
                if (uploading != false) {
                    uploading.abort();
                }
                settings.modal.modal('hide');
            });

            var button_close = $('<button/>').attr({
                    type: 'button',
                    class: 'btn btn-danger',
                    role: 'button'
                })
                .text(settings.lang.close_button)
                .prependTo('.modal-footer #button-action', settings.modal)
                .on('click', {
                    quit_validate: quit_validate
                }, function (event) {
                    annuler();
                    if (settings.urlServeur && (uploading != false || modifNoSave == true)) {
                        $(event.data.quit_validate).show();
                        $(this).parent().hide();
                    } else {
                        settings.modal.modal('hide');
                    }
                });

            $('button', quit_validate).on('click', {
                quit_validate: quit_validate
            }, function (event) {
                $(event.data.quit_validate).hide();
                $('.modal-footer #button-action', settings.modal).show();
            });


            //button save
            $('<button />').attr({
                    class: 'btn btn-success',
                    type: 'button'
                })
                .on('click', download)
                .text(settings.lang.download_button)
                .prependTo('.modal-footer #button-action', settings.modal);

            //button d'upload
            if (settings.urlServeur != null) {
                $('<button />').attr({
                        id: 'upload',
                        type: 'button',
                        class: 'btn'
                    })
                    .text(settings.lang.upload_button)
                    .on('click', upload)
                    .prependTo('.modal-footer #button-action', settings.modal);
            }
            $('#loading_circle', settings.modal).show();
            $('#progressBar', settings.modal).parent().hide();



            ////////
            //cropper
            ////////
            var crop_format = [{
                    "label": "16:9",
                    "value": 16 / 9
                }, //0
                {
                    "label": "4:3",
                    "value": 4 / 3
                }, //1
                {
                    "label": "1:1",
                    "value": 1
                }, //2
                {
                    "label": "3:4",
                    "value": 3 / 4
                }, //3
                {
                    "label": "9:16",
                    "value": 9 / 16
                } //4
            ];
            $("#crop #crop-free-text", settings.modal).text(settings.lang.crop_free);
            $("#crop > label", settings.modal).text(settings.lang.crop + '/' + settings.lang.rotate);
            $("#mirror > label", settings.modal).text(settings.lang.mirror);
            for (var i = 0; i < crop_format.length; i++) {
                $('<label>', {
                        class: 'btn btn-primary',
                        id: "ratio-add"
                    })
                    .append(
                        $('<input>', {
                            type: 'radio',
                            name: 'crop-ratio',
                            value: crop_format[i].value
                        })
                    ).append(
                        $('<span>').text(crop_format[i].label)
                    ).appendTo('#crop-ratio-list');
            }
            $('#crop #crop-ratio-list input:radio[value="NaN"]', settings.modal).click(); //.prop('checked',true);
            $('#crop #crop-ratio-list input:radio', settings.modal).on('change', function (event) {
                $('#cropper > :not(#crop) button#annuler:visible').click();
                $('#cropper > #crop #validation').show();
                var image = $('#image_zone #image', settings.modal);
                var data;
                if (image.data("cropper-active") == true) {
                    data = image.cropper('getData');
                    image.cropper('destroy');
                    delete data.x;
                    delete data.y;
                    delete data.height;
                    delete data.width;
                }
                image.one("built.cropper", function () {
                    if (data != undefined) {
                        image.cropper('setData', data);
                    }
                }).cropper({
                    viewMode: 0,
                    aspectRatio: this.value
                });
                image.data("cropper-active", true);
            });
            $('#mirror label:has(input)', settings.modal).on('click', function (e) {
                $('#cropper > :not(#mirror) button#annuler:visible').click();
                $('#cropper > #mirror #validation').show();
                var data = $('input', this).data();
                var $image = $('#image_zone #image', settings.modal);
                $('input', this).data('option', -data.option);
                var setData = function () {
                    $image.cropper(data.method, data.option);
                    $image.cropper('clear');
                    $image.cropper('setDragMode', 'none');
                    $image.data("cropper-active", true);
                };
                if ($image.data("cropper-active") == true) {
                    setData();
                } else {
                    $image.one("built.cropper", setData).cropper();
                }

            });
            $('#mirror button#annuler', settings.modal).click(function (e) {
                $('#mirror label.active:has(input)', settings.modal).click();
                $('#cropper > #mirror #validation').hide();
                $('#image_zone #image', settings.modal).cropper('destroy').data("cropper-active", false);
            });
            $('#mirror button#valider', settings.modal).click(function (e) {
                $('#cropper > #mirror #validation').hide();
                var image = $('#image_zone #image', settings.modal);
                var data = image.cropper('getData');
                delete data.x;
                delete data.y;
                delete data.height;
                delete data.width;
                var $image_modif = $(image_modif);
                $image_modif.one("built.cropper", function () {
                    $image_modif.cropper('clear');
                    $image_modif.cropper('setData', data);
                    var canvas = image_modif.cropper('getCroppedCanvas')
                    image_modif.src = canvas.toDataURL("image/" + settings.formatImageSave, 1);
                    $image_modif.cropper('destroy');
                    $('#mirror label.active input', settings.modal).data('option', -1);
                    $('#mirror label.active:has(input)', settings.modal).removeClass('active').find('input').prop('checked', false);
                    image.cropper('destroy');
                }).cropper();

            });
            $('#crop #rotate-input').on('change input', function (e) {
                if (!$('#crop #crop-ratio-list input').is(':checked')) {
                    $('#crop #crop-ratio-list input[value="NaN"]').click();
                }
                $('#image_zone #image', settings.modal).cropper($(this).data('method'), this.value - 180);
            });

            //image_modif.load(settings.urlImage);
            image_base.load(settings.urlImage);
            //image_base.src = settings.urlImage;


        });

    }

    function download() {
        var a = document.createElement('a');
        a.download = settings.imageName + '.' + settings.formatImageSave;
        a.href = image_modif.src;
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    function affiche_base() {
        $('#loading_circle', settings.modal).show();
        var cropData;
        if ($('#image_zone #image', settings.modal).data("cropper-active") == true) {
            cropData = $('#image_zone #image', settings.modal).cropper('getData');
            $('#image_zone #image', settings.modal).cropper('destroy').data("cropper-active", false);
        }
        var canvas_base = document.createElement('canvas');
        resizeCanvasImage(image_base, canvas_base, 550, 550);
        var timer;
        var change = function () {
            if (timer) {
                clearTimeout(timer);
                $('#loading_circle', settings.modal).hide();
            }
            $(image_affiche).off('load build.ready built.ready cropstart.cropper cropmove.cropper cropend.cropper crop.cropper zoom.cropper', change);
        }
        $(image_affiche).one('load', function () {
            $(this).one('load build.ready built.ready cropstart.cropper cropmove.cropper cropend.cropper crop.cropper zoom.cropper', change);
        });
        image_affiche.src = canvas_base.toDataURL("image/png");
        //image_affiche.src = "data:image/png;base64,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";
        timer = setTimeout(function (e) {
            $(image_affiche).off('load build.ready built.ready cropstart.cropper cropmove.cropper cropend.cropper crop.cropper zoom.cropper', change);
            if (cropData) {
                $('#image_zone #image', settings.modal).one("built.cropper", function () {
                    $('#image_zone #image', settings.modal).cropper('setData', cropData).data("cropper-active", true);
                    if ($('#cropper #mirror #validation', settings.modal).is(':visible')) {
                        $('#image_zone #image', settings.modal).cropper('clear');
                    }
                }).cropper()
            } else if ($('#li_traitement', settings.modal).parent().hasClass('active') && canvas_glfx) {
                image_affiche.src = canvas_glfx.toDataURL("image/png");
            } else {
                image_affiche.src = canvas_traitement.toDataURL("image/png");
            }
            $('#loading_circle', settings.modal).hide();
        }, 1000);
        $(canvas_base).remove();
        delete canvas_base;
    }

    function filtreValidation(etat) {
        //valider les traitements sur taille réel ou non
        $('#loading_circle', settings.modal).show();
        if (etat == 'true' && filtre_utilise != null) {
            canvas_reel = document.createElement('canvas');
            canvas_reel.height = image_modif.height;
            canvas_reel.width = image_modif.width;
            canvas_reel.getContext('2d').drawImage(image_modif, 0, 0);
            Caman(canvas_reel, function () {
                this[filtre_utilise]();
                this.render(function () {
                    image_modif.src = canvas_reel.toDataURL("image/" + settings.formatImageSave, 1);
                    filtre_utilise = null;
                    $(canvas_traitement).remove();
                    delete canvas_reel;
                    delete canvas_traitement;
                    canvas_traitement = document.createElement('canvas');
                    //pour ne pas perdre les filtre au preview avec this.revert()
                    $('#filtre_zone #filtre', settings.modal).show();
                    $('#loading_circle', settings.modal).hide();
                });
            });
        } else if (filtre_utilise != null) {
            Caman(canvas_traitement, function () {
                this.revert();
                this.render(function () {
                    filtre_utilise = null;
                    image_affiche.src = canvas_traitement.toDataURL("image/png");
                    $('#loading_circle', settings.modal).hide();
                    $('#filtre_zone #filtre', settings.modal).show();
                });
            });
        } else {
            $('#loading_circle', settings.modal).hide();
            $('#filtre_zone #filtre', settings.modal).show();
        }

        $('#filtre_zone #validation', settings.modal).hide();
        $(".modal-footer #button-action", settings.modal).removeClass("traitement-no-validate");

    }

    function camanFiltre(filtre) {
        //pour le préview
        $('#loading_circle', settings.modal).show();
        Caman(canvas_traitement, function () {
            if (filtre == "normal") {
                this.revert();
                filtre_utilise = filtre;
            } else if (filtre in this) {
                filtre_utilise = filtre;
                this.revert();
                this[filtre]();
            }
            this.render(function () {
                image_affiche.src = canvas_traitement.toDataURL("image/png");
                $('#filtre_zone #filtre', settings.modal).hide();
                $('#filtre_zone #validation', settings.modal).show();
                $('#loading_circle', settings.modal).hide();
                modifNoSave = true;
                $(".modal-footer #button-action", settings.modal).addClass("traitement-no-validate");
            });
        });
    }

    function slider_change(slider, traitement, value) {
        traitement[slider.id] = parseFloat(value);
        traitement.update();
    }

    function getBorderById(id) {
        if (typeof id === 'string') {
            for (var i = 0; i < $.fn.imageEditor.prototype.borderList.length; i++) {
                var border = $.fn.imageEditor.prototype.borderList[i];
                if (border.id === id) {
                    return border;
                }
            }
        }
        return null;
    }

    function previewBorder(borderId) {
        $('#loading_circle', settings.modal).show();
        var border = getBorderById(borderId);
        if (border !== null) {
            switch (border.partial) {
                default: var imageBorder = new Image();
                var canvas = document.createElement('CANVAS');
                canvas.height = image_affiche.naturalHeight;
                canvas.width = image_affiche.naturalWidth;
                canvas.getContext('2d').drawImage(image_affiche, 0, 0);
                $(imageBorder).one('load', {
                    canvas: canvas
                }, function (e) {
                    var ctx = e.data.canvas.getContext('2d');
                    ctx.drawImage(this, 0, 0, this.width, this.height, 0, 0, image_affiche.naturalWidth, image_affiche.naturalHeight);
                    image_affiche.src = e.data.canvas.toDataURL('image/png');
                });
                imageBorder.onerror = function (err, a, z, e, r) {
                    console.log(err);
                };
                if (border.svgData) {
                    if (border.svgData.getAttribute('height') == undefined) {
                        imageBorder.height = image_affiche.naturalHeight;
                        border.svgData.setAttribute('height', image_affiche.naturalHeight);
                    }
                    if (border.svgData.getAttribute('width') == undefined) {
                        imageBorder.width = image_affiche.naturalWidth;
                        border.svgData.setAttribute('width', image_affiche.naturalWidth);
                    }
                    var xml = (new XMLSerializer).serializeToString(border.svgData);
                    imageBorder.src = "data:image/svg+xml;base64," + btoa(xml);
                    //imageBorder.src = settings.path + 'border/' + border.file;
                } else {
                    imageBorder.src = border.src;
                }
                break;
                case 2:
                        break;
                case 4:
                        break;
            }
        } else {

        }

        $('#loading_circle', settings.modal).hide();
        $('#border_zone #validation', settings.modal).hide();
        $('#border_zone #border_zone_list', settings.modal).show();

        $(".modal-footer #button-action", settings.modal).removeClass("traitement-no-validate");
    }

    function borderValidation(borderId) {
        $('#loading_circle', settings.modal).show();
        var border = getBorderById(borderId);
        if (border !== null) {
            switch (border.partial) {
                default: var imageBorder = new Image();
                var canvas = document.createElement('CANVAS');
                canvas.height = image_modif.height;
                canvas.width = image_modif.width;
                canvas.getContext('2d').drawImage(image_modif, 0, 0);
                $(imageBorder).one('load', {
                    canvas: canvas
                }, function (e) {
                    var ctx = e.data.canvas.getContext('2d');
                    ctx.drawImage(this, 0, 0, this.width, this.height, 0, 0, image_modif.width, image_modif.height);
                    image_modif.src = e.data.canvas.toDataURL('image/png');
                });
                imageBorder.onerror = function (err, a, z, e, r) {
                    console.log(err);
                };
                if (border.svgData) {
                    if (border.svgData.getAttribute('height') == undefined) {
                        imageBorder.height = image_modif.height;
                        border.svgData.setAttribute('height', image_modif.height);
                    }
                    if (border.svgData.getAttribute('width') == undefined) {
                        imageBorder.width = image_modif.width;
                        border.svgData.setAttribute('width', image_modif.width);
                    }
                    var xml = (new XMLSerializer).serializeToString(border.svgData);
                    imageBorder.src = "data:image/svg+xml;base64," + btoa(xml);
                    //imageBorder.src = settings.path + 'border/' + border.file;
                } else {
                    imageBorder.src = border.src;
                }
                break;
                case 2:
                        break;
                case 4:
                        break;
            }
            modifNoSave = true;
        } else {
            var canvas = document.createElement('canvas');
            resizeCanvasImage(image_modif, canvas, 550, 550);
            image_affiche.src = canvas.toDataURL("image/png");
        }

        $('#loading_circle', settings.modal).hide();
        $('#border_zone #validation', settings.modal).hide();
        $('#border_zone #border_zone_list', settings.modal).show();

        $(".modal-footer #button-action", settings.modal).removeClass("traitement-no-validate");
    }

    function reset() {
        modifNoSave = false;
        $('#filtre', settings.modal).empty();
        $('#filtre_zone #validation', settings.modal).empty();
        $('#traitement_zone', settings.modal).empty();
        $('#border_zone #border_zone_list', settings.modal).empty();
        $(canvas_traitement).remove();
        delete canvas_traitement;
        canvas_traitement = document.createElement('canvas');
        canvas_traitement.height = image_affiche.naturalHeight;
        canvas_traitement.width = image_affiche.naturalWidth;
        if (canvas_glfx) {
            $(canvas_glfx).remove();
            delete canvas_glfx;
            canvas_glfx = fx.canvas();
            canvas_glfx.height = image_affiche.naturalHeight;
            canvas_glfx.width = image_affiche.naturalWidth;
        }
        if (image_affiche.onload === null) {
            //image_affiche change à onload de image_modif
            resizeCanvasImage(image_base, canvas_traitement, settings.maxWidth, settings.maxHeight);
            image_modif.src = canvas_traitement.toDataURL('image/' + settings.formatImageSave, 1);
        }
        filtre_utilise = null;

        /////////
        //  Border
        /////////
        $('a#li_border', settings.modal).parent().hide();
        $.fn.imageEditor.prototype.borderList = $.fn.imageEditor.prototype.borderList || [];
        var borderToAdd = $.fn.imageEditor.prototype.borderList.length;
        var numberBorderLoad = 0;
        var displayBorderZone = false;
        var borderLoaded = function (success) {
            numberBorderLoad++;
            console.log(success);
            console.log(borderToAdd);
            console.log(numberBorderLoad);
            console.log(displayBorderZone);
            console.log("-------------------");
            if (success === true) {
                displayBorderZone = true;
            }
            if (numberBorderLoad === borderToAdd && displayBorderZone === true) {
                $('a#li_border', settings.modal).parent().show();
            } else if (displayBorderZone === false) {
                $('a#li_border', settings.modal).parent().hide();
            }
        }
        for (var i = 0; i < $.fn.imageEditor.prototype.borderList.length; i++) {
            var border = $.fn.imageEditor.prototype.borderList[i];
            var url = settings.path + 'border/' + border.file;
            if (/https?:\/\/[^\s]+/.test(border.file)) { //si le fichier est une url
                url = border.file;
            }
            border.url = url;
            $.ajax({
                url: url,
                context: border,
                success: function (data, status, jqXHR) {

                    if (data instanceof XMLDocument) {
                        //si svg
                        //if(/^\s*(?:<\?xml[^>]*>\s*)?(?:<!doctype svg[^>]*\s*(?:<![^>]*>)*[^>]*>\s*)?<svg[^>]*>[^]*<\/svg>\s*$/gim.test(data)){
                        this.svgData = data.getElementsByTagName('svg')[0];
                        this.svgData.setAttribute('preserveAspectRatio', 'none');
                        if (this.svgData.getAttribute('height') == undefined) {
                            this.svgData.setAttribute('height', image_affiche.height);
                        }
                        if (this.svgData.getAttribute('width') == undefined) {
                            this.svgData.setAttribute('width', image_affiche.width);
                        }
                        url = "data:image/svg+xml;base64," + btoa((new XMLSerializer).serializeToString(this.svgData));
                    }
                    $('<div>').addClass('col-xs-6 col-sm-4 col-lg-3').append(
                        $('<button>').addClass('btn').attr({
                            id: this.id
                        }).append(
                            $('<img>').attr({
                                alt: this.id,
                                title: this.id,
                                src: url || this.url
                            }).addClass('img-responsive')
                        ).click(function () {
                            previewBorder(this.id);
                            $('#border_zone #border_zone_list', settings.modal).hide();
                            $('#border_zone #validation', settings.modal).show();
                            $('#border_zone #validation', settings.modal).show();
                            $('#border_zone #validation #valider', settings.modal).val(this.id);
                        })
                    ).appendTo('#border_zone #border_zone_list', settings.modal);
                    borderLoaded(true);
                },
                error: function (jqxhr, setting, exception) {
                    borderLoaded(false);
                }
            });
        }
        $('#border_zone #validation', settings.modal).hide()
            .find('button')
            .on('click', function () {
                borderValidation(this.value);
            });
        $('#border_zone #border_zone_list', settings.modal).show();

        /////////
        //  Filtres
        /////////
        var $liFilter = $('a#li_filtre', settings.modal).parent().hide();
        for (var i = 0; i < filtres.length; i++) {
            var filtre = filtres[i];
            var display = true;
            for (var j = 0; j < $.fn.imageEditor.noDisplayFiltres.length; j++) {
                if (filtre.id == $.fn.imageEditor.noDisplayFiltres[j]) {
                    display = false;
                    break;
                }
            }
            if (display) {
                $liFilter.show();
                $('<button />').attr({
                        type: "button",
                        class: "btn",
                        id: filtre.id
                    })
                    .text(filtre.label)
                    .appendTo('#filtre', settings.modal)
                    .on('click', function () {
                        camanFiltre(this.id);
                    });
            }
        }
        $('<button />').attr({
                id: "valider",
                type: "button",
                value: "true",
                class: "btn"
            })
            .text(settings.lang.validate_button)
            .appendTo('#filtre_zone #validation', settings.modal)
            .on('click', function () {
                filtreValidation(this.value);
            });

        $('<button />').attr({
                id: "annuler",
                type: "button",
                value: "false",
                class: "btn"
            })
            .text(settings.lang.cancel_button)
            .appendTo('#filtre_zone #validation', settings.modal)
            .on('click', function () {
                filtreValidation(this.value);
            })
            .parent().hide();
        ///////////
        //  Traitements
        /////////

        if (canvas_glfx) {
            $('<div />').attr({
                id: "traitement",
                class: "center-block"
            }).appendTo('#traitement_zone', settings.modal);
            $('<div />').attr({
                id: "traitement_parametre",
                class: "tab-content"
            }).appendTo('#traitement_zone', settings.modal);
            var $liTraitement = $('a#li_traitement', settings.modal).parent().hide();
            for (var i = 0; i < traitements.length; i++) {
                var traitement = traitements[i];
                var display = true;
                for (var j = 0; j < $.fn.imageEditor.noDisplayTraitements.length; j++) {
                    if (traitement.id == $.fn.imageEditor.noDisplayTraitements[j]) {
                        display = false;
                        break;
                    }
                }
                if (display) {
                    $liTraitement.show();
                    $('<button />').attr({
                            'data-toggle': "tab",
                            href: '#' + traitement.id,
                            class: 'btn',
                            type: 'button'
                        })
                        .text(traitement.label)
                        .on('click', {
                            traitement: traitement
                        }, function (event) {
                            var traitement = event.data.traitement;
                            setSelectedTraitement(traitement);
                        })
                        .appendTo('#traitement', settings.modal);

                    var div_traitement = $('<div />').attr({
                        id: traitement.id,
                        class: "row center-block tab-pane fade in table-responsive"
                    }).appendTo('#traitement_parametre', settings.modal);
                    $('<p/>').text(traitement.label).attr({
                        style: 'text-align: center;'
                    }).appendTo(div_traitement);

                    /////////
                    //  Sliders
                    /////////
                    if (traitement.sliders.length) {
                        var table = $('<table />').attr({
                            class: 'table'
                        }).appendTo(div_traitement);
                    }
                    for (var j = 0; j < traitement.sliders.length; j++) {
                        var slider = traitement.sliders[j];
                        var tr = $('<tr />').appendTo(table);
                        $('<th />').text(slider.label).appendTo(tr);
                        traitement[slider.id] = slider.value;
                        var th = $('<th />').appendTo(tr);
                        $('<input />').attr({
                                type: "range",
                                id: slider.id,
                                min: slider.min,
                                max: slider.max,
                                value: slider.value,
                                step: slider.step
                            }).on('input', {
                                slider: slider,
                                traitement: traitement
                            }, function (event) {
                                slider_change(event.data.slider, event.data.traitement, $(this).val());
                            })
                            .on('change', {
                                slider: slider,
                                traitement: traitement
                            }, function (event) {
                                slider_change(event.data.slider, event.data.traitement, $(this).val());
                            })
                            .appendTo(th);
                    }

                    //////////
                    //  Checkbox pour preview reel
                    //////////
                    $('<span/>').text(settings.lang.checkbox_preview)
                        .insertAfter(
                            $('<input />').attr({
                                type: 'checkbox'
                            })
                            .on('change', {
                                traitement: traitement
                            }, function (event) {
                                var traitement = event.data.traitement;
                                traitement.previewReel = $(this).is(':checked');
                                traitement.update();
                            }).appendTo(
                                $('<label/>').appendTo(
                                    $('<div/>').attr({
                                        class: 'checkbox',
                                        id: 'checkbox_preview'
                                    }).appendTo('#' + traitement.id, settings.modal)
                                )
                            )
                        );


                    //////////
                    //  Valider/Annuler
                    //////////
                    $('<button />').attr({
                            id: "valider",
                            type: "button",
                            value: "true",
                            class: "btn"
                        })
                        .text(settings.lang.validate_button)
                        .appendTo('#' + traitement.id, settings.modal)
                        .on('click', {
                            traitement: traitement
                        }, function (event) {
                            event.data.traitement.validate();
                        });
                    $('<button />').attr({
                            id: "annuler",
                            type: "button",
                            value: "false",
                            class: "btn"
                        })
                        .text(settings.lang.cancel_button)
                        .appendTo('#' + traitement.id, settings.modal)
                        .on('click', function () {
                            setSelectedTraitement(null)
                        });


                    /////////
                    //  Nubs (position sur l'image)
                    /////////
                    var nub_present = false;
                    for (var j = 0; j < traitement.nubs.length; j++) {
                        var nub = traitement.nubs[j];
                        var x = nub.x * canvas_glfx.width;
                        var y = nub.y * canvas_glfx.height;
                        traitement[nub.id] = {
                            x: x,
                            y: y,
                            reel_x: x / ratio_image,
                            reel_y: y / ratio_image
                        };
                        if (nub_present == false) {
                            nub_present = true;
                        }
                    }

                    if (traitement.reset) {
                        traitement.reset();
                    }
                }
            }
        } else {
            $('<div />').text(settings.lang.error_glfx_support_msg).css({
                "text-align": "center",
                "color": "red"
            }).appendTo('#traitement_zone', settings.modal);
        }

        $('#loading_circle', settings.modal).hide();
    }

    function setSelectedTraitement(traitement) {
        $('#image_zone .nub', settings.modal).remove();
        $(window).off('resize', actualisePos);
        $(window).off('orientationchange', actualisePos);
        $('body').off('touchend mouseup'); // pour les node
        if (!canvas_glfx) {
            return;
        }
        $('#traitement_parametre > div', settings.modal).removeClass('active');
        if (traitement == null) {
            image_affiche.src = canvas_traitement.toDataURL('image/png');
            $('#traitement_parametre', settings.modal).hide();
            $('#traitement', settings.modal).show();
            $(".modal-footer #button-action", settings.modal).removeClass("traitement-no-validate");
            return;
        }


        $(canvas_glfx).remove();
        delete canvas_glfx;
        canvas_glfx = fx.canvas();
        canvas_glfx.height = canvas_traitement.height;
        canvas_glfx.width = canvas_traitement.width;;
        if (texture) {
            texture.destroy();
        }
        texture = canvas_glfx.texture(canvas_traitement);
        canvas_glfx.draw(texture).update();


        image_affiche.src = canvas_traitement.toDataURL('image/png');
        $('#' + traitement.id, settings.modal).addClass('active');
        $('#traitement_parametre', settings.modal).show();
        $('#traitement', settings.modal).hide();

        // Reset all sliders
        for (var i = 0; i < traitement.sliders.length; i++) {
            var slider = traitement.sliders[i];
            $('#' + traitement.id + ' #' + slider.id, settings.modal).val(parseFloat(slider.value));
            traitement[slider.id] = parseFloat(slider.value);
        }


        // Generate all nubs
        if (traitement.nubs.length) {
            $(image_affiche).parent().css({
                position: 'relative'
            });
        }
        for (var i = 0; i < traitement.nubs.length; i++) {
            var nub = traitement.nubs[i];

            // position par rapport à la taille affichée
            var x = nub.x * image_affiche.naturalWidth;
            var y = nub.y * image_affiche.naturalHeight;
            $('<div class="nub" id="' + nub.id + '"></div>').insertBefore($('#image', settings.modal));

            /////////
            //  Event pour le déplacement des nubs
            /////////

            var ontouchmove = (function (event) {
                ////	TACTILE
                var e = event.originalEvent;
                event.preventDefault();
                var nub = event.data.nub;
                var $nub = $(nub);
                var $img = $(image_affiche);

                var position = {
                    img: {
                        // position par rapport à l'affichage de l'écran
                        offset: $img.offset(),

                        // position exact de l'image dans son container
                        //      (espace présent sur les cotés de l'image) / 2 (pour un seul coté)
                        display: {
                            left: ($img.parent().width() - $img.width()) / 2,
                            top: ($img.parent().height() - $img.height()) / 2,

                        }
                    },
                    nub: {
                        //position relative au container #image_zone
                        css: $nub.position(),
                    }
                }
                // position du nub par rapport a l'image
                position.nub.img = {
                    left: position.nub.css.left - position.img.display.left,
                    top: position.nub.css.top - position.img.display.top
                }

                // nouvelles position fix par rapport à la taille naturel de l'image
                var x = (e.touches[0].pageX - position.img.offset.left) * (image_affiche.naturalWidth / $img.width())
                var y = (e.touches[0].pageY - position.img.offset.top) * (image_affiche.naturalWidth / $img.width())

                // ne pas sortir des bords  de l'image ?
                if (x < 0)
                    x = 0;
                if (x > image_affiche.naturalWidth)
                    x = image_affiche.naturalWidth;
                if (y < 0)
                    y = 0;
                if (y > image_affiche.naturalHeight)
                    y = image_affiche.naturalHeight;

                // nouvelle position de nub
                //                      ( position réel  * ratio de l'image affiché) + la vrai position de l'image
                position.nub.css.left = (x * ($img.width() / image_affiche.naturalWidth)) + position.img.display.left;
                position.nub.css.top = (y * ($img.height() / image_affiche.naturalHeight)) + position.img.display.top;
                //$.map({x:x,y:y,position:position}, (val,key) => {console.log(key +' : ' + val);if(val instanceof Object){ console.log(JSON.stringify(val)) }});
                //console.log('-----------------------------FIN----------------------------');

                $nub.css(position.nub.css);

                traitement[nub.id] = {
                    x: x,
                    y: y,
                    reel_x: x / ratio_image,
                    reel_y: y / ratio_image
                };

                // activer ou désactiver le traitement continue pour les ecrans tactiles
                //traitement.update();
            });
            var onmousemove = (function (event) {
                ////	SOURIS
                var offset = $(event.target).offset();
                var nub = event.data.nub;
                var $nub = $(nub);
                var $img = $(image_affiche);

                var position = {
                    img: {
                        // position par rapport à l'affichage de l'écran
                        offset: $img.offset(),

                        // position exact de l'image dans son container
                        //      (espace présent sur les cotés de l'image) / 2 (pour un seul coté)
                        display: {
                            left: ($img.parent().width() - $img.width()) / 2,
                            top: ($img.parent().height() - $img.height()) / 2,

                        }
                    },
                    nub: {
                        //position relative au container #image_zone
                        css: $nub.position(),
                    }
                }
                // position du nub par rapport a l'image
                position.nub.img = {
                    left: position.nub.css.left - position.img.display.left,
                    top: position.nub.css.top - position.img.display.top
                }
                // nouvelles position fix par rapport à la taille naturel de l'image
                var x = (event.pageX - position.img.offset.left) * (image_affiche.naturalWidth / $img.width())
                var y = (event.pageY - position.img.offset.top) * (image_affiche.naturalWidth / $img.width())

                // ne pas sortir des bords  de l'image ?
                if (x < 0)
                    x = 0;
                if (x > image_affiche.naturalWidth)
                    x = image_affiche.naturalWidth;
                if (y < 0)
                    y = 0;
                if (y > image_affiche.naturalHeight)
                    y = image_affiche.naturalHeight;

                // nouvelle position de nub
                //                      ( position réel  * ratio de l'image affiché) + la vrai position de l'image
                position.nub.css.left = (x * ($img.width() / image_affiche.naturalWidth)) + position.img.display.left;
                position.nub.css.top = (y * ($img.height() / image_affiche.naturalHeight)) + position.img.display.top;
                //$.map({x:x,y:y,position:position}, (val,key) => {console.log(key +' : ' + val);if(val instanceof Object){ console.log(JSON.stringify(val)) }});
                //console.log('-----------------------------FIN----------------------------');

                $nub.css(position.nub.css);

                traitement[nub.id] = {
                    x: x,
                    y: y,
                    reel_x: x / (ratio_image * (image_affiche.width / image_affiche.naturalWidth)),
                    reel_y: y / (ratio_image * (image_affiche.height / image_affiche.naturalHeight))
                };
                traitement.update();
            });

            /////////
            //  Atribution des events
            ////////

            //	TACTILE
            $('#' + nub.id, settings.modal).on('touchstart', function (event) {
                $('body').on('touchmove', {
                    nub: event.target
                }, ontouchmove);
            });
            $('body').on('touchend', function (event) {
                $('body').off('touchmove', ontouchmove);
                traitement.update();
            });

            //	SOURIS
            $('#' + nub.id, settings.modal).mousedown(function (event) {
                $('body').on('mousemove', {
                    nub: event.target
                }, onmousemove);
            });
            $('body').mouseup(function (event) {
                $('body').off('mousemove', onmousemove);
            });

            var actualisePos = function (event) {
                var traitement = event.data.traitement;
                var nub = event.data.nub;
                var $nub = $('#' + nub.id, settings.modal);
                var $img = $(image_affiche);

                var position = {
                    img: {
                        // position par rapport à l'affichage de l'écran
                        offset: $img.offset(),

                        // position exact de l'image dans son container
                        //      (espace présent sur les cotés de l'image) / 2 (pour un seul coté)
                        display: {
                            left: ($img.parent().width() - $img.width()) / 2,
                            top: ($img.parent().height() - $img.height()) / 2,

                        }
                    },
                    nub: {
                        //position relative au container #image_zone
                        css: $nub.position(),
                    }
                }
                // position du nub par rapport a l'image
                position.nub.img = {
                    left: position.nub.css.left - position.img.display.left,
                    top: position.nub.css.top - position.img.display.top
                }

                var x = traitement[nub.id].x;
                var y = traitement[nub.id].y;

                position.nub.css.left = (x * ($img.width() / image_affiche.naturalWidth)) + position.img.display.left;
                position.nub.css.top = (y * ($img.height() / image_affiche.naturalHeight)) + position.img.display.top;

                $nub.css(position.nub.css);
            };

            $(window).on('orientationchange resize', {
                nub: nub,
                traitement: traitement
            }, actualisePos);


            traitement[nub.id] = {
                x: x,
                y: y,
                reel_x: x / (ratio_image * (image_affiche.width / image_affiche.naturalWidth)),
                reel_y: y / (ratio_image * (image_affiche.height / image_affiche.naturalHeight))
            };
            actualisePos({
                data: {
                    nub: nub,
                    traitement: traitement
                }
            });

        }
        traitement.update(); /// la taille naturelle est redéfini par la texture glfx donc calcul des pos après
    }

    function crop() {
        // Cropper(http://fengyuanchen.github.io/cropper/)
        $("#crop-ratio-list input:checked", settings.modal).prop('checked', false).parent().toggleClass('active');
        $('#loading_circle', settings.modal).show();
        //$('#image_zone #image', settings.modal).cropper('clear');
        $('#loading_circle', settings.modal).hide();
        //$(".modal-footer #button-action", settings.modal).addClass("traitement-no-validate");
    }

    function cropValidation(etat) {
        $('#loading_circle', settings.modal).show();
        if (etat == "true") {
            var canvasData = $("#image_zone #image").cropper("getCanvasData");
            var imageData = $("#image_zone #image").cropper("getImageData");
            var data = $("#image_zone #image").cropper("getData");
            $('#crop rotate-input', settings.modal).val(0);
            if (data.height !== 0 && data.width !== 0) {
                //récupérer les pos et crop preview et reel
                var newData = {
                    width: data.width / ratio_image,
                    height: data.height / ratio_image,
                    x: data.x / ratio_image,
                    y: data.y / ratio_image,
                    rotate: data.rotate
                };
                var div = $('<div></div>').insertAfter(settings.modal);
                $(image_modif).appendTo(div);
                $(image_modif).one("built.cropper", function () {
                    div.hide();
                    $(image_modif).cropper('setData', newData);
                    $(image_modif).cropper('setCanvasData', canvasData);
                    $(image_modif).cropper('setData', newData); /// Il y a une dépendence entre Data et Canvas Data (principalement pour la rotation) donc on réaplique avec les nouveaux param du canvas
                    var canvas = $(image_modif).cropper('getCroppedCanvas');
                    //var canvas = $(image_modif).cropper().cropper('setCanvasData', canvasData).cropper('setData', newData).cropper('getCroppedCanvas');
                    $(image_modif).cropper('destroy');
                    $(image_modif).detach();
                    div.remove();
                    image_modif.src = canvas.toDataURL('image/' + settings.formatImageSave, 1);
                }).cropper();

                //canvas_traitement.width = data.width / ratio_image;
                //canvas_traitement.height = data.height / ratio_image;
                //canvas_traitement.getContext("2d").drawImage(image_modif, -data.x / ratio_image, -data.y / ratio_image);
                //image_modif.src = canvas_traitement.toDataURL("image/" + settings.formatImageSave, 1);

                $('#crop #rotate-input', settings.modal).val(180);
                $('#image_zone #image', settings.modal).cropper("destroy");
                modifNoSave = true;
                $('#crop label.active:has(input)', settings.modal).removeClass('active').find('input').prop('checked', false);
            }
        } else if (etat == "false") {
            $("#crop-ratio-list input:checked", settings.modal).prop('checked', false).parent().toggleClass('active');
            $('#crop #rotate-input', settings.modal).val(180);
            $('#image_zone #image', settings.modal).cropper('destroy')
        } else {
            $('#cropper button#annuler').click();
            $('#image_zone #image', settings.modal).cropper("destroy");
        }
        $('#cropper > #crop #validation').hide();
        //$('#famille li', settings.modal).removeClass("active");
        //$('.tab-content div', settings.modal).removeClass("active");
        $('#loading_circle', settings.modal).hide();
        //$(".modal-footer #button-action", settings.modal).removeClass("traitement-no-validate");
    }

    function annuler() {
        resizeCanvasImage(image_modif, canvas_traitement, 550, 550);
        cropValidation(null);
        filtreValidation("false");
        borderValidation("false");
        setSelectedTraitement(null);
    }

    function upload() {
        $('#loading_circle', settings.modal).show();
        var canvas_rendu_final = document.createElement('canvas');
        canvas_rendu_final.width = image_modif.width;
        canvas_rendu_final.height = image_modif.height;
        canvas_rendu_final.getContext('2d').drawImage(image_modif, 0, 0);

        var url = canvas_rendu_final.toDataURL("image/" + settings.formatImageSave, 1);
        var format = "";
        for (var i = 0; i < format_possible.length; i++) {
            format += format_possible[i];
            if (i != format_possible.length - 1) {
                format += '|';
            }
        }
        var regex = new RegExp("^data:image/(" + format + ");base64,");
        url = url.replace(regex, "");
        $(regex).remove();
        delete regex;

        $('#progressBar', settings.modal).css('width', '0%').attr('aria-valuenow', 0).text("0 %");
        $('#close_msg', settings.modal).text(settings.lang.close_uploading_msg);
        $('#progressBar', settings.modal).parent().show();
        uploading = $.ajax({
            type: 'POST',
            url: settings.urlServeur,
            data: $.extend({}, settings.uploadData, {
                "formatImageSave": settings.formatImageSave,
                'imageName': settings.imageName,
                "imageData": url,
                'imageWidth': image_modif.width,
                'imageHeight': image_modif.height
            }),
            success: function (msg) {
                uploading = false;
                $('#close_msg', settings.modal).text(settings.lang.close_msg);
                $('#loading_circle', settings.modal).hide();
                modifNoSave = false;
                $('#progressBar', settings.modal).parent().hide();
                settings.onUpload(msg);
            },
            error: function (msg) {
                uploading = false;
                $('#close_msg', settings.modal).text(settings.lang.close_msg);
                settings.onUploadError(msg);
            },
            xhr: function () {
                var xhr = new window.XMLHttpRequest();
                //Upload progress
                xhr.upload.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        //Do something with upload progress
                        $('#progressBar', settings.modal).css('width', percentComplete + '%').attr('aria-valuenow', percentComplete).text((percentComplete).toFixed(1) + " %");
                        //console.log("up : "+percentComplete);
                    }
                }, false);
                //Download progress
                xhr.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total;
                        //Do something with download progress
                        //console.log("down : "+percentComplete);
                    }
                }, false);
                return xhr;
            }
        });

        delete canvas_rendu_final;
    }

    ////////////////////////////////////
    //	Traitements et Filtres	  //
    ////////////////////////////////////

    function Traitement(id, init, update, validate, flip_canvas, label) {
        if (typeof (label) != 'string') {
            if (settings.lang[id]) {
                label = settings.lang[id];
            } else {
                label = id;
            }
        }
        this.label = label;
        this.id = id;
        this.update = update;
        this.sliders = [];
        this.nubs = [];
        this.flip_canvas = flip_canvas;
        this.validate = validate;
        this.previewReel = false;
        init.call(this);
    }

    Traitement.prototype.addSlider = function (id, min, max, value, step, label) {
        if (typeof (label) != 'string') {
            if (settings.lang[id]) {
                label = settings.lang[id];
            } else {
                label = id;
            }
        }
        this.sliders.push({
            id: id,
            label: label,
            min: min,
            max: max,
            value: value,
            step: step
        });
    };

    Traitement.prototype.addNub = function (id, x, y) {
        this.nubs.push({
            id: id,
            x: x,
            y: y
        });
    };

    var flip = false;
    for (var i = 0; i < devices_glfx_flip.length; i++) {
        var device = devices_glfx_flip[i];
        if (navigator.platform === device) {
            flip = true;
            break;
        }
    }

    function applyPreview(traitement) {
        if (traitement.previewReel) {
            var canvas_temp = document.createElement('canvas');
            var image_temp = new Image();
            $(image_temp).load({
                image_temp: image_temp,
                canvas_temp: canvas_temp
            }, function (event) {
                var image_temp = event.data.image_temp;
                var canvas_temp = event.data.canvas_temp;
                canvas_temp.getContext('2d').drawImage(image_temp, 0, 0);
                resizeCanvasImage(image_temp, canvas_temp, 550, 550);
                image_affiche.src = canvas_temp.toDataURL('image/png');
                canvas_temp.remove();
                delete canvas_temp;
                image_temp.remove();
                delete image_temp;
            });
            image_temp.src = canvas_glfx.toDataURL('image/png');
        } else {
            if (traitement.flip_canvas) {
                var canvas_flip = document.createElement('canvas');
                canvas_flip.height = canvas_glfx.height;
                canvas_flip.width = canvas_glfx.width;
                canvas_flip.getContext('2d').drawImage(canvas_glfx, 0, 0);
                image_affiche.src = canvas_flip.toDataURL("image/png");
                $(canvas_flip).remove();
                delete canvas_flip;
            } else {
                image_affiche.src = canvas_glfx.toDataURL("image/png");
            }
        }
        //canvas_traitement.getContext('2d').drawImage(canvas_glfx,0,0);
        $(".modal-footer #button-action", settings.modal).addClass("traitement-no-validate");
    }

    function applyReal(traitement) {
        if (this.flip_canvas) {
            var canvas_flip = document.createElement('canvas');
            canvas_flip.height = canvas_glfx.height;
            canvas_flip.width = canvas_glfx.width;
            canvas_flip.getContext('2d').drawImage(canvas_glfx, 0, 0);
            image_modif.src = canvas_flip.toDataURL("image/" + settings.formatImageSave, 1);
            $(canvas_flip).remove();
            delete canvas_flip;
        } else {
            image_modif.src = canvas_glfx.toDataURL("image/" + settings.formatImageSave, 1);
        }
        //le canvas est cassé donc on le reinit
        $(canvas_glfx).remove();
        delete canvas_glfx;
        canvas_glfx = fx.canvas();
        canvas_glfx.height = image_affiche.naturalHeight;
        canvas_glfx.width = image_affiche.naturalWidth;;

        modifNoSave = true;
        $('#traitement_parametre', settings.modal).hide();
        $('#traitement', settings.modal).show();
        $(window).off('resize');
        $(window).off('orientationchange');
        $('#image_zone .nub', settings.modal).remove();
        $('#traitement_parametre > div', settings.modal).removeClass('active');
        $(".modal-footer #button-action", settings.modal).removeClass("traitement-no-validate");
    }


    var traitements = null;
    var filtres = null;

    function initTraitements() {

        filtres = [{
                id: "vintage",
                label: settings.lang.vintage
            },
            {
                id: "lomo",
                label: settings.lang.lomo
            },
            {
                id: "clarity",
                label: settings.lang.clarity
            },
            {
                id: "sinCity",
                label: settings.lang.sinCity
            },
            {
                id: "sunrise",
                label: settings.lang.sunrise
            },
            {
                id: "crossProcess",
                label: settings.lang.crossProcess
            },
            {
                id: "orangePeel",
                label: settings.lang.orangePeel
            },
            {
                id: "love",
                label: settings.lang.love
            },
            {
                id: "grungy",
                label: settings.lang.grungy
            },
            {
                id: "jarques",
                label: settings.lang.jarques
            },
            {
                id: "pinhole",
                label: settings.lang.pinhole
            },
            {
                id: "oldBoot",
                label: settings.lang.oldBoot
            },
            {
                id: "glowingSun",
                label: settings.lang.glowingSun
            },
            {
                id: "hazyDays",
                label: settings.lang.hazyDays
            },
            {
                id: "herMajesty",
                label: settings.lang.herMajesty
            },
            {
                id: "nostalgia",
                label: settings.lang.nostalgia
            },
            {
                id: "hemingway",
                label: settings.lang.hemingway
            },
            {
                id: "concentrate",
                label: settings.lang.concentrate
            }
        ];

        traitements = [
            new Traitement('brightnessContrast', function () {
                this.addSlider('brightness', -1, 1, 0, 0.1);
                this.addSlider('contrast', -1, 1, 0, 0.1);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).brightnessContrast(this.brightness, this.contrast).update();
                } else {
                    canvas_glfx.draw(texture).brightnessContrast(this.brightness, this.contrast).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).brightnessContrast(this.brightness, this.contrast).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('hueSaturation', function () {
                this.addSlider('hue', -1, 1, 0, 0.01);
                this.addSlider('saturation', -1, 1, 0, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).hueSaturation(this.hue, this.saturation).update();
                } else {
                    canvas_glfx.draw(texture).hueSaturation(this.hue, this.saturation).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).hueSaturation(this.hue, this.saturation).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('vibrance', function () {
                this.addSlider('amount', -1, 1, 0, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).vibrance(this.amount).update();
                } else {
                    canvas_glfx.draw(texture).vibrance(this.amount).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).vibrance(this.amount).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('denoise', function () {
                this.addSlider('exponent', 0, 50, 20, 1);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).denoise(this.exponent).update();
                } else {
                    canvas_glfx.draw(texture).denoise(this.exponent + ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).denoise(this.exponent).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('unsharpMask', function () {
                this.addSlider('radius', 0, 200, 20, 1);
                this.addSlider('strength', 0, 5, 2, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).unsharpMask(this.radius, this.strength).update();
                } else {
                    canvas_glfx.draw(texture).unsharpMask(this.radius * ratio_image, this.strength).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).unsharpMask(this.radius, this.strength).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('noise', function () {
                this.addSlider('amount', 0, 1, 0.5, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).noise(this.amount).update();
                } else {
                    canvas_glfx.draw(texture).noise(this.amount * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).noise(this.amount).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('sepia', function () {
                this.addSlider('amount', 0, 1, 1, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).sepia(this.amount).update();
                } else {
                    canvas_glfx.draw(texture).sepia(this.amount).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).sepia(this.amount).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('vignette', function () {
                this.addSlider('amount', 0, 1, 0.5, 0.01);
                this.addSlider('size', 0, 1, 0.5, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).vignette(this.size, this.amount).update();
                } else {
                    canvas_glfx.draw(texture).vignette(this.size, this.amount).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).vignette(this.size, this.amount).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            //////////////////////////////////////////////////////
            new Traitement('zoomBlur', function () {
                this.addNub('center', 0.5, 0.5);
                this.addSlider('strength', 0, 1, 0.3, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).zoomBlur(this.center.reel_x, this.center.reel_y, this.strength).update();
                } else {
                    canvas_glfx.draw(texture).zoomBlur(this.center.x, this.center.y, this.strength).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).zoomBlur(this.center.reel_x, this.center.reel_y, this.strength).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('tiltShift', function () {
                this.addNub('start', 0.15, 0.75);
                this.addNub('end', 0.75, 0.6);
                this.addSlider('blurRadius', 0, 50, 15, 1);
                this.addSlider('gradientRadius', 0, 400, 200, 1);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).tiltShift(this.start.reel_x, this.start.reel_y, this.end.reel_x, this.end.reel_y, this.blurRadius, this.gradientRadius).update();
                } else {
                    canvas_glfx.draw(texture).tiltShift(this.start.x, this.start.y, this.end.x, this.end.y, this.blurRadius * ratio_image, this.gradientRadius * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).tiltShift(this.start.reel_x, this.start.reel_y, this.end.reel_x, this.end.reel_y, this.blurRadius, this.gradientRadius).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('triangleBlur', function () {
                this.addSlider('radius', 0, 200, 50, 1);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).triangleBlur(this.radius).update();
                } else {
                    canvas_glfx.draw(texture).triangleBlur(this.radius * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).triangleBlur(this.radius).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('lensBlur', function () {
                this.addSlider('radius', 0, 50, 10, 1);
                this.addSlider('brightness', -1, 1, 0.75, 0.01);
                this.addSlider('angle', -3.14, 3.14, 0, 0.01);
                //-Math.PI, Math.PI, 0, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).lensBlur(this.radius, this.brightness, this.angle).update();
                } else {
                    canvas_glfx.draw(texture).lensBlur(this.radius * ratio_image, this.brightness, this.angle).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).lensBlur(this.radius, this.brightness, this.angle).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('swirl', function () {
                this.addSlider('radius', 0, 600, 200, 1);
                this.addSlider('angle', -25, 25, 3, 0.1);
                this.addNub('center', 0.5, 0.5);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).swirl(this.center.reel_x, this.center.reel_y, this.radius / ratio_image, this.angle).update();
                } else {
                    canvas_glfx.draw(texture).swirl(this.center.x, this.center.y, this.radius, this.angle).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).swirl(this.center.reel_x, this.center.reel_y, this.radius / ratio_image, this.angle).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('bulgePinch', function () {
                this.addSlider('radius', 0, 600, 200, 1);
                this.addSlider('strength', -1, 1, 0.5, 0.01);
                this.addNub('center', 0.5, 0.5);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).bulgePinch(this.center.reel_x, this.center.reel_y, this.radius, this.strength).update();
                } else {
                    canvas_glfx.draw(texture).bulgePinch(this.center.x, this.center.y, this.radius * ratio_image, this.strength).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).bulgePinch(this.center.reel_x, this.center.reel_y, this.radius, this.strength).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('perspective', function () {
                this.addNub('a', 0.25, 0.25);
                this.addNub('b', 0.75, 0.25);
                this.addNub('c', 0.25, 0.75);
                this.addNub('d', 0.75, 0.75);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    this.before = [0, 0, image_modif.width, 0, 0, image_modif.height, image_modif.width, image_modif.height];
                    this.after = [this.a.reel_x, this.a.reel_y, this.b.reel_x, this.b.reel_y, this.c.reel_x, this.c.reel_y, this.d.reel_x, this.d.reel_y];
                    //this.after = [this.a.x,this.a.y,this.b.x,this.b.y,this.c.x,this.c.y,this.d.x,this.d.y];
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).perspective(this.before, this.after).update();
                } else {
                    this.after = [this.a.x, this.a.y, this.b.x, this.b.y, this.c.x, this.c.y, this.d.x, this.d.y];
                    this.before = [0, 0, image_affiche.naturalWidth, 0, 0, image_affiche.naturalHeight, image_affiche.naturalWidth, image_affiche.naturalHeight];
                    canvas_glfx.draw(texture).perspective(this.before, this.after).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                this.before = [0, 0, image_modif.width, 0, 0, image_modif.height, image_modif.width, image_modif.height];
                this.after = [this.a.reel_x, this.a.reel_y, this.b.reel_x, this.b.reel_y, this.c.reel_x, this.c.reel_y, this.d.reel_x, this.d.reel_y];
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).perspective(this.before, this.after).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('ink', function () {
                this.addSlider('strength', -1, 1, 0, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).ink(this.strength).update();
                } else {
                    canvas_glfx.draw(texture).ink(this.strength * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).ink(this.strength).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('edgeWork', function () {
                this.addSlider('radius', 0, 200, 10, 1);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).edgeWork(this.radius).update();
                } else {
                    canvas_glfx.draw(texture).edgeWork(this.radius * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).edgeWork(this.radius).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('hexagonalPixelate', function () {
                this.addNub('center', 0.5, 0.5);
                this.addSlider('scale', 10, 100, 20, 1);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).hexagonalPixelate(this.center.reel_x, this.center.reel_y, this.scale).update();
                } else {
                    canvas_glfx.draw(texture).hexagonalPixelate(this.center.x, this.center.y, this.scale * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                ///	REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).hexagonalPixelate(this.center.reel_x, this.center.reel_y, this.scale).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('dotScreen', function () {
                this.addNub('center', 0.5, 0.5);
                this.addSlider('angle', 0, Math.PI / 2, 1.1, 0.01);
                this.addSlider('size', 3, 20, 3, 0.01);
            }, function () {
                ///	PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).dotScreen(this.center.reel_x, this.center.reel_y, this.angle, this.size).update();
                } else {
                    canvas_glfx.draw(texture).dotScreen(this.center.x, this.center.y, this.angle, this.size * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                /// REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).dotScreen(this.center.reel_x, this.center.reel_y, this.angle, this.size).update();
                applyReal(this);
                $('#loading_circle', settings.modal).hide();
            }, flip, null),
            /////////////////////////////////////////////////////
            new Traitement('colorHalftone', function () {
                this.addNub('center', 0.5, 0.5);
                this.addSlider('angle', 0, Math.PI / 2, 0.25, 0.01);
                this.addSlider('size', 3, 20, 4, 0.01);
            }, function () {
                /// PREVIEW
                $('#loading_circle', settings.modal).show();
                if (this.previewReel) {
                    canvas_glfx.draw(canvas_glfx.texture(image_modif)).colorHalftone(this.center.reel_x, this.center.reel_y, this.angle, this.size).update();
                } else {
                    canvas_glfx.draw(texture).colorHalftone(this.center.x, this.center.y, this.angle, this.size * ratio_image).update();
                }
                applyPreview(this);
                $('#loading_circle', settings.modal).hide();
            }, function () {
                /// REAL
                $('#loading_circle', settings.modal).show();
                canvas_glfx.draw(canvas_glfx.texture(image_modif)).colorHalftone(this.center.reel_x, this.center.reel_y, this.angle, this.size).update();
                applyReal(this);;

                $('#loading_circle', settings.modal).hide();
            }, flip, null)
        ];
    }

    delete flip;
    delete device;
}(jQuery));