-- Placeholder which ensures that PHP upgrade tasks are executed

UPDATE civicrm_report_instance SET form_values = REPLACE(
  form_values,
  's:34:"participant_register_date_relative";s:1:"0";s:30:"participant_register_date_from";s:1:" ";s:28:"participant_register_date_to";s:1:" "',
  's:34:"participant_register_date_relative";s:1:"0";s:30:"participant_register_date_from";s:0:"";s:28:"participant_register_date_to";s:0:"";')
WHERE form_values LIKE '%s:34:"participant_register_date_relative";s:1:"0";s:30:"participant_register_date_from";s:1:" ";%'
;
UPDATE civicrm_report_instance SET form_values = REPLACE(
  form_values,
's:15:"receive_date_to";s:1:" ";',
's:15:"receive_date_to";s:0:"";'
)
WHERE form_values LIKE '%s:15:"receive_date_to";s:1:" ";%';
UPDATE civicrm_report_instance SET form_values = REPLACE(
  form_values,
's:17:"receive_date_from";s:1:" ";',
's:17:"receive_date_from";s:0:"";'
)
WHERE form_values LIKE '%s:17:"receive_date_from";s:1:" ";%';






