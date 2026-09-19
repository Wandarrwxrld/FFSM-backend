<?php
/**
 * Auto-generated from the real MySQL schema (schema_parsed.json).
 * This is the single source of truth the generic CRUD endpoint
 * (api/records.php) uses to whitelist table names, column names,
 * and which roles may touch which table. Never build SQL from
 * raw client input without checking it against this file first.
 */

return [
    'farms' => [
        'pk' => 'farm_id',
        'roles' => ['admin', 'owner'],
        'columns' => ['owner_id', 'farm_name', 'total_area_hectares', 'latitude', 'longitude', 'address'],
        'foreign_keys' => ['owner_id' => ['table' => 'users', 'column' => 'user_id']],
    ],
    'fields' => [
        'pk' => 'field_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['farm_id', 'field_name', 'area_hectares', 'gps_boundaries', 'soil_type', 'ph_level', 'organic_matter_pct', 'current_land_use'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'crop_varieties' => [
        'pk' => 'variety_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['crop_name', 'variety_name', 'typical_maturity_days', 'optimal_density_per_ha'],
        'foreign_keys' => [],
    ],
    'crop_plantings' => [
        'pk' => 'planting_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['field_id', 'variety_id', 'planting_date', 'expected_harvest_date', 'actual_harvest_date', 'planted_area_hectares', 'planting_density_per_ha', 'spacing_row_cm', 'spacing_plant_cm', 'status'],
        'foreign_keys' => ['field_id' => ['table' => 'fields', 'column' => 'field_id'], 'variety_id' => ['table' => 'crop_varieties', 'column' => 'variety_id']],
    ],
    'crop_growth_logs' => [
        'pk' => 'log_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist', 'worker'],
        'columns' => ['planting_id', 'log_date', 'growth_stage', 'avg_height_cm', 'health_status', 'remarks'],
        'foreign_keys' => ['planting_id' => ['table' => 'crop_plantings', 'column' => 'planting_id']],
    ],
    'water_sources' => [
        'pk' => 'source_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['farm_id', 'source_name', 'capacity_cubic_meters'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'irrigation_systems' => [
        'pk' => 'system_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['field_id', 'source_id', 'system_type', 'installation_date', 'flow_rate_liters_per_hr', 'status'],
        'foreign_keys' => ['field_id' => ['table' => 'fields', 'column' => 'field_id'], 'source_id' => ['table' => 'water_sources', 'column' => 'source_id']],
    ],
    'irrigation_schedules' => [
        'pk' => 'schedule_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist', 'worker'],
        'columns' => ['system_id', 'start_time', 'duration_minutes', 'water_volume_liters', 'electricity_cost', 'status'],
        'foreign_keys' => ['system_id' => ['table' => 'irrigation_systems', 'column' => 'system_id']],
    ],
    'livestock' => [
        'pk' => 'animal_id',
        'roles' => ['admin', 'owner', 'manager', 'worker'],
        'columns' => ['farm_id', 'tag_number', 'species', 'breed', 'gender', 'birth_date', 'sire_id', 'dam_id', 'status'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id'], 'sire_id' => ['table' => 'livestock', 'column' => 'animal_id'], 'dam_id' => ['table' => 'livestock', 'column' => 'animal_id']],
    ],
    'livestock_health_records' => [
        'pk' => 'record_id',
        'roles' => ['admin', 'owner', 'manager', 'worker'],
        'columns' => ['animal_id', 'record_type', 'diagnosis', 'treatment_details', 'batch_id', 'dosage', 'cost', 'administered_date', 'next_due_date'],
        'foreign_keys' => ['animal_id' => ['table' => 'livestock', 'column' => 'animal_id'], 'batch_id' => ['table' => 'inventory_batches', 'column' => 'batch_id']],
    ],
    'livestock_production_logs' => [
        'pk' => 'production_id',
        'roles' => ['admin', 'owner', 'manager', 'worker'],
        'columns' => ['animal_id', 'farm_id', 'production_type', 'quantity', 'unit', 'logged_date'],
        'foreign_keys' => ['animal_id' => ['table' => 'livestock', 'column' => 'animal_id'], 'farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'livestock_feed_logs' => [
        'pk' => 'feed_log_id',
        'roles' => ['admin', 'owner', 'manager', 'worker'],
        'columns' => ['farm_id', 'species', 'batch_id', 'quantity_used', 'feeding_date'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id'], 'batch_id' => ['table' => 'inventory_batches', 'column' => 'batch_id']],
    ],
    'inventory_items' => [
        'pk' => 'item_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['farm_id', 'item_name', 'category', 'unit_of_measure', 'current_stock', 'reorder_threshold'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'inventory_batches' => [
        'pk' => 'batch_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['item_id', 'supplier_id', 'batch_number', 'quantity_received', 'quantity_remaining', 'cost_per_unit', 'purchase_date', 'expiry_date'],
        'foreign_keys' => ['item_id' => ['table' => 'inventory_items', 'column' => 'item_id'], 'supplier_id' => ['table' => 'suppliers', 'column' => 'supplier_id']],
    ],
    'inventory_transactions' => [
        'pk' => 'transaction_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['batch_id', 'transaction_type', 'quantity', 'reference_type', 'reference_id', 'transaction_date', 'performed_by', 'notes'],
        'foreign_keys' => ['batch_id' => ['table' => 'inventory_batches', 'column' => 'batch_id'], 'performed_by' => ['table' => 'users', 'column' => 'user_id']],
    ],
    'suppliers' => [
        'pk' => 'supplier_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['supplier_name', 'contact_person', 'phone', 'email', 'address', 'rating'],
        'foreign_keys' => [],
    ],
    'purchase_orders' => [
        'pk' => 'po_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['farm_id', 'supplier_id', 'order_date', 'total_amount', 'status', 'approved_by'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id'], 'supplier_id' => ['table' => 'suppliers', 'column' => 'supplier_id'], 'approved_by' => ['table' => 'users', 'column' => 'user_id']],
    ],
    'machinery' => [
        'pk' => 'machinery_id',
        'roles' => ['admin', 'owner', 'manager', 'worker'],
        'columns' => ['farm_id', 'name', 'type', 'serial_number', 'purchase_price', 'purchase_date', 'current_operating_hours', 'status'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'machinery_maintenance' => [
        'pk' => 'maintenance_id',
        'roles' => ['admin', 'owner', 'manager', 'worker'],
        'columns' => ['machinery_id', 'maintenance_type', 'service_date', 'operating_hours_at_service', 'description', 'cost', 'next_service_due_hours'],
        'foreign_keys' => ['machinery_id' => ['table' => 'machinery', 'column' => 'machinery_id']],
    ],
    'machinery_usage' => [
        'pk' => 'usage_id',
        'roles' => ['admin', 'owner', 'manager', 'worker'],
        'columns' => ['machinery_id', 'field_id', 'driver_id', 'date_used', 'hours_worked', 'fuel_consumed_liters'],
        'foreign_keys' => ['machinery_id' => ['table' => 'machinery', 'column' => 'machinery_id'], 'field_id' => ['table' => 'fields', 'column' => 'field_id'], 'driver_id' => ['table' => 'users', 'column' => 'user_id']],
    ],
    'employees' => [
        'pk' => 'employee_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['farm_id', 'user_id', 'first_name', 'last_name', 'national_id', 'designation', 'employment_type', 'daily_rate', 'hire_date'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id'], 'user_id' => ['table' => 'users', 'column' => 'user_id']],
    ],
    'task_allocations' => [
        'pk' => 'task_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['farm_id', 'field_id', 'title', 'description', 'task_type', 'start_date', 'due_date', 'status'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id'], 'field_id' => ['table' => 'fields', 'column' => 'field_id']],
    ],
    'labor_assignments' => [
        'pk' => 'assignment_id',
        'roles' => ['admin', 'owner', 'manager'],
        'columns' => ['task_id', 'employee_id', 'hours_worked', 'date_performed', 'labor_cost'],
        'foreign_keys' => ['task_id' => ['table' => 'task_allocations', 'column' => 'task_id'], 'employee_id' => ['table' => 'employees', 'column' => 'employee_id']],
    ],
    'pest_disease_catalog' => [
        'pk' => 'catalog_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['name', 'type', 'symptoms', 'recommended_control'],
        'foreign_keys' => [],
    ],
    'scouting_records' => [
        'pk' => 'scouting_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist', 'worker'],
        'columns' => ['field_id', 'scout_id', 'scout_date', 'catalog_id', 'severity_level', 'affected_area_pct', 'image_url', 'notes'],
        'foreign_keys' => ['field_id' => ['table' => 'fields', 'column' => 'field_id'], 'scout_id' => ['table' => 'users', 'column' => 'user_id'], 'catalog_id' => ['table' => 'pest_disease_catalog', 'column' => 'catalog_id']],
    ],
    'chemical_applications' => [
        'pk' => 'application_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['scouting_id', 'planting_id', 'batch_id', 'application_date', 'dosage_per_ha', 'total_chemical_used', 'effectiveness'],
        'foreign_keys' => ['scouting_id' => ['table' => 'scouting_records', 'column' => 'scouting_id'], 'planting_id' => ['table' => 'crop_plantings', 'column' => 'planting_id'], 'batch_id' => ['table' => 'inventory_batches', 'column' => 'batch_id']],
    ],
    'weather_logs' => [
        'pk' => 'weather_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['farm_id', 'log_timestamp', 'temp_celsius', 'humidity_pct', 'rainfall_mm', 'wind_speed_kmh', 'forecast_summary'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'harvests' => [
        'pk' => 'harvest_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['planting_id', 'harvest_date', 'quantity_harvested', 'unit_of_measure', 'grade', 'loss_quantity', 'loss_reason'],
        'foreign_keys' => ['planting_id' => ['table' => 'crop_plantings', 'column' => 'planting_id']],
    ],
    'warehouses' => [
        'pk' => 'warehouse_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['farm_id', 'name', 'type', 'capacity_metric_tons'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'produce_storage' => [
        'pk' => 'storage_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist'],
        'columns' => ['harvest_id', 'warehouse_id', 'quantity_stored', 'entry_date', 'spoilage_quantity', 'status'],
        'foreign_keys' => ['harvest_id' => ['table' => 'harvests', 'column' => 'harvest_id'], 'warehouse_id' => ['table' => 'warehouses', 'column' => 'warehouse_id']],
    ],
    'customers' => [
        'pk' => 'customer_id',
        'roles' => ['admin', 'owner', 'manager', 'accountant'],
        'columns' => ['customer_name', 'company_name', 'contact_phone', 'email', 'address'],
        'foreign_keys' => [],
    ],
    'sales_orders' => [
        'pk' => 'order_id',
        'roles' => ['admin', 'owner', 'manager', 'accountant'],
        'columns' => ['farm_id', 'customer_id', 'order_date', 'total_amount', 'payment_status', 'order_status'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id'], 'customer_id' => ['table' => 'customers', 'column' => 'customer_id']],
    ],
    'sales_order_items' => [
        'pk' => 'item_id',
        'roles' => ['admin', 'owner', 'manager', 'accountant'],
        'columns' => ['order_id', 'harvest_id', 'production_id', 'description', 'quantity', 'unit_price', 'subtotal'],
        'foreign_keys' => ['order_id' => ['table' => 'sales_orders', 'column' => 'order_id'], 'harvest_id' => ['table' => 'harvests', 'column' => 'harvest_id'], 'production_id' => ['table' => 'livestock_production_logs', 'column' => 'production_id']],
    ],
    'financial_transactions' => [
        'pk' => 'transaction_id',
        'roles' => ['admin', 'owner', 'accountant'],
        'columns' => ['farm_id', 'transaction_type', 'category', 'amount', 'transaction_date', 'reference_type', 'reference_id', 'description'],
        'foreign_keys' => ['farm_id' => ['table' => 'farms', 'column' => 'farm_id']],
    ],
    'notifications' => [
        'pk' => 'notification_id',
        'roles' => ['admin', 'owner', 'manager', 'agronomist', 'accountant', 'worker'],
        'columns' => ['user_id', 'alert_type', 'message', 'is_read'],
        'foreign_keys' => ['user_id' => ['table' => 'users', 'column' => 'user_id']],
    ],
];
