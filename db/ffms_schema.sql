-- ============================================================================
-- FULL FARM MANAGEMENT SYSTEM (FFMS) - DATABASE SCHEMA
-- Engine: InnoDB | Charset: utf8mb4 | Database: MySQL / MariaDB
-- ============================================================================

CREATE DATABASE IF NOT EXISTS ffms_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ffms_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- MODULE 21: SECURITY, USERS & ACCESS CONTROL
-- ----------------------------------------------------------------------------

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE, -- Admin, Owner, Manager, Agronomist, Worker, Accountant
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    table_affected VARCHAR(50),
    record_id INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 1: FARM & FIELD MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE farms (
    farm_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    farm_name VARCHAR(100) NOT NULL,
    total_area_hectares DECIMAL(10,2) NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE fields (
    field_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    area_hectares DECIMAL(10,2) NOT NULL,
    gps_boundaries GEOMETRY, -- Polygon boundary
    soil_type VARCHAR(50),
    ph_level DECIMAL(3,1),
    organic_matter_pct DECIMAL(4,2),
    current_land_use VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 2: CROP MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE crop_varieties (
    variety_id INT AUTO_INCREMENT PRIMARY KEY,
    crop_name VARCHAR(100) NOT NULL, -- e.g. Maize, Tomato
    variety_name VARCHAR(100) NOT NULL, -- e.g. SC719, MoneyMaker
    typical_maturity_days INT,
    optimal_density_per_ha INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE crop_plantings (
    planting_id INT AUTO_INCREMENT PRIMARY KEY,
    field_id INT NOT NULL,
    variety_id INT NOT NULL,
    planting_date DATE NOT NULL,
    expected_harvest_date DATE,
    actual_harvest_date DATE,
    planted_area_hectares DECIMAL(10,2) NOT NULL,
    planting_density_per_ha INT,
    spacing_row_cm DECIMAL(5,2),
    spacing_plant_cm DECIMAL(5,2),
    status ENUM('planned', 'active', 'harvested', 'failed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE CASCADE,
    FOREIGN KEY (variety_id) REFERENCES crop_varieties(variety_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE crop_growth_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    planting_id INT NOT NULL,
    log_date DATE NOT NULL,
    growth_stage VARCHAR(50), -- Germination, Vegetative, Flowering, Maturity
    avg_height_cm DECIMAL(5,2),
    health_status VARCHAR(100),
    remarks TEXT,
    FOREIGN KEY (planting_id) REFERENCES crop_plantings(planting_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 6 & 14: SUPPLIERS, PROCUREMENT & INVENTORY
-- ----------------------------------------------------------------------------

CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    rating DECIMAL(2,1),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    category ENUM('seed', 'fertilizer', 'chemical', 'feed', 'medicine', 'packaging', 'fuel', 'tool', 'other') NOT NULL,
    unit_of_measure VARCHAR(20) NOT NULL, -- kg, L, bags, units
    current_stock DECIMAL(10,2) DEFAULT 0.00,
    reorder_threshold DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inventory_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    supplier_id INT,
    batch_number VARCHAR(50),
    quantity_received DECIMAL(10,2) NOT NULL,
    quantity_remaining DECIMAL(10,2) NOT NULL,
    cost_per_unit DECIMAL(10,2) NOT NULL,
    purchase_date DATE NOT NULL,
    expiry_date DATE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE inventory_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    transaction_type ENUM('stock_in', 'stock_out', 'adjustment', 'return') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    reference_type VARCHAR(50), -- e.g., 'fertilizer_application', 'feed_log'
    reference_id INT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    performed_by INT,
    notes TEXT,
    FOREIGN KEY (batch_id) REFERENCES inventory_batches(batch_id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
    po_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('draft', 'requested', 'approved', 'fulfilled', 'cancelled') DEFAULT 'draft',
    approved_by INT,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 4: IRRIGATION & WATER MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE water_sources (
    source_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    source_name VARCHAR(100) NOT NULL, -- River, Borehole, Dam
    capacity_cubic_meters DECIMAL(12,2),
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE irrigation_systems (
    system_id INT AUTO_INCREMENT PRIMARY KEY,
    field_id INT NOT NULL,
    source_id INT NOT NULL,
    system_type ENUM('drip', 'sprinkler', 'pivot', 'flood', 'other') NOT NULL,
    installation_date DATE,
    flow_rate_liters_per_hr DECIMAL(10,2),
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES water_sources(source_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE irrigation_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    system_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL,
    water_volume_liters DECIMAL(10,2),
    electricity_cost DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    FOREIGN KEY (system_id) REFERENCES irrigation_systems(system_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 5: LIVESTOCK MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE livestock (
    animal_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    tag_number VARCHAR(50) NOT NULL UNIQUE,
    species VARCHAR(50) NOT NULL, -- Cattle, Poultry, Goat
    breed VARCHAR(50),
    gender ENUM('male', 'female') NOT NULL,
    birth_date DATE,
    sire_id INT NULL,
    dam_id INT NULL,
    status ENUM('active', 'sold', 'deceased') DEFAULT 'active',
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE,
    FOREIGN KEY (sire_id) REFERENCES livestock(animal_id) ON DELETE SET NULL,
    FOREIGN KEY (dam_id) REFERENCES livestock(animal_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE livestock_health_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    record_type ENUM('vaccination', 'treatment', 'disease_check') NOT NULL,
    diagnosis VARCHAR(255),
    treatment_details TEXT,
    batch_id INT, -- Refers to inventory_batches (medicine used)
    dosage DECIMAL(8,2),
    cost DECIMAL(10,2) DEFAULT 0.00,
    administered_date DATE NOT NULL,
    next_due_date DATE,
    FOREIGN KEY (animal_id) REFERENCES livestock(animal_id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES inventory_batches(batch_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE livestock_production_logs (
    production_id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT, -- Optional: NULL for flock/herd level entries
    farm_id INT NOT NULL,
    production_type ENUM('milk', 'eggs', 'wool', 'meat', 'manure') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL, -- L, kg, count
    logged_date DATE NOT NULL,
    FOREIGN KEY (animal_id) REFERENCES livestock(animal_id) ON DELETE CASCADE,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE livestock_feed_logs (
    feed_log_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    species VARCHAR(50),
    batch_id INT NOT NULL, -- Inventory batch for feed
    quantity_used DECIMAL(10,2) NOT NULL,
    feeding_date DATE NOT NULL,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES inventory_batches(batch_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 7: FARM EQUIPMENT & MACHINERY
-- ----------------------------------------------------------------------------

CREATE TABLE machinery (
    machinery_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL, -- Tractor, Harvester, Pump
    serial_number VARCHAR(100) UNIQUE,
    purchase_price DECIMAL(12,2),
    purchase_date DATE,
    current_operating_hours DECIMAL(8,2) DEFAULT 0.00,
    status ENUM('operational', 'under_maintenance', 'out_of_service') DEFAULT 'operational',
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE machinery_maintenance (
    maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'inspection') NOT NULL,
    service_date DATE NOT NULL,
    operating_hours_at_service DECIMAL(8,2),
    description TEXT,
    cost DECIMAL(10,2) DEFAULT 0.00,
    next_service_due_hours DECIMAL(8,2),
    FOREIGN KEY (machinery_id) REFERENCES machinery(machinery_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE machinery_usage (
    usage_id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id INT NOT NULL,
    field_id INT,
    driver_id INT,
    date_used DATE NOT NULL,
    hours_worked DECIMAL(5,2) NOT NULL,
    fuel_consumed_liters DECIMAL(6,2),
    FOREIGN KEY (machinery_id) REFERENCES machinery(machinery_id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 8: LABOUR & EMPLOYEE MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    user_id INT UNIQUE, -- Optional link if employee has system access
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    national_id VARCHAR(50) UNIQUE,
    designation VARCHAR(50),
    employment_type ENUM('permanent', 'casual', 'contract') NOT NULL,
    daily_rate DECIMAL(10,2) DEFAULT 0.00,
    hire_date DATE,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE task_allocations (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    field_id INT,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    task_type VARCHAR(50), -- Planting, Weeding, Spraying, Harvesting
    start_date DATE,
    due_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE labor_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    employee_id INT NOT NULL,
    hours_worked DECIMAL(5,2) DEFAULT 0.00,
    date_performed DATE NOT NULL,
    labor_cost DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (task_id) REFERENCES task_allocations(task_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 9: PEST & DISEASE MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE pest_disease_catalog (
    catalog_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('pest', 'disease', 'weed') NOT NULL,
    symptoms TEXT,
    recommended_control TEXT
) ENGINE=InnoDB;

CREATE TABLE scouting_records (
    scouting_id INT AUTO_INCREMENT PRIMARY KEY,
    field_id INT NOT NULL,
    scout_id INT NOT NULL,
    scout_date DATE NOT NULL,
    catalog_id INT,
    severity_level ENUM('low', 'moderate', 'high', 'severe') NOT NULL,
    affected_area_pct DECIMAL(5,2),
    image_url VARCHAR(255),
    notes TEXT,
    FOREIGN KEY (field_id) REFERENCES fields(field_id) ON DELETE CASCADE,
    FOREIGN KEY (scout_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (catalog_id) REFERENCES pest_disease_catalog(catalog_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE chemical_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    scouting_id INT,
    planting_id INT NOT NULL,
    batch_id INT NOT NULL, -- Inventory chemical batch
    application_date DATE NOT NULL,
    dosage_per_ha DECIMAL(8,2) NOT NULL,
    total_chemical_used DECIMAL(10,2) NOT NULL,
    effectiveness ENUM('effective', 'partially_effective', 'ineffective', 'pending') DEFAULT 'pending',
    FOREIGN KEY (scouting_id) REFERENCES scouting_records(scouting_id) ON DELETE SET NULL,
    FOREIGN KEY (planting_id) REFERENCES crop_plantings(planting_id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES inventory_batches(batch_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 10: WEATHER & ENVIRONMENTAL MONITORING
-- ----------------------------------------------------------------------------

CREATE TABLE weather_logs (
    weather_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    log_timestamp DATETIME NOT NULL,
    temp_celsius DECIMAL(4,1),
    humidity_pct DECIMAL(4,1),
    rainfall_mm DECIMAL(6,2),
    wind_speed_kmh DECIMAL(5,2),
    forecast_summary VARCHAR(255),
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 11 & 15: HARVEST, STORAGE & POST-HARVEST MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE warehouses (
    warehouse_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('ambient', 'cold_storage', 'silo', 'shed') NOT NULL,
    capacity_metric_tons DECIMAL(10,2),
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE harvests (
    harvest_id INT AUTO_INCREMENT PRIMARY KEY,
    planting_id INT NOT NULL,
    harvest_date DATE NOT NULL,
    quantity_harvested DECIMAL(10,2) NOT NULL, -- in units/kg
    unit_of_measure VARCHAR(20) NOT NULL,
    grade VARCHAR(20), -- Grade A, Premium, etc.
    loss_quantity DECIMAL(10,2) DEFAULT 0.00,
    loss_reason TEXT,
    FOREIGN KEY (planting_id) REFERENCES crop_plantings(planting_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE produce_storage (
    storage_id INT AUTO_INCREMENT PRIMARY KEY,
    harvest_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity_stored DECIMAL(10,2) NOT NULL,
    entry_date DATE NOT NULL,
    spoilage_quantity DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('stored', 'dispatched', 'disposed') DEFAULT 'stored',
    FOREIGN KEY (harvest_id) REFERENCES harvests(harvest_id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 12: MARKET & SALES MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(100),
    contact_phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE sales_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('pending', 'partially_paid', 'paid') DEFAULT 'pending',
    order_status ENUM('pending', 'confirmed', 'dispatched', 'delivered', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE sales_order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    harvest_id INT, -- Associated crop batch
    production_id INT, -- Associated livestock batch
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES sales_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (harvest_id) REFERENCES harvests(harvest_id) ON DELETE SET NULL,
    FOREIGN KEY (production_id) REFERENCES livestock_production_logs(production_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 13: FINANCIAL MANAGEMENT
-- ----------------------------------------------------------------------------

CREATE TABLE financial_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    transaction_type ENUM('income', 'expense') NOT NULL,
    category VARCHAR(100) NOT NULL, -- Input purchase, Labor cost, Sales, Fuel, Maintenance
    amount DECIMAL(12,2) NOT NULL,
    transaction_date DATE NOT NULL,
    reference_type VARCHAR(50), -- e.g., 'sales_orders', 'purchase_orders'
    reference_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- MODULE 19: NOTIFICATIONS & ALERTS
-- ----------------------------------------------------------------------------

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL, -- Low Stock, Weather, Task Reminders
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;