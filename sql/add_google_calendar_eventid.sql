ALTER TABLE `turni_calendario`
  ADD COLUMN `google_calendar_eventid` varchar(255) DEFAULT NULL AFTER `id_tipo`,
  ADD UNIQUE KEY `google_calendar_eventid` (`google_calendar_eventid`);

ALTER TABLE `eventi`
  ADD COLUMN `google_calendar_eventid` varchar(255) DEFAULT NULL AFTER `note`,
  ADD UNIQUE KEY `google_calendar_eventid` (`google_calendar_eventid`);
