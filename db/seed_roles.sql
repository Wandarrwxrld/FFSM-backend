-- ============================================================================
-- FFMS — ROLE SEED
-- Must match the role ids used throughout the front end (js/store.js ROLES)
-- exactly, since auth_signup.php looks up a role by this exact name.
-- Safe to re-run.
-- ============================================================================

USE ffms_db;

INSERT INTO roles (role_name, description) VALUES
    ('admin',      'Full system access — manages all users, roles, farms, and system settings.'),
    ('owner',      'Owns one or more farms; sees everything for their own farm(s).'),
    ('manager',    'Runs day-to-day operations for an assigned farm.'),
    ('agronomist', 'Crop and field focus — planting, growth monitoring, pest & disease.'),
    ('accountant', 'Financial module — income, expenses, budgets, payroll, reports.'),
    ('worker',     'Limited, task-focused access — assigned tasks, field and livestock logs.')
ON DUPLICATE KEY UPDATE description = VALUES(description);
