CREATE TABLE `userlevel_permissions` (
  `userlevelid` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 0,
  `can_insert` tinyint(1) NOT NULL DEFAULT 0,
  `can_update` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`userlevelid`,`resource_id`),
  CONSTRAINT `fk_ulperm_userlevels` FOREIGN KEY (`userlevelid`) REFERENCES `userlevels` (`userlevelid`) ON DELETE CASCADE,
  CONSTRAINT `fk_ulperm_resources` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO resources (name) VALUES
  ('page:lista_spesa.php'),
  ('ajax:add_lista_spesa'),
  ('ajax:update_lista_spesa'),
  ('ajax:get_lista_spesa');

INSERT INTO userlevel_permissions (userlevelid, resource_id, can_view, can_insert, can_update, can_delete) VALUES
  (-1, (SELECT id FROM resources WHERE name='page:lista_spesa.php'), 1,1,1,1),
  (-1, (SELECT id FROM resources WHERE name='ajax:add_lista_spesa'), 1,1,1,1),
  (-1, (SELECT id FROM resources WHERE name='ajax:update_lista_spesa'), 1,1,1,1),
  (-1, (SELECT id FROM resources WHERE name='ajax:get_lista_spesa'), 1,1,1,1);
