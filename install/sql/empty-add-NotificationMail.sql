INSERT INTO `glpi_notificationtemplates` (`name`, `itemtype`, `date_mod`, `comment`, `css`, `date_creation`) VALUES ('Rapport PDF', 'Ticket', NULL, 'Created by the plugin RP', '', NULL);
INSERT INTO `glpi_notificationtemplatetranslations` (`notificationtemplates_id`, `language`, `subject`, `content_text`, `content_html`) VALUES (LAST_INSERT_ID(), '', '[GLPI ###ticket.id##] | ##rapport.type.titel## ', ' 		 \n\n##rapport.type.titel##\n\n 		 \n\nChère cliente, cher client,\n\nVeuillez trouver ci-joint ##rapport.type## en date du ##rapport.date.creation##\n\nVous trouverez l’ensemble des informations sur le lien suivant : ##ticket.url##\n\nSujet du ticket : ##ticket.title##\nNuméro du Ticket : ##ticket.id##\n\nLe PDF en ligne : ##document.weblink##\n\nCordialement,\n\nL\'équipe JCD\n\n_Ce courrier électronique est envoyé automatiquement par le centre de service Easi Support._\n\nGénéré automatiquement par GLPI.', '&#60;table cellspacing="0" cellpadding="0" align="center"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center"&#62;\r\n&#60;table width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="transparent"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="left"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left" width="270"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center"&#62;&#60;a&#62;&#60;img class="CToWUd" src="https://ci3.googleusercontent.com/meips/ADKq_NbMZUvFUDxtwMPhuNczY-aOMR16hRkHxEmquZBcZpKGBl9BC3YIrYH-z17yfdhPcPp09La0Nog_G4pdSYn2d-3ursWMp_Pw-Z9kmQWueHhG3wShJskYop-sxZyuZYniSHfXagm3suTdfg3Ll5YlcMOGD8GjB-o1oVpTK2c03gDvjVKIYeASbGwd=s0-d-e1-ft#https://fvjwbn.stripocdn.email/content/guids/CABINET_44164322675628a7251e1d7d361331e9/images/logoeasisupportnew.png" alt="" width="270" data-bit="iit"&#62;&#60;/a&#62;&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="right"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left" width="270"&#62; &#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="center"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center"&#62;\r\n&#60;table width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left" bgcolor="transparent"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center" valign="top" width="560"&#62;&#60;br&#62;\r\n&#60;table style="height: 331px;" width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr style="height: 37px;"&#62;\r\n&#60;td style="height: 37px;" align="center"&#62;\r\n&#60;div&#62;\r\n&#60;h2&#62;&#60;strong&#62;##rapport.type.titel##&#60;/strong&#62;&#60;/h2&#62;\r\n&#60;/div&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;tr style="height: 21px;"&#62;\r\n&#60;td style="height: 21px;" align="center"&#62; &#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;tr style="height: 273px;"&#62;\r\n&#60;td style="height: 273px;" align="left"&#62;\r\n&#60;h2&#62;Chère cliente, cher client,&#60;/h2&#62;\r\n&#60;p&#62;&#60;br&#62;Veuillez trouver ci-joint ##rapport.type## en date du ##rapport.date.creation##&#60;/p&#62;\r\n&#60;p&#62;Vous trouverez l’ensemble des informations sur le lien suivant : ##ticket.url##&#60;br&#62;&#60;br&#62;Sujet du ticket : ##ticket.title##&#60;br&#62;Numéro du Ticket : ##ticket.id##&#60;/p&#62;\r\n&#60;p&#62;Le PDF en ligne : ##document.weblink##&#60;/p&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="center"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center"&#62;\r\n&#60;table width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center" valign="top" width="600"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;\r\n&#60;p&#62;Cordialement,&#60;/p&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="center"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center"&#62;\r\n&#60;table width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="left"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left" width="180"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;&#60;a&#62;&#60;img class="CToWUd" src="https://ci3.googleusercontent.com/meips/ADKq_NaEqy6VWWDm6oXDTghtitNBpsEAJW4Y7U_gB_qLkXpa-YxlCy-x_y_C8PpoMO04E1b6HRXqdP0He4DkNFt5P7beMeR85v0eio-YPyDCYhE-SruwGX8SnV-urmM72buDbwYgKbDmkEjvtBrNEo1C5tMXbtKATt3d5rA4b5P7Jvvfl4Uf=s0-d-e1-ft#https://fvjwbn.stripocdn.email/content/guids/CABINET_44164322675628a7251e1d7d361331e9/images/logo_jcd_54G.png" alt="" width="80" data-bit="iit"&#62;&#60;/a&#62;&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="right"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left" width="360"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;\r\n&#60;p&#62;&#60;br&#62;&#60;br&#62;&#60;strong&#62;L\'équipe JCD&#60;/strong&#62;&#60;/p&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;table cellspacing="0" cellpadding="0" align="center"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center"&#62;\r\n&#60;table width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="center" valign="top" width="560"&#62;\r\n&#60;table width="100%" cellspacing="0" cellpadding="0"&#62;\r\n&#60;tbody&#62;\r\n&#60;tr&#62;\r\n&#60;td align="left"&#62;\r\n&#60;p&#62;&#60;br&#62;&#60;em&#62;Ce courrier électronique est envoyé automatiquement par le centre de service Easi Support.&#60;/em&#62;&#60;br&#62;&#60;br&#62;&#60;br&#62;Généré automatiquement par GLPI.&#60;/p&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;\r\n&#60;/td&#62;\r\n&#60;/tr&#62;\r\n&#60;/tbody&#62;\r\n&#60;/table&#62;');