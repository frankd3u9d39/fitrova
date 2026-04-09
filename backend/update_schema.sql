USE fitrova_db;
ALTER TABLE user_profiles ADD COLUMN survey_step VARCHAR(50) DEFAULT 'Personalization';
