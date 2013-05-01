DROP PROCEDURE IF EXISTS buildNavMenuItem;

DELIMITER //
CREATE PROCEDURE buildNavMenuItem(
       IN menulabel VARCHAR(255) CHARACTER SET utf8,
       IN domainID INT,
       IN grandParentID INT )
BEGIN
     DECLARE done TINYINT DEFAULT 0;
     DECLARE submenuLabel varchar(255) CHARACTER SET utf8;
     DECLARE menuID INT;
     DECLARE  cur_menu CURSOR FOR
       SELECT child.label COLLATE 'utf8_unicode_ci', child.id  FROM civicrm_navigation child LEFT JOIN
      civicrm_navigation parent ON child.parent_id = parent.id  AND child.domain_id = parent.domain_id
       WHERE parent.label = menuLabel COLLATE 'utf8_unicode_ci' AND child.domain_id = 1
       AND parent.id = grandParentID
      AND child.id IS NOT NULL;

     DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
   #  SELECT CONCAT(  "SELECT child.label COLLATE 'utf8_unicode_ci', child.id  FROM civicrm_navigation child LEFT JOIN       civicrm_navigation parent ON #child.parent_id = parent.id  AND child.domain_id = parent.domain_id        WHERE parent.label = ", menuLabel, " COLLATE 'utf8_unicode_ci' AND #child.domain_id = 1        AND parent.id = " , grandParentID );
   SELECT id FROM civicrm_navigation WHERE domain_id = domainID AND label = menulabel      COLLATE 'utf8_unicode_ci' LIMIT 1
    INTO @parentID;
      INSERT INTO civicrm_navigation (domain_id, label, name, url, `permission`, permission_operator,    is_active,  
      has_separator, weight, parent_id) 

       SELECT domainID as domain_id, n1.label, n1.name, n1.url,n1.`permission`, n1.permission_operator, n1.is_active,
      n1.has_separator, n1.weight, @parentID as parent_id 
      FROM civicrm_navigation n2 
        RIGHT JOIN (

           SELECT n.* FROM civicrm_navigation n
           INNER JOIN civicrm_navigation p ON p.id = n.parent_id
           WHERE p.name = menulabel COLLATE 'utf8_unicode_ci'
          AND n.domain_id = 1
) as n1 ON n1.label = n2.label AND n2.domain_id = domainID
WHERE n2.id IS NULL;

     OPEN cur_menu;
read_loop: LOOP
            FETCH cur_menu INTO submenuLabel, menuID;
     IF done THEN
      LEAVE read_loop;
     END IF;

     call buildNavMenuItem(submenuLabel COLLATE 'utf8_unicode_ci', domainID, menuID);
  END LOOP;

CLOSE cur_menu;

    END//
    
    DELIMITER ;
    
    DROP PROCEDURE IF EXISTS buildNavigation;

DELIMITER //


CREATE PROCEDURE buildNavigation()
  BEGIN
   DECLARE domainID INT;
   DECLARE menuID INT;
   DECLARE max_domain INT;
   DECLARE max_menu varchar(255) CHARACTER SET utf8;
   DECLARE menuLabel varchar(255) CHARACTER SET utf8;
   DECLARE done TINYINT DEFAULT 0;
   DECLARE  cur_domain CURSOR FOR
     SELECT id FROM civicrm_domain WHERE id <> 1;
   DECLARE  cur_menu CURSOR FOR
     SELECT label COLLATE 'utf8_unicode_ci', id  FROM civicrm_navigation WHERE parent_id IS NULL AND label <> 'Data' AND domain_id = 1;
   DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
   SELECT max(id) FROM civicrm_domain INTO max_domain;
   SELECT max(label) COLLATE 'utf8_unicode_ci' FROM civicrm_navigation WHERE parent_id IS NULL AND label <> 'Data' INTO max_menu;

   SET NAMES 'utf8' COLLATE 'utf8_unicode_ci';
   OPEN cur_domain;
   REPEAT
     FETCH  cur_domain INTO domainID;
     OPEN cur_menu;
     REPEAT
     FETCH cur_menu INTO menuLabel, menuID;
       call buildNavMenuItem(menuLabel COLLATE 'utf8_unicode_ci', domainID, menuID);
       UNTIL done = TRUE   
     END REPEAT;
     CLOSE cur_menu;
     SET done = FALSE;
   UNTIL domainID = max_domain
   END REPEAT;
   CLOSE cur_domain;
  END//
DELIMITER ;

SET max_sp_recursion_depth = 15 ;
call buildNavigation();
 TABLE aaa_nav_2 SELECT * FROM civicrm_navigation;