# Functional Requirements

## 1. Dashboard

### Purpose

Display the latest status of all tanks.

### Information

For each tank, display:

- Tank name
- Capacity
- Latest PPM
- Latest pH
- Last input date
- Status

Example:

**Tank A**

- Capacity: 900 L
- PPM: 850
- pH: 6.2
- Last Update: June 10, 2026, 08:00

Also display user activity logs.

---

## 2. Tank Master Data

### Purpose

Manage nutrient tanks.

### Fields

- id
- name
- capacity_liter
- notes
- is_active
- created_at
- updated_at

### Features

- Create tank
- Edit tank
- Deactivate tank
- View tank details

---

## 3. Daily Monitoring Log

### Purpose

Record daily tank conditions.

### Fields

- id
- tank_id
- log_date
- ppm
- ph
- water_temperature (optional)
- notes
- created_at

### Features

- Add daily monitoring record
- Edit monitoring record
- Delete monitoring record
- View monitoring history

### Validation

**PPM**

- Minimum: 0
- Maximum: 3000

**pH**

- Minimum: 0
- Maximum: 14

Only one monitoring log is allowed per tank per date.

---

## 4. Nutrient Addition Log

### Purpose

Record every AB Mix nutrient addition.

### Fields

- id
- tank_id
- log_date
- ppm_before
- ppm_after
- nutrient_a_ml
- nutrient_b_ml
- notes
- created_at

### Features

- Add nutrient addition log
- Edit log
- Delete log
- View nutrient addition history

### Business Rules

- `ppm_after` must be greater than `ppm_before`.

---

## 5. pH Down Log

### Purpose

Record pH Down usage.

### Fields

- id
- tank_id
- log_date
- ph_before
- ph_after
- ph_down_ml
- notes
- created_at

### Features

- Add pH Down log
- Edit log
- Delete log
- View usage history

### Business Rules

- `ph_after` must be lower than `ph_before`.

---

## 6. Tank Detail Page

### Purpose

Display the complete history of a tank.

### Sections

#### Tank Information

- Name
- Capacity

#### Latest Condition

- Latest PPM
- Latest pH

#### Monitoring History

List of daily monitoring records.

#### Nutrient Addition History

List of nutrient addition records.

#### pH Down History

List of pH Down usage records.

---

## 7. Reports

### Monitoring Report

#### Filters

- Tank
- Start date
- End date

#### Output

- Average PPM
- Highest PPM
- Lowest PPM
- Average pH
- Highest pH
- Lowest pH

---

### Nutrient Usage Report

#### Output

- Total Nutrient A used
- Total Nutrient B used

#### Filters

- Period
- Tank

---

### pH Down Usage Report

#### Output

- Total pH Down used

#### Filters

- Period
- Tank
