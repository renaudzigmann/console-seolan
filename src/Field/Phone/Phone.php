<?php
namespace Seolan\Field\Phone;

use \libphonenumber\PhoneNumberFormat;
use \libphonenumber\PhoneNumberUtil;
use Seolan\Core\Labels;
use Seolan\Field\ShortText\ShortText;
use Seolan\Pack\Phone\Phone as PhonePack;

class Phone extends ShortText {
  public $type = "tel";
  public $generate_link = true;
  public $display_format = PhoneNumberFormat::NATIONAL;
  public $possible_format = array(PhoneNumberFormat::E164, PhoneNumberFormat::INTERNATIONAL, PhoneNumberFormat::NATIONAL, PhoneNumberFormat::RFC3966);

  function initOptions() {
    parent::initOptions();

    $this->_options->delOpt('separator');
    $this->_options->delOpt('display_format');
    $this->_options->delOpt('edit_format');
    $this->_options->delOpt('edit_format_text');
    $this->_options->delOpt('listbox');
    $this->_options->delOpt('boxsize');
    $this->_options->delOpt('listbox_limit');
    $this->_options->delOpt('with_confirm');
    $this->_options->delOpt('autocomplete');
    $this->_options->delOpt('autocompleteRelatedFields');

    $viewgroup = Labels::getTextSysLabel('Seolan_Core_General','view');
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','display_format'), 'display_format', 'list', array('values'=>$this->possible_format,'labels'=>['E164', 'INTERNATIONAL', 'NATIONAL', 'RFC3966']), NULL, $viewgroup);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','generate_link'), 'generate_link', 'boolean', NULL, NULL, $viewgroup);

    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','preferredcountries'), 'preferredCountries', 'text', NULL, 'fr', $viewgroup);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','initialcountry'), 'initialCountry', 'text', NULL, 'fr', $viewgroup);


  }

  function my_display_deferred(&$r) {
    try {
      $format = in_array($this->display_format, $this->possible_format) ? $this->display_format : PhoneNumberFormat::NATIONAL;
      $phoneUtil = PhoneNumberUtil::getInstance();
      $number = $phoneUtil->parse($r->raw);
      $r->html = $phoneUtil->format($number, $format);
    }
    catch(\Throwable $e) {
      $r->html = $r->raw;
    }

    $r->text = $r->html;

    if($this->generate_link) {
      $r->html = '<a href="tel:'.$r->raw.'">'.$r->html.'</a>';
    }

    return $r;
  }

  function my_edit(&$value, &$options, &$fields_complement = NULL) {
    $ret = parent::my_edit($value, $options, $fields_complement);

    if (!$GLOBALS['TZR_PACKS']->packDefined('\Seolan\Pack\Phone\Phone')) {
      return $ret;
    }

    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname = $this->field."[$o]";
      $fnamehid = $this->field."_HID[$o]";
    }
    elseif(!empty($options['fieldname'])) {
      $fname = $options['fieldname'];
      $fnamehid = $options['fieldname']."_HID";
    }
    else {
      $fname = $this->field;
      $fnamehid = $this->field."_HID";
    }

    $packPath = PhonePack::ressourcepath();
    $id = $ret->varid;
    $raw = $ret->raw;
    $type = $this->type;
    $class = $this->compulsory ? "tzr-input-compulsory" : '';
    $class .= $this->error ? " error_field" : '';
    $class = $class ? "class='$class'" : '';
    $req = $this->compulsory ? 'required' : '';

    $preferredCountries = 'fr';
    if($this->preferredCountries) {
      $preferredCountries = preg_split('/[^a-z]+/', $this->preferredCountries, null, PREG_SPLIT_NO_EMPTY);
      $preferredCountries = count($preferredCountries) ? implode("','", $preferredCountries) : 'fr';
    }
    $initialCountry = $this->initialCountry ?: 'fr';

    $ret->html = "
      <input type='$type' name='$fname' id='$id' value='$raw' $class $req>
      <input type='hidden' name='$fnamehid' id='${id}_HID' value='$raw'>
      <script>
        var input_$id = document.querySelector('#$id');
        var hid_$id = document.querySelector('#${id}_HID');
        var iti_$id = window.intlTelInput(input_$id, {
          preferredCountries: ['$preferredCountries'],
          initialCountry: '$initialCountry',
          utilsScript: '$packPath/js/utils.js'
        });

        jQuery(input_$id).on('keyup change', {input: input_$id, hid: hid_$id, iti: iti_$id}, TZR.phoneFormat);
      </script>
    ";

    return $ret;
  }

  function post_edit($value, $options = NULL, &$fields_complement = NULL) {
    $value = @$options[$this->field.'_HID'] ?: $value;

    return parent::post_edit($value, $options, $fields_complement);
  }

}
