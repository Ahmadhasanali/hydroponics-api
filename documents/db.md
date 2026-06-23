# Database Design

## users

- id
- name
- password
- is_admin
- remember_token
- timestamps(created_at, updated_at)

## farms

- id
- user_id
- name
- address
- description

## tanks

- id
- farm_id
- name
- capacity_liter
- notes
- is_active
- created_at
- updated_at

---

## daily_monitorings

- id
- user_id
- farm_id
- tank_id
- log_date
- ppm
- ph
- water_temperature
- notes
- created_at
- updated_at

---

## nutrient_additions

- id
- user_id
- tank_id
- log_date
- ppm_before
- ppm_after
- nutrient_a_ml
- nutrient_b_ml
- notes
- created_at
- updated_at

---

## ph_down_logs

- id
- user_id
- farm_id
- tank_id
- log_date
- ph_before
- ph_after
- ph_down_ml
- notes
- created_at
- updated_at

---

## activity_logs

- id
- user_id
- action
- entity_type
- entity_id
- description
- created_at
