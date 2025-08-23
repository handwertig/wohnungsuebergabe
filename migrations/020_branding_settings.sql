-- Branding/Personalisierung
INSERT INTO app_settings (`key`,`value`) VALUES
  ('brand_primary', '#222357')
, ('brand_secondary', '#e22278')
, ('custom_css', '')
, ('pdf_logo_path', '')
ON DUPLICATE KEY UPDATE value=VALUES(value);
