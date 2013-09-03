cj(function ($) {
  'use strict';
   $().crmaccordions();
   $('.form-submit').on("click", function(event){
     $('.form-submit').attr('value','Processing');
     $('.form-submit').attr('disabled','Disabled');
   });
});