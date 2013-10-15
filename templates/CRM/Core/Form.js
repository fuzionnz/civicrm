cj(function ($) {
  'use strict';
  $('.form-submit').on("click", function() {
  if($('.crm-form-button-submit').attr('value') == 'Processing'
    || $('.crm-form-button-upload').attr('value') == 'Processing'
  ) {
    return false;
  }
  $('.crm-form-button-submit').attr({value: 'Processing'}).closest('form').submit();
  $('.crm-form-button-upload').attr({value: 'Processing'}).closest('form').submit();

  $('.crm-form-button-back ').closest('span').hide();
  $('.crm-form-button-cancel').closest('span').hide();
  return false;
  });
});

