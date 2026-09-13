-- Zusätzliches Datumsfeld: wann die Trainingsanfrage eingegangen ist.
-- Für bestehende Installationen einmalig einspielen:
--   mysql -u USER -p DBNAME < migrations/001-trainingsanfrage.sql

ALTER TABLE participants
  ADD COLUMN request_date DATE NULL AFTER birth_year;
