# ImageEditor
---
ImageEditor is a jQuery plugin to edit images using canvas.
It works with [Bootstrap](http://getbootstrap.com/) ([License](https://github.com/twbs/bootstrap/blob/master/LICENSE)), [CamanJS](http://camanjs.com/) ([License](https://github.com/meltingice/CamanJS/blob/master/LICENSE)), [Glfx](http://evanw.github.io/glfx.js/) ([License](https://github.com/evanw/glfx.js/blob/master/LICENSE)) and [Cropper](http://fengyuanchen.github.io/cropper/) ([License](https://github.com/fengyuanchen/cropper/blob/master/LICENSE)).

## Summary
[Installation](home/#toc_2)
[ How to use it ?](home/#toc_3)

- [Include files](home/#toc_4)
- [Usage](home/#toc_5)
  - [Options](home/#toc_6)
  - [Action](home/#toc_7)
  - [Selector](home/#toc_8)
- [Personalised](home/#toc_9)
  - [Languages](home/#toc_10)
  - [Don't display some filters or processings](home/#toc_11)
  - [Borders](home/#toc_12)
- [Upload](home/#toc_13)
- [Examples](home/#toc_14)

## [Installation](home/#toc_1)
First clone image-editor using git :

```html
git clone git@git.xsalto.com:vdubois/image-editor.git
```
Or copy the three image-editor files and the two folders to your website.

```html
image-editor.js
image-editor.css
image-editor.html
dependence/
├──  caman.full.js
├──  cropper.min.css
├──  cropper.min.js
├──  glfx.js
├──  bootstrap.LICENSE
├──  caman.js.LICENSE
├──  cropper.LICENSE
├──  glfx.LICENSE
├──  loading_circle.gif 
└──  background.png
lang/
├──  fr.js
└──  en.js
```

## [How to use it ?](home/#toc_1)
:warning: ImageEditor need [Bootstrap](http://getbootstrap.com/).

### [Include files](home/#toc_1)

```html
<link  href="/path/to/image-editor-folder/image-editor.css" rel="stylesheet">
<script src="/path/to/image-editor-folder/image-editor.js"></script>
```
### [Usage](home/#toc_1)

Just use `$( [selector] ).imageEditor( [options], [action] )` method.
ImageEditor's modal is created if there is no one already.

#### [Options](home/#toc_1)

| Parameters | Descriptions |
| ----------------- | ------------------ |
| urlImage | Url of the image you want to edit |
| formatImageSave | Format you want to save the final image. :warning: Only support **png** (manages transparancy), **jpeg** and **webp**. :information_source: Default : png . |
| imageName | Name of the image that will be edited. With or without extension. :information_source: Default : image . |
| lang | Set one of languages that are present in folder lang. :warning: Languages are loaded at the creation of the modal. :information_source: Default : fr . |
| maxHeight | Set the maximum height of the image. Image out of limits will be resized. :warning: Maximum : 4096 . :information_source: Default : 4096 . |
| maxWidth | Set the maximum width of the image. Image out of limits will be resized. :warning: Maximum : 4096 . :information_source: Default : 4096 . |
| urlServeur | Url of server that will receive the image when uploaded. |
| uploadData | Object of data you want to transmit to the server using POST request. |

| Callbacks | Parameters | Descriptions |
| -------------- | ----------------- | ------------------ |
| onShow | | Call when the bootstrap modal has been open. |
| onHide | | Call when the bootstrap modal has been hid. |
| onUpload | Server's message | Call when the image has been uploaded. |
| onUploadError | Error message | Call when the image has been uploaded. |
| onGlfxNoSupport | | Call if canvas glfx no supported on the browser. :information_source: Default : alert message. |
| onLoadScriptError | Url script | Call when a script fail to load. :information_source: Default : alert message. |
| onLoadLangError | Error message | Call when the language fail to load. :information_source: Default : alert message. |
| onLoadImageError | | Call when the image fail to load. |

#### [Action](home/#toc_1)

:information_source: By default, action is 'show' if there is an image's url pass to urlImage.

| Actions | Descriptions |
| ----------- | ------------------ |
| 'show' | Display ImageEditor. |
| 'hide' | Hide ImageEditor. |
| 'remove' | Remove ImageEditor's modal from the DOM and reset all options that was passed to this function. |

#### [Selector](home/#toc_1)

The selector is only useful at the first call of ImageEditor, it permit to specify where the modal must be created.
:information_source: By default it is created, at the end of `body`.

### [Personalised](home/#toc_1)

#### [Languages](home/#toc_1)

ImageEditor gives the possibility to modify each text it manage.

To do that use one of default languages in the folder lang as example and change what you want like this : 

```javascript
$().imageEditor.personaliseLang = {
    title: 'My new title',
	loading_image_msg: 'Please, wait a moment...' 
	};
```
:warning: Languages are loaded at the creation of the modal.

#### [Don't display some filters or processings](home/#toc_1)

If you want to remove some filters or image processings of the user's possibility, get the ids of what you want to remove in one of default languages in the folder lang, and transfer them to imageEditor by this way :

```javascript
$().imageEditor.noDisplayFiltres = [
	'sinCity',
	'love',
	'nostalgia'
	];

$().imageEditor.noDisplayTraitements = [
	'noise',
	'hueSaturation',
	'zoomBlur'
	];
```

#### [Borders](home/#toc_1)

The list of borders can be defined by editing the file `border/index.js` or dynamically inside the page using ImageEditor.
A border is defined by an object with this attributes :

| Attribute | Description |
| ---------------------- | ---------------- |
| id | Border identifier :warning: Must be unique :information_source: Will disappear in a future version |
| file | Url of the border. Could be relative or absolute |

### [Upload](home/#toc_1)

If you want to give the possibility to upload the image to your server, use the file `upload.php` as example.
:information_source: You can add information to send your server using `GET requests` in the parameter `urlServeur`.

| POST requests | Description |
| ---------------------- | ---------------- |
| formatImageSave | Extension of the image |
| imageName | Name of the image |
| imageData | Data in base64 of the image |
| imageHeight | Height of the image |
| imageWidth | Width of the image |

There is also the data you have specify in uploadData option.

### [Examples](home/#toc_1)

#### When the modal is not created


Create the modal at the default placement :

```javascript
$().imageEditor();
```

Create the modal inside the id `hereYouGo` :

```javascript
$('#hereYouGo').imageEditor();
```


Create the modal inside the id `hereYouGo` and `edit image-test`, with the `format jpeg` :

```javascript
$('#hereYouGo').imageEditor({
	urlImage: 'http://my.fake.web/image-test.png',
	formatImageSave: 'jpeg'
	});
```


Create the modal inside the id `hereYouGo` and `don't show` it but create an alert `at the opening` :

```javascript
$('#hereYouGo').imageEditor({
	urlImage: 'http://my.fake.web/image-test.png',
	onShow: function(){
		alert('Opened');
	}
	}, 'hide' );
```


#### When the modal is already created

Hide the modal if it is opened :

```javascript
$().imageEditor( 'hide' );
```

Show the modal if it is hided :

```javascript
$().imageEditor( 'show' );
```

Load new options :

```javascript
$().imageEditor( {
	urlImage: 'http://another.image/test.jpg' ,
	urlServeur: 'http://my.serveur.test/upload.php?user="test"&val=2' ,
	uploadData: { password: 'azerty' },
	onUpload: function(msg){
		if( msg == 'OK' ){
			$().imageEditor( 'hide' );
		}
	}
	} );
```

Remove the modal from the DOM :

```javascript
$().imageEditor( 'remove' );
```

