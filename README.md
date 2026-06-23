# MVP v1 – Hydroponic Farm Management System

## Project Overview

A hydroponic NFT farm management application designed to record daily operational activities, with a primary focus on nutrient quality monitoring and corrective action tracking.

### MVP Scope (Phase 1)

**Target Completion:** 1–2 Weeks

#### Core Features

- [ ] Authentication (Login)
    - Required to identify which user created or modified records.
- [ ] Daily PPM Monitoring
- [ ] Daily pH Monitoring
- [ ] Nutrient Addition Logging (AB Mix)
- [ ] pH Down Usage Logging
- [ ] Tank Condition Dashboard

### Out of Scope

The following features are explicitly excluded from MVP v1:

- IoT Sensors
- Pump Automation
- Accounting
- Sales Management
- Multi-Tenant Support
- AI-Based Predictions

---

# Non-Functional Requirements

### Usability

- Responsive mobile web interface
- Fast CRUD operations
- Simple operator workflow

### Data Management

- Soft delete support
- Form validation
- Audit timestamps

    - created_at
    - updated_at

- Pagination
- Search and filtering

---

# Future Roadmap (Not Included in MVP)

## Phase 2

### Farm Operations

- Bed Management
- Planting Cycle Management
- Harvest Management

### Cost & Maintenance Tracking

- Expense Tracking
- Trichoderma Application Log
- Fungicide Application Log
- Insecticide Application Log
- Yellow Trap Reminder System

---

## Phase 3

### Platform Expansion

- Mobile Application
- Push Notifications
- QR Code per Bed
- Multi-Farm Support
- IoT Integration

---

# Success Criteria

The MVP will be considered successful when:

- Operators can record daily PPM readings in less than 30 seconds.
- Operators can record daily pH readings in less than 30 seconds.
- Operators can view nutrient adjustment history for each tank.
- Operators can view total nutrient consumption and pH Down usage for a selected period.

---

# Technical Requirements

## Backend

- Laravel 12
- PHP 8.4
- PostgreSQL

## Frontend

- Laravel Blade
- Livewire 3
- Tailwind CSS

## Authentication

- Laravel Breeze

## Charts & Visualization

- ApexCharts

## API

- REST API Ready

## Deployment

- Docker

---

# Recommended MVP Modules

### 1. Authentication

Features:

- Login
- Logout
- User session management

User Fields:

- id
- name
- email
- password
- created_at
- updated_at

---

### 2. Tank Management

Purpose:

Manage hydroponic nutrient tanks.

Fields:

- id
- name
- capacity_liter
- notes
- is_active
- created_at
- updated_at

Features:

- Create tank
- Edit tank
- Deactivate tank
- View tank details

---

### 3. Daily Monitoring

Purpose:

Record daily tank conditions.

Fields:

- id
- tank_id
- log_date
- ppm
- ph
- water_temperature (optional)
- notes
- created_by
- created_at
- updated_at

Validation:

- PPM: 0–3000
- pH: 0–14
- One monitoring record per tank per day

Features:

- Create monitoring record
- Edit monitoring record
- Delete monitoring record
- View monitoring history

---

### 4. Nutrient Addition Log

Purpose:

Track AB Mix nutrient adjustments.

Fields:

- id
- tank_id
- log_date
- ppm_before
- ppm_after
- nutrient_a_ml
- nutrient_b_ml
- notes
- created_by
- created_at

Business Rules:

- ppm_after must be greater than ppm_before

Features:

- Create log
- Edit log
- Delete log
- View history

---

### 5. pH Down Log

Purpose:

Track pH correction activities.

Fields:

- id
- tank_id
- log_date
- ph_before
- ph_after
- ph_down_ml
- notes
- created_by
- created_at

Business Rules:

- ph_after must be lower than ph_before

Features:

- Create log
- Edit log
- Delete log
- View history

---

### 6. Dashboard

Purpose:

Provide an overview of all tanks.

For each tank display:

- Tank name
- Capacity
- Latest PPM
- Latest pH
- Last monitoring date
- Active status

Example:

Tank A

- Capacity: 900 L
- PPM: 850
- pH: 6.2
- Last Updated: June 10, 2026 08:00

Additional Widgets:

- Recent monitoring activity
- Recent nutrient additions
- Recent pH Down usage
- User activity log

---

### 7. Reports

#### Monitoring Report

Filters:

- Tank
- Start Date
- End Date

Output:

- Average PPM
- Highest PPM
- Lowest PPM
- Average pH
- Highest pH
- Lowest pH

---

#### Nutrient Usage Report

Filters:

- Tank
- Period

Output:

- Total Nutrient A Used (ml)
- Total Nutrient B Used (ml)

---

#### pH Down Usage Report

Filters:

- Tank
- Period

Output:

- Total pH Down Used (ml)

---

# Suggested Database Tables

- users
- tanks
- daily_monitoring_logs
- nutrient_addition_logs
- ph_down_logs
- activity_logs

Total MVP Tables: 6
