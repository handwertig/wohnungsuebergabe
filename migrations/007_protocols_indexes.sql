CREATE INDEX IF NOT EXISTS idx_protocols_created_at ON protocols (created_at);
CREATE INDEX IF NOT EXISTS idx_protocols_type ON protocols (type);
CREATE INDEX IF NOT EXISTS idx_protocols_unit_id ON protocols (unit_id);
CREATE INDEX IF NOT EXISTS idx_objects_city_street ON objects (city, street, house_no);
CREATE INDEX IF NOT EXISTS idx_units_label ON units (label);
