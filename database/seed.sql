-- AI Card Collector — development seed data
-- Usage: mysql -u root ai_card_collector < database/seed.sql
--
-- To generate the password hash locally, run in PHP:
--   php -r "echo password_hash('your-chosen-password', PASSWORD_BCRYPT) . PHP_EOL;"
-- Then replace the placeholder below with the output before running this file.
-- Never store a real password in this file — use a throwaway local password only.

INSERT INTO users (email, password_hash)
VALUES (
    'sebastian@example.local',
    '$2y$12$REPLACE_THIS_WITH_OUTPUT_OF_password_hash_FUNCTION'
);
