# Database Design

## users

- id
- name
- password
- is_admin
- remember_token
- timestamps(created_at, updated_at, deleted_at)

## farms

- id
- created_by
- name
- address
- description
- timestamps(created_at, updated_at, deleted_at)

## farm_users

- id
- farm_id
- user_id
- role -- owner, manager, operator
- timestamps(created_at, updated_at, deleted_at)

## tanks

- id
- farm_id
- created_by
- name
- capacity_liter
- notes
- is_active
- timestamps(created_at, updated_at, deleted_at)

---

## daily_monitorings

- id
- user_id
- tank_id
- log_date
- ppm
- ph
- water_temperature
- notes
- timestamps(created_at, updated_at, deleted_at)

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
- timestamps(created_at, updated_at, deleted_at)

---

## ph_down_logs

- id
- user_id
- tank_id
- log_date
- ph_before
- ph_after
- ph_down_ml
- notes
- created_at
- updated_at
- deleted_at

---

## activity_logs

- id
- farm_id
- user_id
- action
- entity_type
- entity_id
- description
- created_at
