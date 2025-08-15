ALTER TABLE `eventi`
  ADD COLUMN `creator_email` varchar(255) DEFAULT NULL AFTER `google_calendar_eventid`;
